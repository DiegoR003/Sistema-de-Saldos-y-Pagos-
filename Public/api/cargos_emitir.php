<?php
// Public/api/cargos_emitir.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/date_utils.php';

function back(string $msg, bool $ok=true): never {
  $q = 'ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg);
  header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar&'.$q);
  exit;
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
  }

  $pdo = db();

  // --- Datos de entrada básicos ---
  $ordenId = (int)($_POST['orden_id'] ?? 0);
  if ($ordenId <= 0) back('Orden inválida', false);

  // 1) Determinar el periodo a emitir
  //    Prioridad: periodo_inicio / periodo_fin que mandas desde las vistas.
  $periodoIniStr = trim((string)($_POST['periodo_inicio'] ?? ''));
  $periodoFinStr = trim((string)($_POST['periodo_fin'] ?? ''));

  if ($periodoIniStr !== '' && $periodoFinStr !== '') {
    // Usar exactamente las fechas que viene del formulario
    $inicio = new DateTimeImmutable($periodoIniStr);
    $fin    = new DateTimeImmutable($periodoFinStr);
  } else {
    // Fallback: mes/año actuales o enviados como y / m (compatibilidad)
    $y = (int)($_POST['y'] ?? date('Y'));
    $m = (int)($_POST['m'] ?? date('n'));
    $inicio = month_start($y, $m);
    $fin    = end_by_interval($inicio, 'mensual', 1);
  }

  // 2) Cargar orden
  $st = $pdo->prepare("SELECT * FROM ordenes WHERE id = ?");
  $st->execute([$ordenId]);
  $orden = $st->fetch(PDO::FETCH_ASSOC);
  if (!$orden) back('Orden no encontrada', false);

  // 3) ¿Ya existe cargo para ese periodo?
  $st = $pdo->prepare("
    SELECT *
    FROM cargos
    WHERE orden_id = ?
      AND periodo_inicio = ?
      AND periodo_fin    = ?
    LIMIT 1
  ");
  $st->execute([
    $ordenId,
    $inicio->format('Y-m-d'),
    $fin->format('Y-m-d')
  ]);
  $cargo = $st->fetch(PDO::FETCH_ASSOC);

  // 4) Items activos de la orden
  $it = $pdo->prepare("
    SELECT id, concepto, monto
    FROM orden_items
    WHERE orden_id = ?
      AND pausado = 0
      AND (billing_type='recurrente'
           OR (billing_type='una_vez' AND end_at IS NULL))
    ORDER BY id
  ");
  $it->execute([$ordenId]);
  $items = $it->fetchAll(PDO::FETCH_ASSOC);

  // 5) Totales
  $subtotal = 0.0;
  foreach ($items as $r) {
    $subtotal += (float)$r['monto'];
  }
  $iva   = round($subtotal * 0.16, 2);
  $total = round($subtotal + $iva, 2);

  $pdo->beginTransaction();

  if ($cargo) {
    // --- Actualizar cargo existente ---

    // No bajar de "pagado" a "emitido"
    $nuevoStatus = ($cargo['estatus'] === 'pagado') ? 'pagado' : 'emitido';

    $upd = $pdo->prepare("
      UPDATE cargos
      SET subtotal = ?, iva = ?, total = ?, estatus = ?
      WHERE id = ?
    ");
    $upd->execute([$subtotal, $iva, $total, $nuevoStatus, $cargo['id']]);

    // Re-generar partidas
    $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id = ?")
        ->execute([$cargo['id']]);

    $insItem = $pdo->prepare("
      INSERT INTO cargo_items (cargo_id, orden_item_id, concepto, monto_base, iva, total)
      VALUES (?,?,?,?,?,?)
    ");
    foreach ($items as $r) {
      $mBase = (float)$r['monto'];
      $mIva  = round($mBase * 0.16, 2);
      $insItem->execute([
        $cargo['id'],
        $r['id'],
        $r['concepto'],
        $mBase,
        $mIva,
        $mBase + $mIva
      ]);
    }

    $pdo->commit();
    back('Cargo actualizado y emitido');

  } else {
    // --- Crear nuevo cargo para ese periodo ---

    $ins = $pdo->prepare("
      INSERT INTO cargos
        (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus)
      VALUES
        (?,?,?,?,?,?,?,'emitido')
    ");
    $ins->execute([
      $ordenId,
      $orden['rfc_id'] ?: null,
      $inicio->format('Y-m-d'),
      $fin->format('Y-m-d'),
      $subtotal,
      $iva,
      $total
    ]);
    $cargoId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("
      INSERT INTO cargo_items (cargo_id, orden_item_id, concepto, monto_base, iva, total)
      VALUES (?,?,?,?,?,?)
    ");
    foreach ($items as $r) {
      $mBase = (float)$r['monto'];
      $mIva  = round($mBase * 0.16, 2);
      $insItem->execute([
        $cargoId,
        $r['id'],
        $r['concepto'],
        $mBase,
        $mIva,
        $mBase + $mIva
      ]);
    }

    $pdo->commit();
    back('Cargo emitido y notificado (placeholder)');
  }

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back('Error al emitir cargo: '.$e->getMessage(), false);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/billing_rules.php';
require_once __DIR__ . '/../../App/notifications.php';

function back(string $msg, bool $ok): never {
  $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
  header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
  exit;
}

function norm(?string $s): string {
  return strtolower(trim((string)$s));
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) back('ID inválido', false);

  $periodicidadGlobal = $_POST['periodicidad'] ?? 'mensual';
  if (!in_array($periodicidadGlobal, ['unico','mensual','bimestral'], true)) {
    $periodicidadGlobal = 'mensual';
  }

  $overrides = json_decode($_POST['billing_json'] ?? '{}', true);
  if (!is_array($overrides)) $overrides = [];

  $rfcId = (int)($_POST['rfc_id'] ?? 0);
  if ($rfcId <= 0) back('Selecciona el RFC emisor antes de aprobar.', false);

  $pdo = db();
  $pdo->beginTransaction();

  // 1) Validar RFC
  $st = $pdo->prepare("SELECT id, rfc, razon_social FROM company_rfcs WHERE id = ? LIMIT 1");
  $st->execute([$rfcId]);
  $emisor = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emisor) {
    throw new RuntimeException('RFC emisor inválido.');
  }

  // 2) Cargar cotización
  $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? FOR UPDATE");
  $st->execute([$id]);
  $cot = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cot) {
    throw new RuntimeException('Cotización no encontrada');
  }

  if ($cot['estado'] !== 'pendiente') {
    throw new RuntimeException('La cotización ya no está pendiente (estado actual: '.$cot['estado'].')');
  }

  // 3) Cliente
  $st = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
  $st->execute([$cot['correo']]);
  $clienteId = (int)$st->fetchColumn();

  if ($clienteId <= 0) {
    $st = $pdo->prepare("INSERT INTO clientes (empresa, correo) VALUES (?, ?)");
    $st->execute([$cot['empresa'], $cot['correo']]);
    $clienteId = (int)$pdo->lastInsertId();
  }

  if ($clienteId <= 0) {
    throw new RuntimeException('No se pudo obtener/crear el cliente.');
  }

  // 4) Aprobar cotización
  $st = $pdo->prepare("UPDATE cotizaciones SET estado = 'aprobada', cliente_id = ? WHERE id = ?");
  $st->execute([$clienteId, $id]);

  // 5) Crear orden
  $vence = null;
  $st = $pdo->prepare("
    INSERT INTO ordenes
      (cotizacion_id, cliente_id, rfc_id, total, saldo, estado, periodicidad, vence_en, proxima_facturacion)
    VALUES
      (:cid, :clid, :rfc, :tot, :sal, 'activa', :per, :vence, NULL)
  ");
  $st->execute([
    ':cid'   => $id,
    ':clid'  => $clienteId,
    ':rfc'   => $rfcId,
    ':tot'   => (float)$cot['total'],
    ':sal'   => (float)$cot['total'],
    ':per'   => $periodicidadGlobal,
    ':vence' => $vence
  ]);

  $ordenId = (int)$pdo->lastInsertId();
  if ($ordenId <= 0) {
    throw new RuntimeException('No se pudo crear la orden.');
  }

  // 6) Items
  $it = $pdo->prepare("
    SELECT id, grupo, opcion, valor
    FROM cotizacion_items
    WHERE cotizacion_id = ?
    ORDER BY id ASC
  ");
  $it->execute([$id]);

  $ins = $pdo->prepare("
    INSERT INTO orden_items
      (orden_id, concepto, monto, billing_type, interval_unit, interval_count, next_run, end_at, prorate)
    VALUES
      (:oid, :concepto, :monto, :bt, :iu, :ic, :nr, NULL, 0)
  ");

  foreach ($it as $r) {
    $grupo    = norm($r['grupo'] ?? '');
    $monto    = (float)($r['valor'] ?? 0);
    $concepto = trim(($r['grupo'] ?? '').' - '.($r['opcion'] ?? ''));

    [$bt, $iu, $ic, $nr] = infer_billing($r, $periodicidadGlobal);

    if (isset($overrides[$grupo]) && is_array($overrides[$grupo])) {
      $ov    = $overrides[$grupo];
      $type  = ($ov['type']  ?? $bt) ?: $bt;
      $unit  = $ov['unit']   ?? $iu;
      $count = $ov['count']  ?? $ic;

      $type  = in_array($type, ['una_vez','recurrente'], true) ? $type : $bt;

      if ($type === 'recurrente') {
        if (!in_array($unit, ['mensual','anual'], true)) {
          $unit = 'mensual';
        }
        $count = (int)($count ?? 1);
        if ($unit === 'mensual' && $count < 1) {
          $count = 1;
        }
      } else {
        $unit  = null;
        $count = null;
      }

      $today = new DateTimeImmutable('today');
      if ($type === 'recurrente') {
        if ($unit === 'anual') {
          $nr = $today->modify('+1 year')->format('Y-m-d');
        } elseif ($count >= 2) {
          $nr = $today->modify('+'.((int)$count).' month')->format('Y-m-d');
        } else {
          $nr = $today->modify('+1 month')->format('Y-m-d');
        }
      } else {
        $nr = null;
      }

      $bt = $type;
      $iu = $unit;
      $ic = $count;
    }

    $ins->execute([
      ':oid'      => $ordenId,
      ':concepto' => $concepto,
      ':monto'    => $monto,
      ':bt'       => $bt,
      ':iu'       => $iu,
      ':ic'       => $ic,
      ':nr'       => $nr,
    ]);

    if ($grupo === 'web' && !empty($overrides['web']['maint'])) {
      $mConcepto = 'Mantenimiento web anual';
      $mMonto    = 2999.00;
      $mType     = 'recurrente';
      $mUnit     = 'anual';
      $mCount    = 1;
      $mNext     = (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d');

      $ins->execute([
        ':oid'      => $ordenId,
        ':concepto' => $mConcepto,
        ':monto'    => $mMonto,
        ':bt'       => $mType,
        ':iu'       => $mUnit,
        ':ic'       => $mCount,
        ':nr'       => $mNext,
      ]);
    }
  }

  // ✅ COMMIT antes de notificaciones
  $pdo->commit();

  // --- Datos mínimos de la cotización para la notificación ---
// --- Datos mínimos de la cotización para la notificación ---
$cotizacionData = [
    'id'             => $cot['id'],
    
    // CORRECCIÓN 1: Si no tienes columna 'folio', usa el ID o genera uno basado en el ID
    'folio'          => $cot['folio'] ?? ('COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT)), 
    
    // CORRECCIÓN 2: Usar 'empresa' en lugar de 'cliente' (según tu código anterior)
    'cliente_nombre' => $cot['empresa'] ?? $cot['cliente_nombre'] ?? 'Cliente Desconocido',
    
    // CORRECCIÓN 3: Usamos la variable $clienteId que ya obtuviste/creaste arriba
    // Esto es más seguro que leer $cot['cliente_id'] porque esa podría ser NULL antes de la aprobación
    'cliente_id'     => $clienteId, 
    
    'correo'         => $cot['correo'] ?? '',
];

// ID del usuario logueado (si lo tienes en $_SESSION)
$usuarioIdActual = $_SESSION['user']['id'] ?? null;

try {
    notificar_cotizacion_aprobada($pdo, $cotizacionData, $usuarioIdActual);
} catch (Throwable $e) {
    // Para depurar si algo falla:
    // error_log('Error al enviar notificación: ' . $e->getMessage());
}

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back('Error: '.$e->getMessage(), false);
}
<?php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

const IVA_TASA = 0.16;

function back(string $msg, bool $ok): never {
  $ordenId = (int)($_POST['orden_id'] ?? 0);

  if ($ordenId > 0) {
    $url = '/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=' . $ordenId;
  } else {
    $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobros';
  }

  header('Location: ' . $url . '&' . ($ok ? 'msg=' : 'err=') . rawurlencode($msg));
  exit;
}

function money_round(float $v): float { return round($v, 2); }

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
  }

  $ordenId        = (int)($_POST['orden_id'] ?? 0);
  $cargoIdPosted  = (int)($_POST['cargo_id'] ?? 0);
  $metodo         = trim((string)($_POST['metodo'] ?? 'EFECTIVO'));
  $referencia     = trim((string)($_POST['referencia'] ?? ''));
  $periodoInicioP = trim((string)($_POST['periodo_inicio'] ?? ''));
  $periodoFinP    = trim((string)($_POST['periodo_fin'] ?? ''));

  if ($ordenId <= 0) {
    back('Orden inválida', false);
  }

  $pdo = db();
  $pdo->beginTransaction();

  // 1) Orden
  $st = $pdo->prepare("SELECT * FROM ordenes WHERE id = ? FOR UPDATE");
  $st->execute([$ordenId]);
  $orden = $st->fetch(PDO::FETCH_ASSOC);

  if (!$orden) {
    back('Orden no encontrada', false);
  }
  if ($orden['estado'] !== 'activa') {
    back('La orden no está activa', false);
  }

  // 2) Resolver cargo
  $cargoId     = 0;
  $cargoTotal  = 0.0;
  $periodo_ini = null;
  $periodo_fin = null;

  if ($cargoIdPosted > 0) {
    // Ya existe cargo
    $stCg = $pdo->prepare("SELECT * FROM cargos WHERE id = ? AND orden_id = ? LIMIT 1");
    $stCg->execute([$cargoIdPosted, $ordenId]);
    $cargo = $stCg->fetch(PDO::FETCH_ASSOC);

    if (!$cargo) {
      back('Cargo no encontrado para la orden', false);
    }

    $cargoId     = (int)$cargo['id'];
    $cargoTotal  = (float)$cargo['total'];
    $periodo_ini = $cargo['periodo_inicio'];
    $periodo_fin = $cargo['periodo_fin'];

  } else {
    // No hay cargo_id, usar periodo
    if ($periodoInicioP === '' || $periodoFinP === '') {
      back('Faltan fechas de periodo para el cobro', false);
    }

    $periodo_ini = $periodoInicioP;
    $periodo_fin = $periodoFinP;

    $stCg = $pdo->prepare("
      SELECT * FROM cargos
      WHERE orden_id = ? AND periodo_inicio = ? AND periodo_fin = ?
      LIMIT 1
    ");
    $stCg->execute([$ordenId, $periodo_ini, $periodo_fin]);
    $cargo = $stCg->fetch(PDO::FETCH_ASSOC);

    if ($cargo) {
      $cargoId    = (int)$cargo['id'];
      $cargoTotal = (float)$cargo['total'];
    } else {
      // Crear cargo nuevo para ese periodo
      $it = $pdo->prepare("
        SELECT *
        FROM orden_items
        WHERE orden_id = ?
          AND pausado = 0
          AND monto > 0
          AND (
                billing_type = 'recurrente'
             OR (billing_type = 'una_vez' AND end_at IS NULL)
          )
        ORDER BY id ASC
      ");
      $it->execute([$ordenId]);
      $items = $it->fetchAll(PDO::FETCH_ASSOC);

      if (!$items) {
        back('No hay partidas para generar el cargo del periodo', false);
      }

      $subtotal = 0.0;
      $iva      = 0.0;
      $total    = 0.0;

      foreach ($items as $r) {
        $m   = (float)$r['monto'];
        $mIv = money_round($m * IVA_TASA);
        $subtotal += $m;
        $iva      += $mIv;
        $total    += $m + $mIv;
      }

      $insCargo = $pdo->prepare("
        INSERT INTO cargos
          (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus)
        VALUES (?,?,?,?,?,?,?, 'emitido')
      ");
      $insCargo->execute([
        $ordenId,
        $orden['rfc_id'],
        $periodo_ini,
        $periodo_fin,
        money_round($subtotal),
        money_round($iva),
        money_round($total),
      ]);

      $cargoId    = (int)$pdo->lastInsertId();
      $cargoTotal = $total;

      $insPart = $pdo->prepare("
        INSERT INTO cargo_items
          (cargo_id, orden_item_id, concepto, monto_base, iva, total)
        VALUES (?,?,?,?,?,?)
      ");
      foreach ($items as $r) {
        $m   = (float)$r['monto'];
        $mIv = money_round($m * IVA_TASA);
        $insPart->execute([
          $cargoId,
          $r['id'],
          $r['concepto'],
          money_round($m),
          $mIv,
          money_round($m + $mIv),
        ]);
      }
    }
  }

  if ($cargoId === 0) {
    back('No se pudo determinar el cargo a cobrar', false);
  }

  // 3) Monto
  $montoPago = (float)($_POST['monto'] ?? 0);
  if ($montoPago <= 0) {
    $montoPago = $cargoTotal;
  }
  $montoPago = money_round($montoPago);

  // 4) Registrar pago
  $insPay = $pdo->prepare("
    INSERT INTO pagos (orden_id, monto, metodo, referencia)
    VALUES (?,?,?,?)
  ");
  $insPay->execute([$ordenId, $montoPago, $metodo, $referencia]);

  // 5) Marcar cargo como pagado (por id y por periodo)
  $pdo->prepare("UPDATE cargos SET estatus = 'pagado' WHERE id = ?")
      ->execute([$cargoId]);
  $pdo->prepare("
      UPDATE cargos
      SET estatus = 'pagado'
      WHERE orden_id = ? AND periodo_inicio = ? AND periodo_fin = ?
    ")
    ->execute([$ordenId, $periodo_ini, $periodo_fin]);

  // 6) Saldo de la orden
  $pdo->prepare("UPDATE ordenes SET saldo = GREATEST(0, saldo - ?) WHERE id = ?")
      ->execute([$montoPago, $ordenId]);

  $pdo->commit();
  back('Pago registrado correctamente', true);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back('Error: '.$e->getMessage(), false);
}

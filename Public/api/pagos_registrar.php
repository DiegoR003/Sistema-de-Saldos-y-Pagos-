<?php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

const IVA_TASA = 0.16;

function back(string $msg, bool $ok): never {
  $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobros';
  header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
  exit;
}

function money_round(float $v): float { return round($v, 2); }

// Fin de periodo (mes o año “civil”)
function end_by_interval(DateTimeImmutable $start, string $unit, int $count): DateTimeImmutable {
  if ($unit === 'anual') return $start->modify('+1 year')->modify('-1 day');
  $count = max(1, (int)$count);
  return $start->modify("+{$count} month")->modify('-1 day');
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); exit('Método no permitido');
  }

  $ordenId   = (int)($_POST['orden_id'] ?? 0);
  $metodo    = trim((string)($_POST['metodo'] ?? 'efectivo'));
  $referencia= trim((string)($_POST['referencia'] ?? ''));

  if ($ordenId <= 0) back('Orden inválida', false);

  $pdo = db();
  $pdo->beginTransaction();

  // 1) Cargar orden
  $st = $pdo->prepare("SELECT * FROM ordenes WHERE id=? FOR UPDATE");
  $st->execute([$ordenId]);
  $orden = $st->fetch(PDO::FETCH_ASSOC);
  if (!$orden) back('Orden no encontrada', false);
  if ($orden['estado'] !== 'activa') back('La orden no está activa', false);

  // 2) Cargar partidas a cobrar
  $it = $pdo->prepare("
    SELECT *
    FROM orden_items
    WHERE orden_id = ?
      AND pausado = 0
      AND (
            billing_type = 'recurrente'
         OR (billing_type = 'una_vez' AND end_at IS NULL)  -- una sola vez no cobrada
      )
      AND monto > 0
    ORDER BY id ASC
  ");
  $it->execute([$ordenId]);
  $items = $it->fetchAll(PDO::FETCH_ASSOC);

  if (!$items) back('No hay partidas para cobrar en esta orden', false);

  $hoy = new DateTimeImmutable('today');

  // 3) Determinar periodo (prepaid_anchor): ancla = ancla_ciclo si existe, si no HOY
  //    Además, si next_run < ancla, normalizamos next_run = ancla (evita periodos pasados).
  foreach ($items as &$r) {
    $ancla = !empty($r['ancla_ciclo']) ? new DateTimeImmutable($r['ancla_ciclo']) : $hoy;
    if (empty($r['ancla_ciclo'])) {
      // persistir ancla_ciclo la primera vez
      $pdo->prepare("UPDATE orden_items SET ancla_ciclo=? WHERE id=?")
          ->execute([$ancla->format('Y-m-d'), $r['id']]);
      $r['ancla_ciclo'] = $ancla->format('Y-m-d');
    }
    // Para el periodo a emitir usamos siempre el ancla_ciclo
    $r['_periodo_inicio'] = $ancla;

    // Fin según intervalo
    if ($r['billing_type'] === 'recurrente') {
      $unit  = $r['interval_unit']  ?: 'mensual';
      $count = (int)($r['interval_count'] ?: 1);
      $r['_periodo_fin'] = end_by_interval($ancla, $unit, $count);
      $r['_next_run']    = $r['_periodo_fin']->modify('+1 day');
    } else {
      // una_vez: periodo de 1 día “hoy”
      $r['_periodo_fin'] = $ancla;
      $r['_next_run']    = null; // no hay siguiente
    }
  }
  unset($r);

  // Validar que todas las partidas comparten el mismo periodo (mismo inicio/fin)
  $pIni = $items[0]['_periodo_inicio'];
  $pFin = $items[0]['_periodo_fin'];
  foreach ($items as $r) {
    if ($r['_periodo_inicio']->format('Y-m-d') !== $pIni->format('Y-m-d') ||
        $r['_periodo_fin']->format('Y-m-d')    !== $pFin->format('Y-m-d')) {
      throw new RuntimeException('Las partidas tienen periodos distintos; revisa intervalos.');
    }
  }

  $periodo_inicio = $pIni->format('Y-m-d');
  $periodo_fin    = $pFin->format('Y-m-d');

  // 4) Crear (o encontrar) cargo del periodo
  $cargoId = null;
  $find = $pdo->prepare("SELECT id FROM cargos WHERE orden_id=? AND periodo_inicio=? AND periodo_fin=? LIMIT 1");
  $find->execute([$ordenId, $periodo_inicio, $periodo_fin]);
  $cargoId = (int)$find->fetchColumn();

  if ($cargoId === 0) {
    // calcular totales
    $subtotal = 0.0; $iva = 0.0; $total = 0.0;
    foreach ($items as $r) {
      $m = (float)$r['monto'];
      $mIva = money_round($m * IVA_TASA);
      $subtotal += $m; $iva += $mIva; $total += $m + $mIva;
    }

    // Insertar cargo
    $insCargo = $pdo->prepare("
      INSERT INTO cargos
        (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus)
      VALUES
        (?,?,?,?,?,?,?, 'emitido')
    ");
    $insCargo->execute([
      $orden['id'],
      $orden['rfc_id'],                  // rfc elegido en la orden
      $periodo_inicio,
      $periodo_fin,
      money_round($subtotal),
      money_round($iva),
      money_round($total),
    ]);
    $cargoId = (int)$pdo->lastInsertId();

    // Insertar partidas
    $insPart = $pdo->prepare("
      INSERT INTO cargo_items
        (cargo_id, orden_item_id, concepto, monto_base, iva, total)
      VALUES (?,?,?,?,?,?)
    ");
    foreach ($items as $r) {
      $m    = (float)$r['monto'];
      $mIva = money_round($m * IVA_TASA);
      $insPart->execute([
        $cargoId,
        $r['id'],
        $r['concepto'],
        money_round($m),
        $mIva,
        money_round($m + $mIva),
      ]);
    }
  }

  // 5) Marcar próximo ciclo en cada item
  foreach ($items as $r) {
    if ($r['billing_type'] === 'recurrente') {
      $pdo->prepare("UPDATE orden_items SET next_run=?, ultimo_periodo_inicio=?, ultimo_periodo_fin=? WHERE id=?")
          ->execute([
            $r['_next_run']->format('Y-m-d'),
            $periodo_inicio,
            $periodo_fin,
            $r['id']
          ]);
    } else {
      // una sola vez: sellamos para no cobrarla de nuevo
      $pdo->prepare("UPDATE orden_items SET end_at=CURDATE(), ultimo_periodo_inicio=?, ultimo_periodo_fin=? WHERE id=?")
          ->execute([$periodo_inicio, $periodo_fin, $r['id']]);
    }
  }

  // 6) Registrar el pago (usa el total del cargo si no mandas “monto”)
  $stC = $pdo->prepare("SELECT total FROM cargos WHERE id=?");
  $stC->execute([$cargoId]);
  $cargoTotal = (float)$stC->fetchColumn();

  $montoPago = (float)($_POST['monto'] ?? $cargoTotal);
  if ($montoPago <= 0) $montoPago = $cargoTotal;

  // Insert pago
  $insPay = $pdo->prepare("INSERT INTO pagos (orden_id, monto, metodo, referencia) VALUES (?,?,?,?)");
  $insPay->execute([$ordenId, money_round($montoPago), $metodo, $referencia]);

  // Liquidar el cargo (simple: total cubierto)
  $pdo->prepare("UPDATE cargos SET estatus='pagado' WHERE id=?")->execute([$cargoId]);

  // Actualizar saldo de la orden (opcional, si lo usas)
  $pdo->prepare("UPDATE ordenes SET saldo = GREATEST(0, saldo - ?) WHERE id=?")
      ->execute([ money_round($montoPago), $ordenId ]);

  $pdo->commit();
  back('Pago registrado y cargo emitido', true);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  back('Error: '.$e->getMessage(), false);
}

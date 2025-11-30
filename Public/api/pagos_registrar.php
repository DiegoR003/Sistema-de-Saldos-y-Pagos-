<?php
// Public/api/pagos_registrar.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';

// Si tienes date_utils.php úsalo, si no, definimos un fallback simple
if (file_exists(__DIR__ . '/../../App/date_utils.php')) {
    require_once __DIR__ . '/../../App/date_utils.php';
}
if (!function_exists('end_by_interval')) {
    // Fin de periodo mensual / anual a partir de una fecha de inicio
    function end_by_interval(DateTimeImmutable $start, string $unit, int $count): DateTimeImmutable {
        if ($unit === 'anual') {
            return $start->modify('+1 year')->modify('-1 day');
        }
        $count = max(1, (int)$count);
        return $start->modify("+{$count} month")->modify('-1 day');
    }
}
if (!function_exists('month_start')) {
    function month_start(int $y, int $m): DateTimeImmutable {
        $m = max(1, min(12, $m));
        return new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m));
    }
}

const IVA_TASA = 0.16;

function back(string $msg, bool $ok, ?int $ordenId = null): never {
    $base = '/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php';
    if ($ordenId) {
        $url = $base . '?m=cobro&orden_id=' . $ordenId;
    } else {
        $url = $base . '?m=cobros';
    }
    header('Location: ' . $url . '&ok=' . ($ok ? 1 : 0) . '&' . ($ok ? 'msg=' : 'err=') . rawurlencode($msg));
    exit;
}

function money_round(float $v): float {
    return round($v, 2);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }

    $ordenId    = (int)($_POST['orden_id'] ?? 0);
    $metodo     = trim((string)($_POST['metodo'] ?? 'EFECTIVO'));
    $referencia = trim((string)($_POST['referencia'] ?? ''));
    $montoForm  = (float)($_POST['monto'] ?? 0);

    if ($ordenId <= 0) {
        back('Orden inválida', false);
    }

    $pdo = db();

    // 1) Cargar orden
    $st = $pdo->prepare("SELECT * FROM ordenes WHERE id = ? FOR UPDATE");
    $st->execute([$ordenId]);
    $orden = $st->fetch(PDO::FETCH_ASSOC);
    if (!$orden) {
        back('Orden no encontrada', false, $ordenId);
    }
    if ($orden['estado'] !== 'activa') {
        back('La orden no está activa', false, $ordenId);
    }

    // 2) Determinar periodo a partir de los hidden del formulario
    $iniStr = trim((string)($_POST['periodo_inicio'] ?? ''));
    $finStr = trim((string)($_POST['periodo_fin'] ?? ''));

    if ($iniStr !== '' && $finStr !== '') {
        $inicio = new DateTimeImmutable($iniStr);
        $fin    = new DateTimeImmutable($finStr);
    } else {
        // Fallback: mes actual
        $y      = (int)date('Y');
        $m      = (int)date('n');
        $inicio = month_start($y, $m);
        $fin    = end_by_interval($inicio, 'mensual', 1);
    }

    $periodo_inicio = $inicio->format('Y-m-d');
    $periodo_fin    = $fin->format('Y-m-d');

    $pdo->beginTransaction();

    // 3) Buscar (o crear) cargo de ese periodo
    $stCargo = $pdo->prepare("
        SELECT *
        FROM cargos
        WHERE orden_id = ?
          AND periodo_inicio = ?
          AND periodo_fin    = ?
        LIMIT 1
    ");
    $stCargo->execute([$ordenId, $periodo_inicio, $periodo_fin]);
    $cargo = $stCargo->fetch(PDO::FETCH_ASSOC);
    $cargoId = $cargo ? (int)$cargo['id'] : 0;

    // 4) Items activos de la orden (recurrentes + una_vez sin cobrar)
    $it = $pdo->prepare("
        SELECT *
        FROM orden_items
        WHERE orden_id = ?
          AND pausado = 0
          AND (
                billing_type = 'recurrente'
             OR (billing_type = 'una_vez' AND end_at IS NULL)
          )
          AND monto > 0
        ORDER BY id ASC
    ");
    $it->execute([$ordenId]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        $pdo->rollBack();
        back('No hay partidas para cobrar en esta orden', false, $ordenId);
    }

    // 5) Calcular totales
    $subtotal = 0.0; $iva = 0.0; $total = 0.0;
    foreach ($items as $r) {
        $m    = (float)$r['monto'];
        $mIva = money_round($m * IVA_TASA);
        $subtotal += $m;
        $iva      += $mIva;
        $total    += $m + $mIva;
    }
    $subtotal = money_round($subtotal);
    $iva      = money_round($iva);
    $total    = money_round($total);

    // 6) Crear o actualizar el cargo
    if ($cargoId === 0) {
        // Crear cargo ya como pagado
        $insCargo = $pdo->prepare("
            INSERT INTO cargos
              (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus)
            VALUES
              (?,?,?,?,?,?,?,'pagado')
        ");
        $insCargo->execute([
            $ordenId,
            $orden['rfc_id'] ?: null,
            $periodo_inicio,
            $periodo_fin,
            $subtotal,
            $iva,
            $total,
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
    } else {
        // Actualizar cargo existente y dejarlo como pagado
        $updCargo = $pdo->prepare("
            UPDATE cargos
            SET subtotal = ?, iva = ?, total = ?, estatus = 'pagado'
            WHERE id = ?
        ");
        $updCargo->execute([$subtotal, $iva, $total, $cargoId]);

        // Regenerar partidas del cargo
        $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id = ?")
            ->execute([$cargoId]);

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

    // 7) Registrar el pago
    $montoPago = $montoForm > 0 ? $montoForm : $total;

    $insPago = $pdo->prepare("
        INSERT INTO pagos (orden_id, monto, metodo, referencia, cargo_id)
        VALUES (?,?,?,?,?)
    ");
    $insPago->execute([
        $ordenId,
        money_round($montoPago),
        $metodo,
        $referencia,
        $cargoId,
    ]);

    // 8) Actualizar next_run de los items recurrentes y la próxima facturación de la orden
    $nextDates = [];

    foreach ($items as $r) {
        if ($r['billing_type'] === 'recurrente') {
            $unit  = $r['interval_unit']  ?: 'mensual';
            $count = (int)($r['interval_count'] ?: 1);

            $start = new DateTimeImmutable($periodo_inicio);
            $end   = end_by_interval($start, $unit, $count);
            $next  = $end->modify('+1 day');

            $nextDates[] = $next->format('Y-m-d');

            $updItem = $pdo->prepare("
                UPDATE orden_items
                SET next_run = ?, ultimo_periodo_inicio = ?, ultimo_periodo_fin = ?
                WHERE id = ?
            ");
            $updItem->execute([
                $next->format('Y-m-d'),
                $periodo_inicio,
                $periodo_fin,
                $r['id'],
            ]);
        } else {
            // una sola vez: sellamos para no volverla a cobrar
            $updItem = $pdo->prepare("
                UPDATE orden_items
                SET end_at = CURDATE(),
                    ultimo_periodo_inicio = ?,
                    ultimo_periodo_fin    = ?
                WHERE id = ?
            ");
            $updItem->execute([
                $periodo_inicio,
                $periodo_fin,
                $r['id'],
            ]);
        }
    }

    // próxima facturación de la orden = mínimo next_run
    $proxima = null;
    if ($nextDates) {
        sort($nextDates);
        $proxima = $nextDates[0];
    }

    // Reducir saldo (por si lo usas) y guardar proxima_facturacion
    $updOrden = $pdo->prepare("
        UPDATE ordenes
        SET saldo = GREATEST(0, saldo - ?),
            proxima_facturacion = COALESCE(?, proxima_facturacion)
        WHERE id = ?
    ");
    $updOrden->execute([
        money_round($montoPago),
        $proxima,
        $ordenId,
    ]);

    $pdo->commit();
    back('Pago registrado y cargo emitido/pagado', true, $ordenId);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Si algo falla, al menos volvemos a la orden
    back('Error al registrar pago: ' . $e->getMessage(), false);
}

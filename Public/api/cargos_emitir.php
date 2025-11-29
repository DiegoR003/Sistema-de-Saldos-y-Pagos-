<?php
// Public/api/cargos_emitir.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/date_utils.php';

function back(string $msg, bool $ok = true): never {
    $q = 'ok=' . ($ok ? 1 : 0) . '&' . ($ok ? 'msg=' : 'err=') . rawurlencode($msg);
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar&' . $q);
    exit;
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }

    $pdo = db();

    /* =======================================================
       1. Datos enviados
       ======================================================= */
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    if ($ordenId <= 0) back('Orden inválida', false);

    $periodo_inicio = trim((string)($_POST['periodo_inicio'] ?? ''));
    $periodo_fin    = trim((string)($_POST['periodo_fin'] ?? ''));

    if ($periodo_inicio === '' || $periodo_fin === '') {
        back('Periodo inválido para emitir.', false);
    }

    $inicio = new DateTimeImmutable($periodo_inicio);
    $fin    = new DateTimeImmutable($periodo_fin);

    /* =======================================================
       2. Validar orden
       ======================================================= */
    $st = $pdo->prepare("SELECT * FROM ordenes WHERE id = ?");
    $st->execute([$ordenId]);
    $orden = $st->fetch(PDO::FETCH_ASSOC);

    if (!$orden) back('Orden no encontrada', false);
    if ($orden['estado'] !== 'activa')
        back('La orden no está activa. No puedes emitir cargos.', false);

    /* =======================================================
       3. Verificar si YA existe cargo del periodo
       ======================================================= */
    $st = $pdo->prepare("
        SELECT *
        FROM cargos
        WHERE orden_id = ?
          AND periodo_inicio = ?
          AND periodo_fin = ?
        LIMIT 1
    ");
    $st->execute([
        $ordenId,
        $inicio->format('Y-m-d'),
        $fin->format('Y-m-d')
    ]);
    $cargo = $st->fetch(PDO::FETCH_ASSOC);

    /* =======================================================
       4. Items Activos
       ======================================================= */
    $st = $pdo->prepare("
        SELECT id, concepto, monto
        FROM orden_items
        WHERE orden_id = ?
          AND pausado = 0
          AND (billing_type = 'recurrente'
            OR (billing_type='una_vez' AND end_at IS NULL))
        ORDER BY id
    ");
    $st->execute([$ordenId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) back("La orden no tiene servicios activos.", false);

    /* =======================================================
       5. Calcular totales
       ======================================================= */
    $subtotal = 0.0;
    foreach ($items as $r) {
        $subtotal += (float)$r['monto'];
    }

    $iva   = round($subtotal * 0.16, 2);
    $total = round($subtotal + $iva, 2);

    $pdo->beginTransaction();

    /* =======================================================
       6. Si el cargo YA EXISTE → ACTUALIZAR (pero respetar estatus)
       ======================================================= */
    if ($cargo) {

        $estatus = ($cargo['estatus'] === 'pagado') ? 'pagado' : 'emitido';

        $upd = $pdo->prepare("
            UPDATE cargos
            SET subtotal=?, iva=?, total=?, estatus=?
            WHERE id=?
        ");
        $upd->execute([$subtotal, $iva, $total, $estatus, $cargo['id']]);

        // Regenerar cargo_items
        $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id=?")
            ->execute([$cargo['id']]);

        $insPart = $pdo->prepare("
            INSERT INTO cargo_items
                (cargo_id, orden_item_id, concepto, monto_base, iva, total)
            VALUES (?,?,?,?,?,?)
        ");

        foreach ($items as $r) {
            $mBase = (float)$r['monto'];
            $mIva  = round($mBase * 0.16, 2);
            $insPart->execute([
                $cargo['id'],
                $r['id'],
                $r['concepto'],
                $mBase,
                $mIva,
                $mBase + $mIva
            ]);
        }

        $pdo->commit();
        back("Cargo actualizado (".$estatus.")", true);
    }

    /* =======================================================
       7. Si NO existe cargo → CREAR UNO
       ======================================================= */
    $ins = $pdo->prepare("
        INSERT INTO cargos
            (orden_id, rfc_id, periodo_inicio, periodo_fin,
             subtotal, iva, total, estatus, creado_en)
        VALUES (?,?,?,?,?,?,?, 'emitido', NOW())
    ");

    $ins->execute([
        $ordenId,
        $orden['rfc_id'],
        $inicio->format('Y-m-d'),
        $fin->format('Y-m-d'),
        $subtotal,
        $iva,
        $total
    ]);

    $cargoId = (int)$pdo->lastInsertId();

    /* === Insertar sus items === */
    $insPart = $pdo->prepare("
        INSERT INTO cargo_items
            (cargo_id, orden_item_id, concepto, monto_base, iva, total)
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($items as $r) {
        $mBase = (float)$r['monto'];
        $mIva  = round($mBase * 0.16, 2);

        $insPart->execute([
            $cargoId,
            $r['id'],
            $r['concepto'],
            $mBase,
            $mIva,
            $mBase + $mIva
        ]);
    }

    $pdo->commit();
    back("Cargo emitido correctamente", true);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    back("Error al emitir cargo: " . $e->getMessage(), false);
}

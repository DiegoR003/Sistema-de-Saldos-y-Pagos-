<?php
// Public/api/cotizacion_approve.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';

const DEBUG_SQL = false; // ponlo en true SOLO para depurar en local

function respond_back(string $msg, bool $ok): never {
    $back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
    header('Location: '.$back.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) respond_back('ID inválido', false);

    $periodicidad = $_POST['periodicidad'] ?? 'mensual';
    if (!in_array($periodicidad, ['unico','mensual','bimestral'], true)) {
        $periodicidad = 'mensual';
    }

    // fecha de vencimiento según periodicidad
    $venceEn = null;
    if ($periodicidad === 'mensual')   $venceEn = date('Y-m-d', strtotime('+30 days'));
    if ($periodicidad === 'bimestral') $venceEn = date('Y-m-d', strtotime('+60 days'));
    // 'unico' => null

    $pdo = db();
    $pdo->beginTransaction();

    // 1) Leer cotización
    $sql = "SELECT id, empresa, correo, subtotal, impuestos, total, estado
            FROM cotizaciones WHERE id = ?";
    $st  = $pdo->prepare($sql);
    $st->execute([$id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);

    if (!$c)        throw new RuntimeException('Cotización no encontrada');
    if ($c['estado'] !== 'pendiente')
                    throw new RuntimeException('La cotización no está en estado pendiente');

    // 2) Cliente por correo (crea si no existe)
    $sql = "SELECT id FROM clientes WHERE correo = ? LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([$c['correo']]);
    $clienteId = (int)$st->fetchColumn();

    if ($clienteId <= 0) {
        $sql = "INSERT INTO clientes (empresa, correo) VALUES (?, ?)";
        $st  = $pdo->prepare($sql);
        $st->execute([$c['empresa'], $c['correo']]);
        $clienteId = (int)$pdo->lastInsertId();
    }

    // 3) Marcar aprobada + vincular cliente
    $sql = "UPDATE cotizaciones SET estado='aprobada', cliente_id=? WHERE id=?";
    $st  = $pdo->prepare($sql);
    $st->execute([$clienteId, $id]);

    // 4) Crear orden
    $sql = "INSERT INTO ordenes
            (cotizacion_id, cliente_id, total, saldo, estado, periodicidad, vence_en)
            VALUES (:cotizacion_id, :cliente_id, :total, :saldo, 'activa', :periodicidad, :vence_en)";
    $st  = $pdo->prepare($sql);
    $st->execute([
        ':cotizacion_id' => $id,
        ':cliente_id'    => $clienteId,
        ':total'         => (float)$c['total'],
        ':saldo'         => (float)$c['total'],
        ':periodicidad'  => $periodicidad,
        ':vence_en'      => $venceEn, // puede ser null
    ]);
    $ordenId = (int)$pdo->lastInsertId();

    // 5) Copiar desglose a orden_items (usa columnas reales: orden_id, concepto, tipo, monto, periodicidad)
    $hasCotItems = $pdo->query("SHOW TABLES LIKE 'cotizacion_items'")->fetchColumn();
    $hasOrdItems = $pdo->query("SHOW TABLES LIKE 'orden_items'")->fetchColumn();

    if ($hasCotItems && $hasOrdItems) {
        // Trae items de la cotización
        $sql = "SELECT grupo, opcion, valor
                FROM cotizacion_items
                WHERE cotizacion_id = ?
                ORDER BY id ASC";
        $it = $pdo->prepare($sql);
        $it->execute([$id]);

        // Inserta con el esquema real
        $ins = $pdo->prepare("
            INSERT INTO orden_items (orden_id, concepto, tipo, monto, periodicidad)
            VALUES (:orden_id, :concepto, :tipo, :monto, :periodicidad)
        ");

        foreach ($it as $r) {
            $grupo    = (string)($r['grupo']  ?? '');
            $opcion   = (string)($r['opcion'] ?? '');
            $precio   = (float) ($r['valor']  ?? 0);

            $concepto = trim($grupo . ' - ' . $opcion); // lo que verás en el detalle
            $tipo     = $grupo;                          // guardamos el grupo como tipo

            $ins->execute([
                ':orden_id'     => $ordenId,
                ':concepto'     => $concepto,
                ':tipo'         => $tipo,
                ':monto'        => $precio,
                ':periodicidad' => $periodicidad, // o null si no quieres por ítem
            ]);
        }
    }

    $pdo->commit();
    respond_back('Cotización aprobada', true);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (DEBUG_SQL) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERROR:\n".$e->getMessage()."\n";
        if ($e instanceof PDOException && isset($e->errorInfo)) {
            var_dump($e->errorInfo);
        }
        exit;
    }
    respond_back($e->getMessage(), false);
}

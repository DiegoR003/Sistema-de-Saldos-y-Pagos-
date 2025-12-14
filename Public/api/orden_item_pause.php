<?php
declare(strict_types=1);
require_once __DIR__.'/../../App/bd.php';
require_once __DIR__.'/../../App/auth.php'; // Agregado por seguridad

if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { 
    http_response_code(405); 
    exit('Método no permitido'); 
}

$ordenId = (int)($_POST['orden_id'] ?? 0);
$itemId  = (int)($_POST['item_id']  ?? 0);

// Ajusta esta ruta base según tu estructura real
$baseUrl = '/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php';

if ($ordenId <= 0 || $itemId <= 0) { 
    http_response_code(400); 
    exit('Parámetros inválidos'); 
}

$pdo = db();
$pdo->beginTransaction();

try {
    
    // =========================================================
    //  CHECK CRÍTICO: Bloquear acción si hay cargos pendientes
    // =========================================================
    $stCheck = $pdo->prepare("
        SELECT SUM(CASE WHEN total > 0 THEN 1 ELSE 0 END) AS pending_count
        FROM cargos 
        WHERE orden_id = ? 
          AND estatus IN ('emitido', 'pendiente', 'vencido')
    ");
    $stCheck->execute([$ordenId]);
    $pendingCount = (int)$stCheck->fetchColumn();

    if ($pendingCount > 0) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $mensajeError = "No puedes cancelar/pausar: hay $pendingCount cargo(s) pendiente(s).";

        // CORRECCIÓN 1: Redirigir a la ruta pública index.php
        $redirectUrl = $baseUrl . "?m=cobro&orden_id=$ordenId&err=" . urlencode($mensajeError);
        header("Location: $redirectUrl");
        exit;
    }

    // =========================================================
    //  Continuar con la acción
    // =========================================================

    

    // 1. Leer estado actual y bloquear fila
    $st = $pdo->prepare("SELECT pausado, end_at FROM orden_items WHERE id=? AND orden_id=? FOR UPDATE");
    $st->execute([$itemId, $ordenId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) { 
        throw new Exception('Item no encontrado'); 
    }
    
    if (!empty($row['end_at'])) { 
        throw new Exception('Item cancelado, no se puede pausar/reanudar'); 
    }

    // 2. Toggle (Alternar) Pausa
    if ((int)$row['pausado'] === 1) {
        // Estaba pausado -> Lo Reanudamos
        $st = $pdo->prepare("UPDATE orden_items SET pausado=0, reanudar_en=CURDATE() WHERE id=?");
        $st->execute([$itemId]);
        $mensaje = 'Servicio reanudado correctamente';
    } else {
        // Estaba activo -> Lo Pausamos
        $st = $pdo->prepare("UPDATE orden_items SET pausado=1, pausa_desde=CURDATE() WHERE id=?");
        $st->execute([$itemId]);
        $mensaje = 'Servicio pausado correctamente';
    }

    $pdo->commit();
    
    
    $params = http_build_query([
        'm'        => 'cobro',
        'orden_id' => $ordenId,
        'ok'       => 1,
        'msg'      => $mensaje,
        'scroll'   => 'servicios'
    ]);
    
    header("Location: " . $baseUrl . "?" . $params);
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $params = http_build_query([
        'm'        => 'cobro',
        'orden_id' => $ordenId,
        'err'      => $e->getMessage(),
        'scroll'   => 'servicios'
    ]);
    
    header("Location: " . $baseUrl . "?" . $params);
    exit;
}
?>
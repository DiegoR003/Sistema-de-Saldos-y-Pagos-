<?php
declare(strict_types=1);
require_once __DIR__.'/../../App/bd.php';
require_once __DIR__.'/../../App/auth.php'; // Agregado por seguridad (sesión)

// Asegurar sesión para validaciones
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { 
    http_response_code(405); 
    exit('Método no permitido'); 
}

$ordenId = (int)($_POST['orden_id'] ?? 0);
$itemId  = (int)($_POST['item_id']  ?? 0);

// Definir la URL base correcta para tu sistema
// Ajusta esta ruta si tu carpeta raíz tiene otro nombre
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

        // CORRECCIÓN 1: Redirigir a index.php con m=cobro
        $redirectUrl = $baseUrl . "?m=cobro&orden_id=$ordenId&err=" . urlencode($mensajeError);
        header("Location: $redirectUrl");
        exit;
    }

    // =========================================================
    //  Continuar con la acción
    // =========================================================

    // Verifica que el item pertenezca a la orden y bloquea fila
    $st = $pdo->prepare("SELECT end_at FROM orden_items WHERE id=? AND orden_id=? FOR UPDATE");
    $st->execute([$itemId, $ordenId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) { 
        throw new Exception('Item no encontrado o no pertenece a la orden'); 
    }
    
    if (!empty($row['end_at'])) { 
        throw new Exception('El servicio ya estaba cancelado anteriormente'); 
    }

    // Baja definitiva: Ponemos fecha de hoy y quitamos pausa
    $st = $pdo->prepare("UPDATE orden_items SET end_at=CURDATE(), pausado=0 WHERE id=?");
    $st->execute([$itemId]);

    $pdo->commit();
    
    // CORRECCIÓN 2: Redirigir correctamente a index.php
    $params = http_build_query([
        'm'        => 'cobro',   // Importante: Indicar el módulo
        'orden_id' => $ordenId,
        'ok'       => 1,         // Para disparar SweetAlert de éxito
        'msg'      => 'Servicio cancelado correctamente'
    ]);
    
    header("Location: " . $baseUrl . "?" . $params);
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // CORRECCIÓN 3: Redirigir error también a index.php
    $params = http_build_query([
        'm'        => 'cobro',
        'orden_id' => $ordenId,
        'err'      => $e->getMessage()
    ]);
    
    header("Location: " . $baseUrl . "?" . $params);
    exit;
}
?>
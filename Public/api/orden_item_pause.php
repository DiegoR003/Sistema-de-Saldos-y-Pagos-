<?php
declare(strict_types=1);
require_once __DIR__.'/../../App/bd.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { 
    http_response_code(405); 
    exit('Método no permitido'); 
}

$ordenId = (int)($_POST['orden_id'] ?? 0);
$itemId  = (int)($_POST['item_id']  ?? 0);

if ($ordenId<=0 || $itemId<=0) { 
    http_response_code(400); 
    exit('Parámetros inválidos'); 
}

$pdo = db();
$pdo->beginTransaction();

try {
    // Verifica que el item pertenezca a la orden
    $st = $pdo->prepare("SELECT pausado, end_at FROM orden_items WHERE id=? AND orden_id=? FOR UPDATE");
    $st->execute([$itemId, $ordenId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) { 
        throw new Exception('Item no encontrado'); 
    }
    
    if (!empty($row['end_at'])) { 
        throw new Exception('Item cancelado, no se puede pausar/reanudar'); 
    }

    // Toggle pausa
    if ((int)$row['pausado'] === 1) {
        // Reanudar
        $st = $pdo->prepare("UPDATE orden_items SET pausado=0, reanudar_en=CURDATE() WHERE id=?");
        $st->execute([$itemId]);
        $mensaje = 'reanudado';
    } else {
        // Pausar
        $st = $pdo->prepare("UPDATE orden_items SET pausado=1, pausa_desde=CURDATE() WHERE id=?");
        $st->execute([$itemId]);
        $mensaje = 'pausado';
    }
// ... (dentro del TRY)
    $pdo->commit();
    
    // Redirigir con parámetro para que JS maneje el scroll
    $params = http_build_query([
        'orden_id' => $ordenId, // 'm' ya no es necesario
        'action' => $mensaje,
        'scroll' => 'servicios'
    ]);
    header('Location: ../../Modules/cobro.php?' . $params); // <-- ¡Ruta directa!
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    // Redirigir con mensaje de error
    $params = http_build_query([
        'orden_id' => $ordenId, // 'm' ya no es necesario
        'error' => $e->getMessage(),
        'scroll' => 'servicios'
    ]);
    header('Location: ../../Modules/cobro.php?' . $params); // <-- ¡Ruta directa!
    exit;
}


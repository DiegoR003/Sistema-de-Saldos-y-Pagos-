<?php
// Public/api/notificacion_leer.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Validar sesión
$u = function_exists('current_user') ? current_user() : null;
if (!$u || empty($u['id'])) { 
    echo json_encode(['ok'=>false, 'msg'=>'No sesión']); 
    exit; 
}

$pdo = db();
$userId = (int)$u['id'];

// 2. Recibir parámetros
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$marcarTodas = isset($_POST['todas']) && $_POST['todas'] === 'true';

try {
    if ($marcarTodas) {
        // OPCIÓN A: Marcar TODAS como leídas (Borra el contador rojo de golpe)
        // Afecta a las propias ($userId) y a las globales internas (usuario_id NULL)
        $sql = "UPDATE notificaciones 
                SET estado = 'leida', leida_en = NOW() 
                WHERE leida_en IS NULL 
                AND (usuario_id = ? OR (tipo = 'interna' AND usuario_id IS NULL))";
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        
    } elseif ($id > 0) {
        // OPCIÓN B: Marcar UNA sola (cuando se hace clic en la X)
        $sql = "UPDATE notificaciones 
                SET estado = 'leida', leida_en = NOW() 
                WHERE id = ? 
                AND leida_en IS NULL
                AND (usuario_id = ? OR (tipo = 'interna' AND usuario_id IS NULL))";
        $st = $pdo->prepare($sql);
        $st->execute([$id, $userId]);
    }

    echo json_encode(['ok'=>true]);

} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
}
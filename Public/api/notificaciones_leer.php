<?php
// Public/api/notificaciones_leer.php
declare(strict_types=1);

// 1) Iniciar sesión SIEMPRE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2) Comprobar usuario logueado
//   Ajusta aquí si en tu login usas otros nombres de variables
$usuarioId = 0;

// soporta ambos nombres por si en alguna parte usaste uno u otro
if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
    $usuarioId = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $usuarioId = (int)$_SESSION['user_id'];
}

if ($usuarioId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'err' => 'No autenticado']);
    exit;
}

// 3) Conexión a BD
require_once __DIR__ . '/../../App/bd.php';
$pdo = db();

// 4) Marcar notificaciones como leídas
$st = $pdo->prepare("
    UPDATE notificaciones
    SET leida_en = NOW()
    WHERE usuario_id = ?
      AND leida_en IS NULL
");
$st->execute([$usuarioId]);

// 5) Respuesta OK
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);

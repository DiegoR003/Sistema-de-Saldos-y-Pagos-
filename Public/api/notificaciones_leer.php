<?php
// Public/api/notificaciones_leer.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../App/bd.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        exit;
    }

    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
    }

    $usuarioId = (int)$_SESSION['usuario_id'];
    $rol       = $_SESSION['usuario_rol'] ?? null;

    $pdo = db();

    // Marcamos como leídas las notificaciones:
    // - dirigidas directamente a este usuario
    // - o bien las que son por rol (admin / operador / cliente) con usuario_id NULL
    $sql = "
        UPDATE notificaciones
        SET leida_en = NOW()
        WHERE leida_en IS NULL
          AND (
                usuario_id = :uid
             OR (usuario_id IS NULL AND (:rol IS NOT NULL AND rol_destino = :rol))
          )
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':uid' => $usuarioId,
        ':rol' => $rol,
    ]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

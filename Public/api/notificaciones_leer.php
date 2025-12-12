<?php
// Public/api/notificaciones_leer.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

// Recibir datos
$id = (int)($_POST['id'] ?? 0);
$todas = isset($_POST['todas']);

// Identificar IDs de sesión
$clienteId = isset($_SESSION['cliente_id']) ? (int)$_SESSION['cliente_id'] : 0;
$usuarioId = isset($_SESSION['user_id'])    ? (int)$_SESSION['user_id']    : 0;

try {
    if ($clienteId > 0) {
        // === LÓGICA PARA CLIENTES ===
        // Solo marcamos las 'externas' que pertenecen a este cliente
        if ($todas) {
            $sql = "UPDATE notificaciones SET leida_en = NOW(), estado = 'leida' 
                    WHERE cliente_id = ? AND tipo = 'externa' AND leida_en IS NULL";
            $pdo->prepare($sql)->execute([$clienteId]);
        } elseif ($id > 0) {
            $sql = "UPDATE notificaciones SET leida_en = NOW(), estado = 'leida' 
                    WHERE id = ? AND cliente_id = ?";
            $pdo->prepare($sql)->execute([$id, $clienteId]);
        }
    } elseif ($usuarioId > 0) {
        // === LÓGICA PARA ADMIN/STAFF ===
        // Marcamos las 'internas' asignadas al usuario o globales (usuario_id NULL)
        if ($todas) {
            $sql = "UPDATE notificaciones SET leida_en = NOW(), estado = 'leida' 
                    WHERE (usuario_id = ? OR usuario_id IS NULL) 
                      AND tipo = 'interna' 
                      AND leida_en IS NULL";
            $pdo->prepare($sql)->execute([$usuarioId]);
        } elseif ($id > 0) {
            // Aquí podríamos validar que la notificación sea del usuario, pero por simplicidad permitimos por ID
            $sql = "UPDATE notificaciones SET leida_en = NOW(), estado = 'leida' 
                    WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
        }
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>
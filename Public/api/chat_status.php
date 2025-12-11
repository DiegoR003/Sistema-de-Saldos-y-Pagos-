<?php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();
$u = current_user();

if ($u) {
    // Si es staff
    if (isset($u['rol']) && in_array(strtolower($u['rol']), ['admin', 'operador'])) {
        $st = $pdo->prepare("UPDATE usuarios SET last_seen = NOW() WHERE id = ?");
        $st->execute([$u['id']]);
    } else {
        // Si es cliente (buscamos por correo)
        $st = $pdo->prepare("UPDATE clientes SET last_seen = NOW() WHERE correo = ?");
        $st->execute([$u['correo']]);
    }
    echo json_encode(['ok' => true]);
}
?>
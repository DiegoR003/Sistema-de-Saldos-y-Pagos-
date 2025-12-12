<?php
// Public/api/cliente_eliminar.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

// 1. VERIFICACIÓN DE ROL ROBUSTA
// Leemos directo de sesión y convertimos a minúsculas para evitar errores
$rol = strtolower($_SESSION['user_rol'] ?? '');

if ($rol !== 'admin') {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&err=No autorizado');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&err=ID inválido');
    exit;
}

try {
    $pdo->beginTransaction();

    // Eliminar Cliente
    $st = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $st->execute([$id]);

    $pdo->commit();
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&ok=Cliente eliminado correctamente');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&err=Error al eliminar: ' . urlencode($e->getMessage()));
}
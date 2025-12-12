<?php
// Public/api/cotizacion_eliminar.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

// 1. VERIFICACIÓN DE ROL ROBUSTA
$rol = strtolower($_SESSION['user_rol'] ?? '');

if ($rol !== 'admin') {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones&err=No autorizado');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

try {
    // Eliminar
    $pdo->prepare("DELETE FROM cotizacion_items WHERE cotizacion_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?")->execute([$id]);

    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones&ok=Cotización eliminada');

} catch (Exception $e) {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones&err=Error al eliminar');
}
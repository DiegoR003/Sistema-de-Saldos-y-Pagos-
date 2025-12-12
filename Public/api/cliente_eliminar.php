<?php
// Public/api/cliente_eliminar.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

// 1. VERIFICACIÓN DE ROL
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

    // A. OBTENER EL CORREO DEL CLIENTE (Antes de borrarlo)
    // Necesitamos esto para encontrar su usuario de login asociado
    $stEmail = $pdo->prepare("SELECT correo FROM clientes WHERE id = ? LIMIT 1");
    $stEmail->execute([$id]);
    $clienteCorreo = $stEmail->fetchColumn();

    // B. BORRAR EL CLIENTE (Ficha de negocio, órdenes, cargos...)
    // Asumimos que tus tablas tienen ON DELETE CASCADE en las claves foráneas
    $stDelCliente = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stDelCliente->execute([$id]);

    // C. BORRAR EL USUARIO DE ACCESO (Login)
    // Si encontramos un correo asociado, borramos el usuario que tenga ese mismo correo
    if ($clienteCorreo) {
        // Primero borramos sus relaciones de seguridad (opcional si tienes CASCADE en DB)
        // $pdo->prepare("DELETE FROM password_resets WHERE user_id = (SELECT id FROM usuarios WHERE correo = ?)")->execute([$clienteCorreo]);
        
        // Borramos el usuario
        $stDelUser = $pdo->prepare("DELETE FROM usuarios WHERE correo = ?");
        $stDelUser->execute([$clienteCorreo]);
    }

    $pdo->commit();
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&ok=Cliente y usuario eliminados correctamente');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php?m=clientes&err=Error al eliminar: ' . urlencode($e->getMessage()));
}
?>
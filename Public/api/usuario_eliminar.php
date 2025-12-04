<?php
// Public/api/usuario_eliminar.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Validar permisos de Admin
$rol = $_SESSION['usuario_rol'] ?? $_SESSION['user']['rol'] ?? 'guest';
if ($rol !== 'admin') {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$id = (int)($_POST['id'] ?? 0);
$miId = (int)($_SESSION['usuario_id'] ?? $_SESSION['user']['id'] ?? 0);

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

// 2. Validaciones de seguridad
if ($id <= 0) {
    header("Location: $back&err=ID+inválido");
    exit;
}

if ($id === $miId) {
    header("Location: $back&err=No+puedes+eliminar+tu+propia+cuenta+mientras+la+usas");
    exit;
}

try {
    $pdo = db();
    
    // (Opcional) Aquí podrías verificar si el usuario tiene ventas/cotizaciones 
    // y decidir si prohibir el borrado o solo marcarlo como inactivo.
    // Por ahora, haremos un borrado físico.

    // Borramos relación de rol primero
    $st = $pdo->prepare("DELETE FROM usuario_rol WHERE usuario_id = ?");
    $st->execute([$id]);

    // Borramos usuario
    $st = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $st->execute([$id]);

    header("Location: $back&ok=Usuario+eliminado+correctamente");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
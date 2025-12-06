<?php
// Public/api/usuario_eliminar.php
declare(strict_types=1);

// 1. Cargar dependencias vitales
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php'; // ¡Importante para reconocer al usuario!

if (session_status() === PHP_SESSION_NONE) session_start();

// 2. VALIDACIÓN DE ROL ROBUSTA
// Buscamos el rol donde sea que esté y lo convertimos a minúsculas
$u = function_exists('current_user') ? current_user() : [];
$rolRaw = $u['rol'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['user']['rol'] ?? 'guest';
$rol = strtolower(trim((string)$rolRaw));

// Solo pasa si es 'admin'
if ($rol !== 'admin') {
    // Para depurar, mostramos qué rol detectó (puedes quitar esto luego)
    die("Acceso denegado. El sistema detectó tu rol como: '" . htmlspecialchars($rolRaw) . "'");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método no permitido');
}

$id = (int)($_POST['id'] ?? 0);
$miId = (int)($u['id'] ?? $_SESSION['usuario_id'] ?? 0);
$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

if ($id <= 0) {
    header("Location: $back&err=ID+inválido");
    exit;
}

if ($id === $miId) {
    header("Location: $back&err=No+puedes+eliminarte+a+ti+mismo");
    exit;
}

try {
    $pdo = db();
    // Borrar relación rol
    $st = $pdo->prepare("DELETE FROM usuario_rol WHERE usuario_id = ?");
    $st->execute([$id]);

    // Borrar usuario
    $st = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $st->execute([$id]);

    header("Location: $back&ok=Usuario+eliminado");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
<?php
// Public/api/usuario_actualizar.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

// Cargar auth para obtener el ID de forma segura
$authFile = __DIR__ . '/../../App/auth.php';
if (file_exists($authFile)) require_once $authFile;

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Método no permitido'); }

$id     = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

if ($id <= 0 || $nombre === '' || $correo === '') {
    header("Location: $back&err=Faltan+datos");
    exit;
}

try {
    $pdo = db();
    
    // Verificar correo duplicado
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
    $st->execute([$correo, $id]);
    if ($st->fetch()) {
        throw new Exception("El correo ya está en uso.");
    }

    // Actualizar BD
    $upd = $pdo->prepare("UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
    $upd->execute([$nombre, $correo, $id]);

    // --- ACTUALIZAR SESIÓN EN VIVO ---
    // Determinamos quién es el usuario logueado actualmente
    $miId = 0;
    if (function_exists('current_user')) {
        $u = current_user();
        $miId = (int)($u['id'] ?? 0);
    }
    // Fallback sesión
    if ($miId === 0) $miId = (int)($_SESSION['usuario_id'] ?? $_SESSION['user']['id'] ?? 0);

    // Si me estoy editando a mí mismo, actualizo la sesión
    if ($miId === $id) {
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['nombre'] = $nombre;
            $_SESSION['user']['correo'] = $correo;
        }
        // Actualizar variables sueltas si las usas en auth.php
        if (isset($_SESSION['usuario_nombre'])) $_SESSION['usuario_nombre'] = $nombre;
        if (isset($_SESSION['nombre'])) $_SESSION['nombre'] = $nombre;
    }

    header("Location: $back&ok=Datos+actualizados");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
<?php
// Public/api/usuario_editar_admin.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Validar Admin
$rol = $_SESSION['usuario_rol'] ?? $_SESSION['user']['rol'] ?? 'guest';
if ($rol !== 'admin') { die("Acceso denegado"); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$id       = (int)($_POST['id'] ?? 0);
$nombre   = trim($_POST['nombre'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? ''; // Opcional
$rolId    = (int)($_POST['rol_id'] ?? 0);
$activo   = isset($_POST['activo']) ? 1 : 0;

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

if ($id <= 0 || $nombre === '' || $correo === '' || $rolId <= 0) {
    header("Location: $back&err=Faltan+datos");
    exit;
}

try {
    $pdo = db();

    // 1. Validar correo duplicado
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
    $st->execute([$correo, $id]);
    if ($st->fetch()) {
        throw new Exception("El correo ya está en uso por otro usuario.");
    }

    // 2. Actualizar datos básicos
    // Si la contraseña no está vacía, la actualizamos también
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nombre=?, correo=?, activo=?, pass_hash=? WHERE id=?";
        $params = [$nombre, $correo, $activo, $hash, $id];
    } else {
        $sql = "UPDATE usuarios SET nombre=?, correo=?, activo=? WHERE id=?";
        $params = [$nombre, $correo, $activo, $id];
    }
    
    $st = $pdo->prepare($sql);
    $st->execute($params);

    // 3. Actualizar Rol
    // Primero borramos el anterior (o podrías hacer UPDATE si existe lógica única)
    $pdo->prepare("DELETE FROM usuario_rol WHERE usuario_id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)")->execute([$id, $rolId]);

    header("Location: $back&ok=Usuario+actualizado");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
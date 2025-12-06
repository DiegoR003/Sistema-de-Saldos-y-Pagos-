<?php
// Public/api/usuario_editar_admin.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php'; // Necesario para validar permisos

if (session_status() === PHP_SESSION_NONE) session_start();

// VALIDACIÓN DE ROL ROBUSTA
$u = function_exists('current_user') ? current_user() : [];
$rolRaw = $u['rol'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['user']['rol'] ?? 'guest';
$rol = strtolower(trim((string)$rolRaw));

if ($rol !== 'admin') {
    die("Acceso denegado. Rol detectado: " . htmlspecialchars($rolRaw));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$id       = (int)($_POST['id'] ?? 0);
$nombre   = trim($_POST['nombre'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$rolId    = (int)($_POST['rol_id'] ?? 0);
$activo   = isset($_POST['activo']) ? 1 : 0;

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

if ($id <= 0 || $nombre === '' || $correo === '' || $rolId <= 0) {
    header("Location: $back&err=Faltan+datos");
    exit;
}

try {
    $pdo = db();

    // Validar correo duplicado
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
    $st->execute([$correo, $id]);
    if ($st->fetch()) {
        throw new Exception("El correo ya está en uso.");
    }

    // Actualizar datos
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

    // Actualizar Rol
    $pdo->prepare("DELETE FROM usuario_rol WHERE usuario_id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)")->execute([$id, $rolId]);

    header("Location: $back&ok=Usuario+actualizado");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
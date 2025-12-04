<?php
// Public/api/usuario_actualizar.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$id     = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios'; 

if ($id <= 0 || $nombre === '' || $correo === '') {
    header("Location: $back&err=Datos+invalidos");
    exit;
}

try {
    $pdo = db();
    
    // Validar que el correo no lo use OTRA persona (id diferente al mío)
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
    $st->execute([$correo, $id]);
    if ($st->fetch()) {
        throw new Exception("El correo ya está en uso por otro usuario.");
    }

    // Actualizar
    $upd = $pdo->prepare("UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
    $upd->execute([$nombre, $correo, $id]);

    // Actualizar la sesión en vivo si el usuario se editó a sí mismo
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Verificamos usando el ID de sesión que corregimos antes
    $sesId = $_SESSION['usuario_id'] ?? $_SESSION['user']['id'] ?? 0;
    
    if ($sesId == $id) {
        // Actualizamos para que el cambio se vea reflejado en el header inmediatamente
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['nombre'] = $nombre;
            $_SESSION['user']['correo'] = $correo;
        }
        // Si usas variables sueltas también
        if (isset($_SESSION['usuario_nombre'])) $_SESSION['usuario_nombre'] = $nombre;
    }

    header("Location: $back&ok=Datos+actualizados");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
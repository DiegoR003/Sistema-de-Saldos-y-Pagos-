<?php
// Public/api/usuario_cambiar_password.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }

$id           = (int)($_POST['id'] ?? 0);
$passActual   = $_POST['password_actual'] ?? '';
$passNueva    = $_POST['password_nueva'] ?? '';
$passConfirm  = $_POST['password_confirmar'] ?? '';

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

if ($id <= 0 || !$passActual || !$passNueva) {
    header("Location: $back&err=Faltan+datos");
    exit;
}

if ($passNueva !== $passConfirm) {
    header("Location: $back&err=Las+nuevas+contraseñas+no+coinciden");
    exit;
}

try {
    $pdo = db();

    // 1. Obtener hash actual de la BD
    $st = $pdo->prepare("SELECT pass_hash FROM usuarios WHERE id = ?");
    $st->execute([$id]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    // 2. Verificar contraseña actual
    if (!$user || !password_verify($passActual, $user['pass_hash'])) {
        throw new Exception("La contraseña actual es incorrecta.");
    }

    // 3. Generar nuevo hash y actualizar
    $newHash = password_hash($passNueva, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE usuarios SET pass_hash = ? WHERE id = ?");
    $upd->execute([$newHash, $id]);

    header("Location: $back&ok=Contraseña+actualizada+correctamente");

} catch (Exception $e) {
    header("Location: $back&err=" . rawurlencode($e->getMessage()));
}
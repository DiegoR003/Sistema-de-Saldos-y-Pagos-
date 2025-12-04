<?php
// Public/api/usuario_crear.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

// Iniciar sesión para validar permisos si es necesario
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Método no permitido');
}

// Recibir datos
$nombre   = trim($_POST['nombre'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$rolId    = (int)($_POST['rol_id'] ?? 0);
$activo   = isset($_POST['activo']) ? 1 : 0;

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

// Validaciones básicas
if ($nombre === '' || $correo === '' || $password === '' || $rolId <= 0) {
    header("Location: $back&err=Faltan+datos+obligatorios");
    exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Verificar si el correo ya existe
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $st->execute([$correo]);
    if ($st->fetch()) {
        throw new Exception("El correo $correo ya está registrado.");
    }

    // 2. Encriptar contraseña
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 3. Insertar Usuario
    $ins = $pdo->prepare("INSERT INTO usuarios (nombre, correo, pass_hash, activo, creado_en) VALUES (?, ?, ?, ?, NOW())");
    $ins->execute([$nombre, $correo, $hash, $activo]);
    $userId = (int)$pdo->lastInsertId();

    // 4. Asignar Rol en la tabla intermedia 'usuario_rol'
    $insRol = $pdo->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, ?)");
    $insRol->execute([$userId, $rolId]);

    $pdo->commit();
    header("Location: $back&ok=Usuario+creado+correctamente");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = rawurlencode($e->getMessage());
    header("Location: $back&err=$msg");
}
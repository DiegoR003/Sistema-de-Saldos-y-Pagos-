<?php
// Public/api/auth_reset_final.php
require_once __DIR__ . '/../../App/bd.php';

header('Content-Type: application/json');
$pdo = db();

$email = trim($_POST['email'] ?? '');
$code  = trim($_POST['code'] ?? '');
$pass  = trim($_POST['password'] ?? '');

if (!$email || strlen($code) !== 6 || strlen($pass) < 6) {
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
    exit;
}

// 1. Obtener ID del usuario
$stU = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
$stU->execute([$email]);
$userId = $stU->fetchColumn();

if (!$userId) {
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
    exit;
}

// 2. Buscar token válido en password_resets
// Debe ser del usuario, no usado, y no expirado
$sqlToken = "
    SELECT id, token_hash 
    FROM password_resets 
    WHERE usuario_id = ? 
      AND used_at IS NULL 
      AND expires_at > NOW() 
    ORDER BY creado_en DESC 
    LIMIT 1
";
$stT = $pdo->prepare($sqlToken);
$stT->execute([$userId]);
$tokenData = $stT->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    echo json_encode(['ok' => false, 'msg' => 'El código ha expirado o no existe. Solicita uno nuevo.']);
    exit;
}

// 3. Verificar el Hash del Código
if (!password_verify($code, $tokenData['token_hash'])) {
    echo json_encode(['ok' => false, 'msg' => 'Código incorrecto']);
    exit;
}

try {
    $pdo->beginTransaction();

    // A. Actualizar contraseña del usuario
    $newHash = password_hash($pass, PASSWORD_DEFAULT);
    $updUser = $pdo->prepare("UPDATE usuarios SET pass_hash = ? WHERE id = ?");
    $updUser->execute([$newHash, $userId]);

    // B. Marcar token como usado
    $updToken = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $updToken->execute([$tokenData['id']]);

    $pdo->commit();
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>
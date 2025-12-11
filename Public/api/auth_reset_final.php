<?php
// Public/api/auth_reset_final.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';

header('Content-Type: application/json; charset=utf-8');

$pdo      = db();
$email    = trim($_POST['email']    ?? '');
$code     = trim($_POST['code']     ?? '');
$password = trim($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Correo inválido']);
    exit;
}
if (strlen($code) !== 6) {
    echo json_encode(['ok' => false, 'msg' => 'Código inválido']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

try {
    // 1) Buscar usuario
    $st = $pdo->prepare("
        SELECT id, correo, activo
        FROM usuarios
        WHERE correo = ?
        LIMIT 1
    ");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || !(int)$user['activo']) {
        echo json_encode(['ok' => false, 'msg' => 'Usuario no válido']);
        exit;
    }

    $usuarioId = (int)$user['id'];

    // 2) Buscar el último reset activo de ese usuario
    $stR = $pdo->prepare("
        SELECT id, token_hash, expires_at
        FROM password_resets
        WHERE usuario_id = ?
          AND used_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stR->execute([$usuarioId]);
    $reset = $stR->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(['ok' => false, 'msg' => 'El código ha expirado o no existe. Solicita uno nuevo.']);
        exit;
    }

    // 3) Revisar expiración
    $now     = new DateTimeImmutable();
    $expires = new DateTimeImmutable($reset['expires_at']);

    if ($expires < $now) {
        echo json_encode(['ok' => false, 'msg' => 'El código ha expirado. Solicita uno nuevo.']);
        exit;
    }

    // 4) Verificar código con password_verify
    if (!password_verify($code, $reset['token_hash'])) {
        echo json_encode(['ok' => false, 'msg' => 'El código es incorrecto.']);
        exit;
    }

    // 5) Actualizar contraseña y marcar reset como usado
    $pdo->beginTransaction();

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $updUser = $pdo->prepare("UPDATE usuarios SET pass_hash = ? WHERE id = ?");
    $updUser->execute([$newHash, $usuarioId]);

    $updReset = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $updReset->execute([(int)$reset['id']]);

    $pdo->commit();

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('auth_reset_final error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $e->getMessage()]);
}

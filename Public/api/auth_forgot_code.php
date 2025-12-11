<?php
// Public/api/auth_forgot_code.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/mailer.php';

header('Content-Type: application/json; charset=utf-8');

$pdo   = db();
$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Correo inválido']);
    exit;
}

try {
    // 1) Buscar usuario
    $st = $pdo->prepare("SELECT id, nombre, correo, activo FROM usuarios WHERE correo = ? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || !(int)$user['activo']) {
        echo json_encode(['ok' => false, 'msg' => 'No se encontró una cuenta activa con este correo.']);
        exit;
    }

    $usuarioId = (int)$user['id'];

    // 2) LIMPIAR CÓDIGOS ANTERIORES (Evita acumulación y doble envío lógico)
    $del = $pdo->prepare("DELETE FROM password_resets WHERE usuario_id = ?");
    $del->execute([$usuarioId]);

    // 3) Generar Nuevo Código
    $codigo    = random_int(100000, 999999);
    $selector  = bin2hex(random_bytes(16)); // Selector ÚNICO aleatorio
    $tokenHash = password_hash((string)$codigo, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 4) Insertar
    $ins = $pdo->prepare("
        INSERT INTO password_resets (usuario_id, selector, token_hash, expires_at, ip_solicita, creado_en)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([
        $usuarioId,
        $selector, 
        $tokenHash,
        $expiresAt,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    // 5) Enviar Correo
    $html = '
    <div style="font-family:Arial,sans-serif; padding:20px; border:1px solid #eee; border-radius:5px;">
        <h2 style="color:#f9af24;">Recuperar Contraseña</h2>
        <p>Hola <strong>'.htmlspecialchars($user['nombre']).'</strong>,</p>
        <p>Tu código de verificación es:</p>
        <div style="background:#f4f4f4; padding:15px; font-size:24px; font-weight:bold; letter-spacing:5px; text-align:center;">
            '.$codigo.'
        </div>
        <p><small>Este código es válido por 15 minutos.</small></p>
    </div>';

    if (function_exists('enviar_correo_sistema')) {
        enviar_correo_sistema($email, $user['nombre'], "Codigo de Recuperacion", $html);
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
?>
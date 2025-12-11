<?php
// Public/api/auth_forgot_code.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/mailer.php';

header('Content-Type: application/json');
$pdo = db();

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Correo inválido']);
    exit;
}

// 1. Verificar si existe el usuario
$st = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1 LIMIT 1");
$st->execute([$email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Por seguridad, a veces se dice "Si existe, se envió", pero para tu uso interno:
    echo json_encode(['ok' => false, 'msg' => 'El correo no está registrado o inactivo']);
    exit;
}

// 2. Generar Código de 6 dígitos
$codigo = rand(100000, 999999);
$hashCodigo = password_hash((string)$codigo, PASSWORD_DEFAULT);
$expira = date('Y-m-d H:i:s', strtotime('+15 minutes')); // 15 min validez

// 3. Guardar en tabla password_resets
// Usamos 'codigo' como selector genérico para identificar el tipo de reset
try {
    $ins = $pdo->prepare("INSERT INTO password_resets (usuario_id, selector, token_hash, expires_at, ip_solicita, creado_en) VALUES (?, 'codigo', ?, ?, ?, NOW())");
    $ins->execute([
        $user['id'],
        $hashCodigo, 
        $expira, 
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    // 4. Enviar Correo
    $html = "
    <div style='font-family:Arial; padding:20px; border:1px solid #eee; border-radius:10px;'>
        <h2 style='color:#fdd835'>Recuperación de Contraseña</h2>
        <p>Hola <strong>{$user['nombre']}</strong>,</p>
        <p>Usa el siguiente código para restablecer tu contraseña:</p>
        <div style='background:#f9f9f9; font-size:24px; letter-spacing:5px; font-weight:bold; text-align:center; padding:15px; margin:20px 0;'>
            $codigo
        </div>
        <p><small>Este código expira en 15 minutos.</small></p>
    </div>";

    if (function_exists('enviar_correo_sistema')) {
        enviar_correo_sistema($email, $user['nombre'], "Código de Recuperación: $codigo", $html);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Error al enviar correo (mailer)']);
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
}
?>
<?php
// Public/test_mail.php
declare(strict_types=1);

// 1. Mostrar errores en pantalla para depurar
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. Cargar el archivo de correo
// Ajusta la ruta si guardaste test_mail.php en otro lado
$rutaMailer = __DIR__ . '/../App/mailer.php';

if (!file_exists($rutaMailer)) {
    die("❌ Error Crítico: No se encuentra el archivo 'App/mailer.php'. Revisa la ruta.");
}

require_once $rutaMailer;

echo "<h2>Prueba de Envío de Correo (PHPMailer)</h2>";

// ==========================================
// 3. CONFIGURA AQUÍ EL CORREO DE PRUEBA
// ==========================================
$miCorreo = 'diego.fosis.bnava@gmail.com'; // <--- ¡PON TU CORREO AQUÍ!
$miNombre = 'Admin de Prueba';

// 4. Preparar el mensaje
$asunto = '🧪 Test de Conexión SMTP - Banana Group';
$cuerpoHTML = '
<div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;">
    <h2 style="color: #fdd835;">¡Funciona! 🍌</h2>
    <p>Si estás leyendo este mensaje, significa que <strong>PHPMailer está bien configurado</strong> y tu servidor SMTP (Gmail/Outlook/Hostinger) ha aceptado la conexión.</p>
    <p><strong>Hora del envío:</strong> ' . date('Y-m-d H:i:s') . '</p>
    <hr>
    <small style="color: #888;">Este es un mensaje automático de prueba.</small>
</div>
';

// 5. Intentar enviar
echo "Intentando enviar correo a: <strong>$miCorreo</strong>...<br><br>";

// Llamamos a la función que creamos en App/mailer.php
$enviado = enviar_correo_sistema($miCorreo, $miNombre, $asunto, $cuerpoHTML);

if ($enviado) {
    echo '<div style="padding:15px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px;">
            ✅ <strong>¡ÉXITO!</strong> El correo se envió correctamente. <br>
            Revisa tu bandeja de entrada (y la carpeta de SPAM por si acaso).
          </div>';
} else {
    echo '<div style="padding:15px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;">
            ❌ <strong>ERROR:</strong> No se pudo enviar el correo.
          </div>';
    
    echo '<h4>Posibles causas:</h4>
          <ul>
            <li>Contraseña de aplicación incorrecta (Si usas Gmail).</li>
            <li>Puerto bloqueado (Intenta cambiar 465 por 587 en mailer.php).</li>
            <li>El antivirus está bloqueando la salida de correos.</li>
          </ul>';
    
    echo '<p><em>Tip: Ve al archivo <strong>App/mailer.php</strong> y descomenta la línea <code>$mail->SMTPDebug</code> para ver el error técnico en pantalla.</em></p>';
}
?>
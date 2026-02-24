<?php
// App/mailer.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ajusta la ruta al autoload si tu estructura es diferente
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/bootstrap.php';

/* ==================================================
   CONFIGURACIÓN SMTP 
   ================================================== */
   
$mail->Host = env('MAIL_HOST', 'smtp.gmail.com');
$mail->Username = env('MAIL_USER');
$mail->Password = env('MAIL_PASS');
$mail->Port = (int) env('MAIL_PORT', 465);

$secure = env('MAIL_SECURE', 'ssl'); // ssl | tls
$mail->SMTPSecure = ($secure === 'tls')
    ? PHPMailer::ENCRYPTION_STARTTLS
    : PHPMailer::ENCRYPTION_SMTPS;

$mail->setFrom(env('MAIL_USER'), env('MAIL_FROM_NAME', 'Banana Group Notificaciones'));

/**
 * Función genérica para enviar correos.
 * * @param string $destinatario Email del cliente
 * @param string $nombreDest   Nombre del cliente
 * @param string $asunto       Asunto del correo
 * @param string $cuerpoHTML   Contenido en HTML
 * @param array  $adjuntos     Array de rutas de archivos (opcional). Ej: ['/ruta/doc.pdf', '/ruta/foto.jpg']
 * * @return bool True si se envió, False si falló.
 */
function enviar_correo_sistema(
    string $destinatario, 
    string $nombreDest, 
    string $asunto, 
    string $cuerpoHTML, 
    array $adjuntos = []
): bool {
    $mail = new PHPMailer(true);

    try {
        // 1. Configuración del Servidor
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomentar para ver errores en pantalla
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = MAIL_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // 2. Remitente y Destinatario
        $mail->setFrom(MAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($destinatario, $nombreDest);

        // 3. Archivos Adjuntos (PDFs, Imágenes, etc.)
        foreach ($adjuntos as $rutaArchivo) {
            if (file_exists($rutaArchivo)) {
                $mail->addAttachment($rutaArchivo);
            }
        }

        // 4. Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;
        $mail->AltBody = strip_tags($cuerpoHTML); // Versión texto plano por si acaso

        $mail->send();
        return true;

    } catch (Exception $e) {
        // En producción, registra el error en un log en lugar de mostrarlo
        error_log("Error Mailer: {$mail->ErrorInfo}");
        return false;
    }
}
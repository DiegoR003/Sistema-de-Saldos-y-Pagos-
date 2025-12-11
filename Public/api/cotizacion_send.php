<?php
// Public/api/cotizacion_send.php
declare(strict_types=1);

// Configuración de errores para respuesta JSON limpia
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
// Aseguramos cargar el mailer para la función enviar_correo_sistema
require_once __DIR__ . '/../../App/mailer.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    $pdo = db();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception("ID de cotización inválido.");
    }

    // 1. OBTENER DATOS
    $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $st->execute([$id]);
    $cot = $st->fetch(PDO::FETCH_ASSOC);

    if (!$cot) throw new Exception("Cotización no encontrada.");
    if (empty($cot['correo'])) throw new Exception("El cliente no tiene correo registrado.");

    $folio = 'COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT);
    $total = number_format((float)$cot['total'], 2);

    // 2. OBTENER ITEMS
    $stI = $pdo->prepare("SELECT grupo, opcion, valor FROM cotizacion_items WHERE cotizacion_id = ?");
    $stI->execute([$id]);
    $items = $stI->fetchAll(PDO::FETCH_ASSOC);

    // 3. GENERAR HTML DEL PDF (Diseño Banana)
    // Definimos los textos descriptivos para que se vea profesional
    function getDesc($g, $o) {
        $g = strtolower($g);
        $map = [
            'cuenta' => 'Asignación de ejecutivo y gastos administrativos.',
            'publicaciones' => 'Posts en feed y stories (FB e IG).',
            'meta' => 'Gestión de campañas (no incluye presupuesto).',
            'reposteo' => 'Respuesta de mensajes y comentarios.',
            'stories' => 'Diseño de historias y cambios de perfil.',
            'fotos' => 'Sesión fotográfica profesional.',
            'video' => 'Producción de video reels.',
            'ads' => 'Gestión de campañas en Google Ads.',
            'mkt' => 'Email Marketing y boletines.',
            'web' => 'Hospedaje, dominio y mantenimiento.',
        ];
        foreach($map as $k=>$v) if(strpos($g,$k)!==false) return $v;
        return $o;
    }

    // Logo en Base64
    $rutaLogo = __DIR__ . '/../../Public/assets/logo.png';
    $logoHTML = "";
    if (file_exists($rutaLogo)) {
        $b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($rutaLogo));
        $logoHTML = "<img src='$b64' style='height:50px;'>";
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: sans-serif; color: #333; }
            .header { padding: 20px; border-bottom: 4px solid #f9af24; background: #f9f9f9; }
            .client-name { font-size: 20px; font-weight: bold; text-transform: uppercase; }
            .price-tag { background: #1a1a1a; color: #f9af24; padding: 10px; border-radius: 8px; float: right; font-weight: bold; font-size: 18px; }
            .svc-tbl { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .svc-tbl td { padding: 10px; border-bottom: 1px solid #eee; }
            .svc-row:nth-child(odd) { background-color: #fff8e1; }
            .footer { position: fixed; bottom: 0; width: 100%; background: #1a1a1a; color: #fff; padding: 20px; text-align: center; font-size: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <table width="100%">
                <tr>
                    <td>
                        <span style="background:#f9af24; color:#fff; padding:3px 8px; border-radius:10px; font-size:10px; font-weight:bold;">COTIZACIÓN</span>
                        <div class="client-name"><?= htmlspecialchars($cot['empresa']) ?></div>
                        <small>Folio: <?= $folio ?></small>
                    </td>
                    <td align="right">
                        <?= $logoHTML ?><br><br>
                        <div class="price-tag">$<?= $total ?> MXN</div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding: 20px;">
            <h3>Servicios Incluidos</h3>
            <table class="svc-tbl">
                <?php foreach($items as $i): ?>
                <tr class="svc-row">
                    <td>
                        <strong><?= strtoupper($i['grupo']) ?></strong><br>
                        <small><?= htmlspecialchars(getDesc($i['grupo'], $i['opcion'])) ?></small>
                    </td>
                    <td align="right">$<?= number_format((float)$i['valor'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <div style="margin-top: 30px; font-size: 10px; color: #666;">
                <strong>Términos:</strong><br>
                Precios + IVA. Pago mensual. Vigencia de 15 días.
            </div>
        </div>

        <div class="footer">
            www.bananagroup.mx | (624) 125 0058 | Los Cabos, B.C.S.
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // 4. CREAR ARCHIVO PDF TEMPORAL
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $pdfOutput = $dompdf->output();
    $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "Cotizacion_{$folio}.pdf";
    file_put_contents($tempFile, $pdfOutput);

    // 5. ENVIAR CORREO (Usando tu mailer.php)
    $asunto = "Cotización $folio - Banana Group";
    $cuerpo = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
        <h2 style='color: #fdd835;'>¡Hola, " . htmlspecialchars($cot['empresa']) . "!</h2>
        <p>Adjunto encontrarás la cotización detallada de los servicios solicitados.</p>
        <p><strong>Total:</strong> $$total MXN + IVA Incluido</p>
        <hr>
        <p>Si tienes dudas o deseas proceder, responde a este correo.</p>
        <p><small>Equipo Banana Group</small></p>
    </div>";

    // Llamamos a tu función pasando la ruta del archivo temporal
    // Asegúrarse de que enviar_correo_sistema en mailer.php acepte el array de adjuntos como 5to parámetro
    $enviado = enviar_correo_sistema($cot['correo'], $cot['empresa'], $asunto, $cuerpo, [$tempFile]);

    // 6. LIMPIEZA
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }

    if ($enviado) {
        echo json_encode(['ok' => true, 'msg' => 'Cotización enviada correctamente.']);
    } else {
        throw new Exception("El sistema de correo falló.");
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>
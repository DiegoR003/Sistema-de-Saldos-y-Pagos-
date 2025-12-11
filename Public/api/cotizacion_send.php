<?php
// Public/api/cotizacion_send.php
declare(strict_types=1);

// Evitar que errores de PHP rompan el JSON
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

// Si usas un helper de mailer global, inclúyelo. Si no, usaremos PHPMailer directo abajo.
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false, 'msg'=>'ID inválido']); exit; }

$pdo = db();

// 1. DATOS (Usando 'creado_en')
$sqlCot = "SELECT id, empresa, correo, total, creado_en FROM cotizaciones WHERE id = ?";
$st = $pdo->prepare($sqlCot);
$st->execute([$id]);
$cot = $st->fetch(PDO::FETCH_ASSOC);

if (!$cot) { echo json_encode(['ok'=>false, 'msg'=>'No existe la cotización']); exit; }
if (empty($cot['correo'])) { echo json_encode(['ok'=>false, 'msg'=>'El cliente no tiene correo registrado']); exit; }

// Variables plantilla
$folioVisual = 'COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT);
$clienteName = mb_strtoupper($cot['empresa'], 'UTF-8');
$total = number_format((float)$cot['total'], 2);

// 2. ITEMS
$sqlItems = "SELECT grupo, opcion FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY id ASC";
$stI = $pdo->prepare($sqlItems);
$stI->execute([$id]);
$itemsDb = $stI->fetchAll(PDO::FETCH_ASSOC);

function getInfoServicio($grupo, $opcion) {
    $g = mb_strtolower(trim($grupo), 'UTF-8');
    $map = [
        'cuenta' => ['t' => 'Cuota Fija', 'd' => 'Asignación de ejecutivo de cuenta y gastos administrativos.'],
        'publicaciones' => ['t' => 'Posts Semanales', 'd' => 'Se publica tanto en tu feed (muro) como en historias. Incluye Facebook e Instagram.'],
        'meta' => ['t' => 'Meta ADS', 'd' => 'No incluye el presupuesto asignado. Pago de campañas de vacantes es ilimitado.'],
        'reposteo' => ['t' => 'Community Manager', 'd' => 'Responder mensajes y comentarios (9am-5pm L-V, 9am-2pm S).'],
        'stories' => ['t' => 'Diseño Gráfico', 'd' => 'Diseño de post y stories. Incluye cambios de perfil y portada.'],
        'fotos' => ['t' => 'Fotografía', 'd' => '1 sesión máx 2 horas, 30 fotos, 1 locación. Incluye drone.'],
        'video' => ['t' => 'Video Reels', 'd' => 'Sesión máx 2 horas, 4 reels (vertical/horizontal). Incluye drone.'],
        'ads' => ['t' => 'Google ADS', 'd' => 'Hasta 2 campañas simultáneamente.'],
        'mkt' => ['t' => 'Email Marketing', 'd' => 'Diseño y envío masivo. No incluye base de datos.'],
        'email' => ['t' => 'Email Marketing', 'd' => 'Diseño y envío masivo. No incluye base de datos.'],
        'web' => ['t' => 'Sitio Web', 'd' => 'Hospedaje, dominio .com, SSL, correos ilimitados. Soporte en horario laboral.']
    ];
    foreach ($map as $k => $v) { if (strpos($g, $k) !== false) return $v; }
    return ['t' => mb_strtoupper($grupo), 'd' => $opcion];
}

$items = [];
foreach ($itemsDb as $row) $items[] = getInfoServicio($row['grupo'], $row['opcion']);

// 3. IMÁGENES BASE64 (Dorado Banana #f9af24)
$rutaLogo = __DIR__ . '/../../Public/assets/logo.png';
$logoHTML = "";
if (file_exists($rutaLogo)) {
    $d = file_get_contents($rutaLogo);
    $b64 = 'data:image/' . pathinfo($rutaLogo, PATHINFO_EXTENSION) . ';base64,' . base64_encode($d);
    $logoHTML = "<img src='$b64' style='height:50px;'>";
}

$ic_mail = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAUElEQVRIie3SMQqDQBQF0Rk02WgVPYJ26z10C/Zewc5LCMbOQjthk84mzMLHB4+B4fs7T5KJ289+3ZhtMnBnXExq4M64mNTAnXExqYF74/L+Ag9WpA97R61H6QAAAABJRU5ErkJggg==";
$ic_phone = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAVklEQVRIie3SsQ2AMBAEwZ8jBSckuu9K6IBOqCCBiEhJg8350oz2bB92Wym5zayk110FqIFVgBpYBaiBVYAaWAWogVWAGlgFqIFVgBpYBaiBVcAb+P9+sQ+jLA97/k7w+gAAAABJRU5ErkJggg==";
$ic_web = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAWklEQVRIie3SwQmAQBBE0d9C0I4t2IIt2IIt2IIt2IYVGIQgIsQ0sA/zYObxYUiSi9nN7KQ3vQWoATWwGvAG1IB3oAbUgBqwGlADasBqQA2oAasBNaAGrAb8g1/sA/V8D3t1l3+7AAAAAElFTkSuQmCC";
$ic_fb = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAALUlEQVRIiWNgGAWjYBSMglEwCkbBSAc8BED+w4Bf8D8D/vCAwz8M+AX/M+APCwA8Gg97z53eOAAAAABJRU5ErkJggg=="; 
$ic_ig = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAQElEQVRIie3SwQ0AIAQDwU9n5WDL0A1d0AnB80MC52Y3s5JedxWgBlYBamAVoAZWAWpgFaAGVgFqYBWgBlYBb+AD9cIPe3s2Y6gAAAAASUVORK5CYII=";
$ic_in = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAANUlEQVRIiWNgGAWjYBSMglEwCkbBSAc8BED+w4Bf8D8D/vCAwz8M+AX/M+APDzj8w0AGBgA82Qo/2495+wAAAABJRU5ErkJggg==";

// 4. PLANTILLA HTML (DISEÑO FRANJAS AMARILLAS + FOOTER OSCURO)
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; background-color: #2d2d2d; font-family: 'Helvetica', sans-serif; color: #333; }
        .page-container { width: 100%; background-color: #fff; min-height: 100%; position: relative; }
        
        /* HEADER */
        .header { background-color: #f9f9f9; padding: 30px 40px; border-bottom: 4px solid #f9af24; }
        table.head-tbl { width: 100%; border-collapse: collapse; }
        td.head-left { width: 60%; vertical-align: top; }
        td.head-right { width: 40%; vertical-align: top; text-align: right; }
        
        .label-attn { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #fff; background-color: #f9af24; padding: 3px 10px; border-radius: 10px; text-transform: uppercase; display: inline-block; margin-bottom: 5px; }
        .client-name { font-size: 20px; font-weight: bold; color: #1a1a1a; text-transform: uppercase; margin-bottom: 5px; }
        .folio { font-size: 10px; color: #666; font-weight: bold; }
        .price-tag { background-color: #1a1a1a; color: #f9af24; padding: 10px 15px; border-radius: 8px; display: inline-block; text-align: right; margin-top: 10px; border: 1px solid #f9af24; }
        .price-val { font-size: 24px; font-weight: bold; line-height: 1; }
        .price-sub { font-size: 9px; color: #ccc; margin-top: 2px; }

        /* LISTADO ESTILO BANANA (Franjas) */
        .body-section { padding: 20px 40px 180px 40px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1a1a1a; text-transform: uppercase; border-bottom: 2px solid #f9af24; padding-bottom: 3px; margin-bottom: 10px; display: inline-block; }
        
        .svc-table { width: 100%; border-collapse: collapse; }
        .svc-row td { padding: 8px 12px; vertical-align: top; }
        /* Franjas */
        .svc-row:nth-child(odd) td { background-color: #fff8e1; } /* Amarillo muy suave */
        .svc-row:nth-child(even) td { background-color: #fdfdfd; }
        .svc-name { font-size: 11px; font-weight: bold; color: #000; margin-bottom: 2px; }
        .svc-detail { font-size: 10px; color: #555; font-style: italic; }

        /* TÉRMINOS */
        .terms-box { margin-top: 20px; background-color: #f4f4f4; padding: 15px 20px; border-radius: 5px; border-left: 4px solid #1a1a1a; }
        .terms-head { font-size: 10px; font-weight: bold; margin-bottom: 8px; color: #000; text-transform: uppercase; }
        .terms-text { font-size: 9px; color: #444; text-align: justify; line-height: 1.4; }
        .terms-text p { margin: 0 0 4px 0; }

        /* FOOTER OSCURO */
        .footer { position: absolute; bottom: 0; width: 100%; background-color: #1a1a1a; color: #fff; padding: 25px 40px; border-top: 4px solid #f9af24; }
        table.ft-tbl { width: 100%; border-collapse: collapse; }
        td.ft-col { width: 50%; vertical-align: top; }
        .ft-name { font-size: 12px; font-weight: bold; color: #f9af24; text-transform: uppercase; }
        .ft-role { font-size: 9px; color: #ccc; margin-bottom: 8px; font-style: italic; }
        .ft-data { font-size: 9px; color: #fff; margin-bottom: 4px; }
        .icon-img { width: 14px; height: 14px; vertical-align: middle; margin-right: 5px; }
        a { color: inherit; text-decoration: none; }
    </style>
</head>
<body>
<div class="page-container">
    <div class="header">
        <table class="head-tbl">
            <tr>
                <td class="head-left">
                    <div class="label-attn">EN ATENCIÓN A</div>
                    <div class="client-name"><?= $clienteName ?></div>
                    <div class="folio">FOLIO: <?= $folioVisual ?></div>
                </td>
                <td class="head-right">
                    <?= $logoHTML ?>
                    <br>
                    <div class="price-tag">
                        <div class="price-val">$<?= $total ?> <span style="font-size:10px; color:#fff">MXN</span></div>
                        <div class="price-sub">+ IVA MENSUAL</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body-section">
        <div class="section-title">Servicios Incluidos</div>
        <table class="svc-table">
            <?php foreach($items as $item): ?>
            <tr class="svc-row">
                <td width="100%">
                    <div class="svc-name"><?= htmlspecialchars($item['t']) ?></div>
                    <div class="svc-detail">
                       <?php if($item['d']): ?>* <?= htmlspecialchars($item['d']) ?><?php else: ?>Servicio profesional.<?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="terms-box">
            <div class="terms-head">* Contrato a 4 meses. Renovable mensualmente.</div>
            <div class="terms-text">
                <p>• Pago en mes de emisión. Tardío genera $1,000 extra.</p>
                <p>• Sin descuentos por suspensión. No se prorratean paquetes.</p>
                <p>• Cancelaciones aviso previo día 5.</p>
                <p>• Hosting incluido en contrato. Al cancelar $3,500 anual.</p>
                <p>• No responsables por intervención del cliente.</p>
                <p>• Emergencias entrega 60min. Planeación día 20.</p>
                <p>• Horario: 8am-5pm (Cabo), Lun-Vie.</p>
                <p>• Precios +IVA. Servicios no acumulables.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <table class="ft-tbl">
            <tr>
                <td class="ft-col" style="border-right: 1px solid #444; padding-right: 15px;">
                    <div class="ft-name">Adamaris Abigail Castillo</div>
                    <div class="ft-role">Dirección Operativa</div>
                    <div class="ft-data"><img src="<?= $ic_mail ?>" class="icon-img"> adamaris@bananagroup.mx</div>
                    <div class="ft-data"><img src="<?= $ic_phone ?>" class="icon-img"> (624) 125 0058</div>
                </td>
                <td class="ft-col" style="padding-left: 20px;">
                    <div class="ft-data" style="margin-bottom:10px;"><img src="<?= $ic_web ?>" class="icon-img"> www.bananagroup.mx</div>
                    <div style="font-size:9px; color:#ccc;">
                        <img src="<?= $ic_fb ?>" class="icon-img" style="width:12px;height:12px;"> Facebook &nbsp;
                        <img src="<?= $ic_ig ?>" class="icon-img" style="width:12px;height:12px;"> Instagram &nbsp;
                        <img src="<?= $ic_in ?>" class="icon-img" style="width:12px;height:12px;"> LinkedIn
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// 5. GENERAR PDF EN MEMORIA
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfOutput = $dompdf->output();

// 6. ENVIAR CORREO
try {
    // Si usas helper global:
    if (function_exists('enviar_correo_con_adjunto_memoria')) {
        // Asumiendo que tienes esta función en mailer.php
        $asunto = "Cotización $folioVisual - Banana Group";
        $cuerpo = "<h3>Hola {$cot['empresa']}</h3><p>Adjunto encontrarás tu cotización.</p>";
        enviar_correo_con_adjunto_memoria($cot['correo'], $cot['empresa'], $asunto, $cuerpo, $pdfOutput, "Cotizacion.pdf");
    } 
    else {
        // PHPMailer directo
        $mail = new PHPMailer(true);
        // $mail->isSMTP(); // Descomenta y configura si es necesario aquí, o asegúrate que mailer.php lo haga
        
        // CONFIGURACIÓN DE TU SERVIDOR (SI NO ESTÁ EN OTRO LADO)
        // $mail->Host = '...'; $mail->Username = '...'; $mail->Password = '...'; 
        
        $mail->setFrom('no-reply@bananagroup.mx', 'Banana Group'); // AJUSTA ESTO
        $mail->addAddress($cot['correo']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Cotización $folioVisual - Banana Group";
        $mail->Body    = "
            <div style='font-family:Arial; color:#333; padding:20px; border:1px solid #eee;'>
                <h2 style='color:#f9af24;'>Hola {$cot['empresa']}</h2>
                <p>Muchas gracias por tu interés. Adjunto encontrarás la cotización solicitada con el detalle de los servicios.</p>
                <p>Cualquier duda, estamos a tus órdenes.</p>
                <br>
                <p>Atte.<br><strong>Equipo Banana Group</strong></p>
            </div>
        ";
        
        $mail->addStringAttachment($pdfOutput, "Cotizacion_{$folioVisual}.pdf", 'base64', 'application/pdf');
        $mail->send();
    }

    echo json_encode(['ok'=>true, 'msg'=>'Cotización enviada correctamente a ' . $cot['correo']]);

} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'msg'=>'Error al enviar: ' . $e->getMessage()]);
}
?>
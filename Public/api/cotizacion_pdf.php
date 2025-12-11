<?php
// Public/api/cotizacion_pdf.php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID inválido.");

$pdo = db();

// 1. DATOS BD
$sqlCot = "SELECT id, empresa, total, creado_en FROM cotizaciones WHERE id = ?";
$st = $pdo->prepare($sqlCot);
$st->execute([$id]);
$cot = $st->fetch(PDO::FETCH_ASSOC);

if (!$cot) die("Cotización no encontrada.");

$folioVisual = 'COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT);
$cliente = mb_strtoupper($cot['empresa'], 'UTF-8');
$total = number_format((float)$cot['total'], 2);

// 2. ÍTEMS
$sqlItems = "SELECT grupo, opcion, valor FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY id ASC";
$stI = $pdo->prepare($sqlItems);
$stI->execute([$id]);
$itemsDb = $stI->fetchAll(PDO::FETCH_ASSOC);

function getInfoServicio($grupo, $opcion) {
    $g = mb_strtolower(trim($grupo), 'UTF-8');
    $map = [
        'cuenta' => ['t' => 'Cuota Fija', 'd' => 'Es tu enlace entre la agencia y tu empresa. Se encarga de crear y coordinar tu estrategia de marketing digital y atender todas tus dudas.'],
        'publicaciones' => ['t' => 'Posts Semanales', 'd' => 'Se publica tanto en tu feed (muro) como en historias. Incluye Facebook e Instagram.'],
        'meta' => ['t' => 'Meta ADS', 'd' => 'No incluye el presupuesto asignado a cada campaña. El pago de campañas de vacantes es ilimitado y no se cuenta para el límite'],
        'reposteo' => ['t' => 'Community Manager', 'd' => 'Es la persona encargada de responder todos los mensajes y comentarios en un horario de 9am a 5pm lunes a viernes y sábados de 9am a 2pm, excepto
dias festivos.'],
        'stories' => ['t' => 'Diseño Gráfico', 'd' => 'Incluye el diseño del post y de las stories tanto de Facebook como de Instagram. También incluye adicionalmente cambios de perfil, portada e historias
destacadas.'],
        'fotos' => ['t' => 'Fotografía', 'd' => '1 sesión de máximo 2 horas de producción, 30 fotografías a entregar y 1 locación. Incluye uso de drone. Si contratas una cada 2 meses estarás obligado a
quedarte 4 meses en vez de 3. No incluye modelos'],
        'video' => ['t' => 'Video Reels', 'd' => 'Sesión de máximo 2 horas de producción, 4 reels en formato vertical u horizontal a entregar. Incluye uso de drone'],
        'ads' => ['t' => 'Google ADS', 'd' => 'Hasta 2 campañas simultáneamente'],
        'email' => ['t' => 'Email Marketing', 'd' => 'Boletines mensuales.'],
        'mkt'   => ['t' => 'Email Marketing', 'd' => 'Incluye el diseño y envío masivo de correo. No incluye base de datos'], // FIX PARA MKT
        'web' => ['t' => 'Sitio Web', 'd' => 'Incluye hospedaje, dominio .com, certificado SSL, correos ilimitados en cantidad. Incluye soporte en horario laboral. Debes contratar un Sistema solo si
requieres bases de datos o venta en línea.']
    ];
    foreach ($map as $k => $v) { if (strpos($g, $k) !== false) return $v; }
    return ['t' => mb_strtoupper($grupo), 'd' => $opcion];
}

$items = [];
foreach ($itemsDb as $row) {
    $items[] = getInfoServicio($row['grupo'], $row['opcion']);
}

// 3. IMÁGENES BASE64 (DORADAS)
// Logo
$rutaLogo = __DIR__ . '/../../Public/assets/logo.png';
$logoHTML = "";
if (file_exists($rutaLogo)) {
    $d = file_get_contents($rutaLogo);
    $b64 = 'data:image/' . pathinfo($rutaLogo, PATHINFO_EXTENSION) . ';base64,' . base64_encode($d);
    $logoHTML = "<img src='$b64' style='height:50px;'>";
}

// Iconos Sociales (Placeholders Dorados para mantener el archivo ligero y funcional)
// Estos son cuadrados/círculos dorados simples.
$ic_mail = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAUElEQVRIie3SMQqDQBQF0Rk02WgVPYJ26z10C/Zewc5LCMbOQjthk84mzMLHB4+B4fs7T5KJ289+3ZhtMnBnXExq4M64mNTAnXExqYF74/L+Ag9WpA97R61H6QAAAABJRU5ErkJggg==";
$ic_phone = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAVklEQVRIie3SsQ2AMBAEwZ8jBSckuu9K6IBOqCCBiEhJg8350oz2bB92Wym5zayk110FqIFVgBpYBaiBVYAaWAWogVWAGlgFqIFVgBpYBaiBVcAb+P9+sQ+jLA97/k7w+gAAAABJRU5ErkJggg==";
$ic_web = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAWklEQVRIie3SwQmAQBBE0d9C0I4t2IIt2IIt2IYVGIQgIsQ0sA/zYObxYUiSi9nN7KQ3vQWoATWwGvAG1IB3oAbUgBqwGlADasBqQA2oAasBNaAGrAb8g1/sA/V8D3t1l3+7AAAAAElFTkSuQmCC";
$ic_fb = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAALUlEQVRIiWNgGAWjYBSMglEwCkbBSAc8BED+w4Bf8D8D/vCAwz8M+AX/M+APCwA8Gg97z53eOAAAAABJRU5ErkJggg=="; 
$ic_ig = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAAQElEQVRIie3SwQ0AIAQDwU9n5WDL0A1d0AnB80MC52Y3s5JedxWgBlYBamAVoAZWAWpgFaAGVgFqYBWgBlYBb+AD9cIPe3s2Y6gAAAAASUVORK5CYII=";
$ic_in = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABmJLR0QA/wD/AP+gvaeTAAAANUlEQVRIiWNgGAWjYBSMglEwCkbBSAc8BED+w4Bf8D8D/vCAwz8M+AX/M+APDzj8w0AGBgA82Qo/2495+wAAAABJRU5ErkJggg==";


// 4. PLANTILLA HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; padding: 0; }
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            background-color: #2d2d2d;
            font-family: 'Helvetica', sans-serif;
            color: #333;
        }
        .page-container {
            min-height: 100%;
            position: relative;
            background-color: #fff;
        }
        
        /* HEADER */
        .header { background-color: #f9f9f9; padding: 30px 40px; border-bottom: 4px solid #f9af24; }
        table.head-tbl { width: 100%; border-collapse: collapse; }
        td.head-left { width: 60%; vertical-align: top; }
        td.head-right { width: 40%; vertical-align: top; text-align: right; }

        .label-attn {
            font-size: 10px; font-weight: bold; letter-spacing: 1px;
            color: #fff; background-color: #f9af24; 
            padding: 3px 10px; border-radius: 10px;
            text-transform: uppercase; display: inline-block; margin-bottom: 5px;
        }
        .client-name { font-size: 24px; font-weight: bold; color: #1a1a1a; text-transform: uppercase; margin-bottom: 5px; }
        .folio { font-size: 11px; color: #666; font-weight: bold; }

        .price-tag {
            background-color: #1a1a1a; color: #f9af24;
            padding: 10px 15px; border-radius: 8px;
            display: inline-block; text-align: right;
            margin-top: 10px; border: 1px solid #f9af24;
        }
        .price-val { font-size: 24px; font-weight: bold; line-height: 1; }
        .price-sub { font-size: 10px; color: #ccc; margin-top: 2px; }

        /* BODY (Padding bottom grande para el footer) */
        .body-section { padding: 20px 40px 180px 40px; }
        
        .section-title {
            font-size: 12px; font-weight: bold; color: #1a1a1a;
            text-transform: uppercase; border-bottom: 2px solid #f9af24;
            padding-bottom: 3px; margin-bottom: 10px; display: inline-block;
        }
        
        /* LISTADO */
        .svc-table { width: 100%; border-collapse: collapse; }
        .svc-row td { padding: 8px 12px; vertical-align: top; }
        .svc-row:nth-child(odd) td { background-color: #fff8e1; }
        .svc-row:nth-child(even) td { background-color: #fdfdfd; }
        .svc-name { font-size: 11px; font-weight: bold; color: #000; margin-bottom: 2px; }
        .svc-detail { font-size: 10px; color: #555; font-style: italic; }

        /* TÉRMINOS */
        .terms-box {
            margin-top: 15px; background-color: #f4f4f4; padding: 10px 15px;
            border-radius: 5px; border-left: 3px solid #1a1a1a;
        }
        .terms-head { font-size: 9px; font-weight: bold; margin-bottom: 5px; color: #000; }
        .terms-text { font-size: 9px; color: #444; text-align: justify; line-height: 1.3; }
        .terms-text p { margin: 0 0 2px 0; }

        /* FOOTER OSCURO PEGADO AL FINAL */
        .footer {
            position: absolute;
            bottom: 0; width: 100%;
            background-color: #1a1a1a; color: #fff;
            padding: 20px 40px;
            border-top: 3px solid #f9af24;
        }
        table.ft-tbl { width: 100%; border-collapse: collapse; }
        td.ft-col { width: 50%; vertical-align: top; }
        
        .ft-name { font-size: 12px; font-weight: bold; color: #f9af24; text-transform: uppercase; }
        .ft-role { font-size: 9px; color: #ccc; margin-bottom: 8px; font-style: italic; }
        .ft-data { font-size: 9px; color: #fff; margin-bottom: 4px; }
        
        /* Estilos de enlaces */
        .link-clean { text-decoration: none; color: inherit; display: inline-block; }
        .icon-img { width: 14px; height: 14px; vertical-align: middle; margin-right: 5px; }
        .ft-data a:hover { text-decoration: underline; }

    </style>
</head>
<body>
<div class="page-container">
    
    <div class="header">
        <table class="head-tbl">
            <tr>
                <td class="head-left">
                    <div class="label-attn">EN ATENCIÓN A</div>
                    <div class="client-name"><?= $cliente ?></div>
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
                <p>• Emergencias entrega 60min (solo desastres/vacantes).</p>
                <p>• Planeación entrega día 20 mes anterior.</p>
                <p>• Horario: 8am-5pm (Cabo), Lun-Vie.</p>
                <p>• Precios +IVA. Sesiones reprogramables 24h antes.</p>
                <p>• Servicios no acumulables.</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <table class="ft-tbl">
            <tr>
                <td class="ft-col" style="border-right: 1px solid #444; padding-right: 15px;">
                    <div class="ft-name">Adamaris Abigail Castillo</div>
                    <div class="ft-role">Dirección Operativa</div>
                    
                    <div class="ft-data">
                        <a href="mailto:adamaris@bananagroup.mx" class="link-clean">
                            <img src="<?= $ic_mail ?>" class="icon-img"> adamaris@bananagroup.mx
                        </a>
                    </div>
                    <div class="ft-data">
                        <a href="tel:+526241250058" class="link-clean">
                            <img src="<?= $ic_phone ?>" class="icon-img"> (624) 125 0058
                        </a>
                    </div>
                </td>

                <td class="ft-col" style="padding-left: 20px;">
                    <div class="ft-data" style="margin-bottom:10px;">
                        <a href="https://www.bananagroup.mx" target="_blank" class="link-clean">
                            <img src="<?= $ic_web ?>" class="icon-img"> www.bananagroup.mx
                        </a>
                    </div>
                    
                    <div style="font-size:9px; color:#ccc;">
                        <a href="https://facebook.com" target="_blank" class="link-clean" style="margin-right:10px;">
                            <img src="<?= $ic_fb ?>" class="icon-img" style="width:12px;height:12px;"> Facebook
                        </a>
                        <a href="https://instagram.com" target="_blank" class="link-clean" style="margin-right:10px;">
                            <img src="<?= $ic_ig ?>" class="icon-img" style="width:12px;height:12px;"> Instagram
                        </a>
                        <a href="https://linkedin.com" target="_blank" class="link-clean">
                            <img src="<?= $ic_in ?>" class="icon-img" style="width:12px;height:12px;"> LinkedIn
                        </a>
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
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $cot['empresa']);
$dompdf->stream("Cotizacion_{$cleanName}.pdf", ["Attachment" => true]);
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

// 1. Cargar Datos
$st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
$st->execute([$id]);
$cot = $st->fetch(PDO::FETCH_ASSOC);
if (!$cot) die("Cotización no encontrada.");

$stI = $pdo->prepare("SELECT * FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY id ASC");
$stI->execute([$id]);
$items = $stI->fetchAll(PDO::FETCH_ASSOC);

// 2. Descripciones del Cotizador (Textos exactos del formulario)
function getDescripcionServicio($grupo, $opcion = '') {
    $g = strtolower(trim($grupo));
    
    // Textos base
    $textos = [
        'cuenta'        => 'Es tu enlace entre la agencia y tu empresa. Se encarga de crear y coordinar tu estrategia de marketing digital y atender todas tus dudas.',
        'publicaciones' => 'Se publica tanto en tu feed (muro) como en historias. Incluye Facebook e Instagram.',
        'campañas'      => 'No incluye el presupuesto asignado a cada campaña (pago directo a Facebook/Meta).',
        'reposteo'      => 'Atiende mensajes y comentarios en horario laboral. Manejo de Google Maps, TripAdvisor, etc.',
        'stories'       => 'Incluye diseño de post y stories.',
        'imprenta'      => 'Diseño gráfico para imprenta o identidad.',
        'fotos'         => 'Sesión fotográfica profesional.',
        'video'         => 'Sesión de producción de video profesional.',
        'ads'           => 'Manejo de campañas en Google ADS.',
        'web'           => 'Desarrollo y mantenimiento de sitio web.',
        'mkt'           => 'Estrategia de Email Marketing.',
    ];

    $desc = $textos[$g] ?? '';

    // Ajustes específicos según la opción (para que coincida más con el PDF)
    if ($g === 'fotos') $desc .= " *Máximo 2 horas de visita.";
    if ($g === 'video') $desc .= " *Máximo 2 horas de visita.";
    if ($g === 'publicaciones') $desc .= " *No incluye el diseño de la publicación (si no se contrata diseño aparte).";

    return $desc;
}

// 3. Variables de Formato
$folio    = $cot['folio'] ?? 'COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT);
$fecha    = date('d/m/Y', strtotime($cot['creado_en']));
$cliente  = mb_strtoupper($cot['empresa']);
$total    = number_format((float)$cot['total'], 2);

// Logo
$rutaLogo = __DIR__ . '/../../Public/assets/logo.png'; 
$logoBase64 = '';
if (file_exists($rutaLogo)) {
    $type = pathinfo($rutaLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($rutaLogo);
    $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// 4. HTML - REPLICA DEL PDF ORIGINAL
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 40px 50px; }
        body {
            font-family: "Helvetica", Arial, sans-serif;
            color: #444;
            font-size: 11px;
            line-height: 1.4;
        }
        
        /* HEADER */
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        
        .logo-img { height: 70px; margin-bottom: 10px; display:block; margin-left: auto; }
        
        .attn-label {
            font-size: 10px;
            font-weight: bold;
            color: #888;
            letter-spacing: 1px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .client-name {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .cot-label {
            font-size: 12px;
            font-weight: bold;
            color: #888;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        /* PRECIO GRANDE */
        .price-container {
            text-align: right;
            margin-top: 10px;
        }
        .big-price {
            font-size: 42px; /* Tamaño grande como en el PDF */
            font-weight: bold;
            color: #000;
            line-height: 1;
        }
        .price-sub {
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }
        .plus-sign {
            font-size: 24px;
            color: #fdd835;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* SERVICIOS */
        .services-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .services-table td { padding-bottom: 15px; vertical-align: top; }
        
        .svc-title {
            font-size: 12px;
            font-weight: bold;
            color: #eebb00; /* Dorado/Naranja del PDF */
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .svc-desc {
            font-size: 11px;
            color: #333;
        }
        
        /* TÉRMINOS */
        .terms {
            margin-top: 40px;
            font-size: 9px;
            color: #555;
            text-align: justify;
            line-height: 1.3;
        }
        .terms p { margin: 0 0 5px 0; }
        
        /* FOOTER CONTACTO */
        .footer-table { 
            width: 100%; 
            margin-top: 40px; 
            border-top: 1px solid #ddd; 
            padding-top: 20px;
        }
        .contact-name { font-weight: bold; font-size: 12px; color: #000; }
        .contact-role { font-size: 11px; color: #666; margin-bottom: 5px; }
        .contact-detail { font-size: 11px; color: #444; }
        .contact-icon { color: #fdd835; margin-right: 5px; font-weight: bold; }

    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="60%">
                <div class="attn-label">EN ATENCIÓN A</div>
                <div class="client-name">' . $cliente . '</div>
                <div class="cot-label">COTIZACIÓN</div>
                <div style="font-size:10px; color:#888; margin-top:5px;">FOLIO: ' . $folio . '</div>
            </td>
            <td width="40%" align="right">
                ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo-img">' : '<h2>BANANA</h2>') . '
                
                <div class="price-container">
                    <div class="big-price">$' . $total . ' <span style="font-size:20px">MXN*</span></div>
                    <div class="price-sub">+ IVA mensual</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="plus-sign">+</div>

    <table class="services-table">';

    foreach ($items as $it) {
        $titulo = $it['grupo'];
        if(!empty($it['opcion'])) $titulo .= " (" . $it['opcion'] . ")";
        
        $desc = getDescripcionServicio($it['grupo'], $it['opcion']);

        $html .= '
        <tr>
            <td>
                <div class="svc-title">' . htmlspecialchars($titulo) . '</div>
                <div class="svc-desc">' . htmlspecialchars($desc) . '</div>
            </td>
        </tr>';
    }

    $html .= '
    </table>

    <div class="terms">
        <p>* Contrato especial a 4 meses. Después es renovable por periodos de 1 mes.</p>
        <p>* Se paga durante el mes de la emisión de la factura. En caso contrario se cobran $1,000 adicionales por concepto de pago tardío y se suspende el servicio totalmente.</p>
        <p>* No se da descuento por el tiempo suspendido.</p>
        <p>* No se prorratean paquetes. (Por ejemplo, si el día 5 de Septiembre se desea cancelar el servicio y pagar el mes en curso, debe pagarse todo el mes de Septiembre, no solamente 5 días).</p>
        <p>* Se tiene hasta el día 5 de cada mes para cancelar el servicio del siguiente mes. (Por ejemplo, a más tardar el 5 de Septiembre se debe avisar que no se requerirá el servicio en el mes de Octubre).</p>
        <p>* El hospedaje y dominio están incluidos en su paquete durante la contratación del servicio mensual; a partir de la cancelación aplica anualidad de $3,500 + IVA en caso de querer mantener el sitio en línea.</p>
        <p>* No nos hacemos responsables de estadísticas de ventas si el cliente interviene en el proceso en contra de nuestras recomendaciones.</p>
        <p>* Diseños de emergencia se entregan en 60 minutos. Solo se consideran emergencias las situaciones que alteren la operación de su negocio (Desastres naturales, previo aviso y vacantes).</p>
        <p>* Planeación del mes siguiente se entrega el día 20 del mes anterior como máximo, dejando 10 días naturales para correcciones y/o ajustes.</p>
        <p>* Website informativo se entrega 15 días naturales después de tener toda la información que incluirá el mismo.</p>
        <p>* Website con tema web se entrega en 20 días hábiles una vez entregada toda la información que incluirá el mismo.</p>
        <p>* Horario de atención: 8:00 am a 5:00 pm hora Los Cabos. 9:00 am a 6:00 pm hora Guadalajara lunes a viernes. No atendemos sábados, domingos ni días festivos.</p>
        <p>* Precios más IVA en caso de factura, depósito o transferencia.</p>
        <p>* Las sesiones se pueden reprogramar 24 hrs antes. Si se cancelan entre 1 y 30 minutos antes hay una cuota de $500 por foto y $500 por video. Si se cancela en menos de 30 minutos antes se considera como sesión cumplida.</p>
        <p>* Todos los servicios, se usen o no total o parcialmente, vencen el último día hábil de cada mes. No son acumulables.</p>
    </div>

    <table class="footer-table">
        <tr>
            <td width="60%">
                <div class="contact-name">Adamaris Abigail Castillo Jimenez</div>
                <div class="contact-role">Dirección Operativa</div>
                <div class="contact-detail"><span class="contact-icon">✉</span> adamaris@bananagroup.mx</div>
            </td>
            <td width="40%" align="right" valign="bottom">
                <div class="contact-detail" style="font-size:14px; font-weight:bold;">(624) 125 0058</div>
                <div class="contact-detail">www.bananagroup.mx</div>
            </td>
        </tr>
    </table>

</body>
</html>';

// Generar PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nombre de Archivo
$cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', $cot['empresa']);
$dompdf->stream("Cotizacion_{$cleanName}.pdf", ["Attachment" => true]);
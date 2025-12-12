<?php
// Public/api/descargar_recibo.php
declare(strict_types=1);

// 1. Cargar dependencias
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Seguridad: Solo usuarios logueados pueden descargar
if (session_status() === PHP_SESSION_NONE) session_start();
if (!current_user()) {
    die("Acceso denegado. Debes iniciar sesión.");
}

// Validar ID
$pagoId = (int)($_GET['id'] ?? 0);
if ($pagoId <= 0) die("ID de pago inválido.");

$pdo = db();

// 1. OBTENER DATOS (Incluyendo RFC del Emisor)
$sql = "
    SELECT 
        p.id as pago_id, 
        p.monto, 
        p.metodo, 
        p.referencia, 
        p.creado_en as fecha_pago, 
        p.cargo_id,
        c.empresa, 
        c.correo, 
        c.telefono,
        cg.periodo_inicio, 
        cg.periodo_fin,
        -- Traemos datos del RFC Emisor desde la orden
        r.razon_social as emisor_razon, 
        r.rfc as emisor_rfc
    FROM pagos p
    JOIN ordenes o ON o.id = p.orden_id
    JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN cargos cg ON cg.id = p.cargo_id
    LEFT JOIN company_rfcs r ON r.id = o.rfc_id
    WHERE p.id = ?
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$pagoId]);
$datos = $st->fetch(PDO::FETCH_ASSOC);

if (!$datos) die("El pago no existe.");

// 2. DEFINIR DATOS DEL EMISOR (Corrección del error Undefined variable)
$emisorNombre = $datos['emisor_razon'] ?: 'Banana Group Marketing';
$emisorRFC    = $datos['emisor_rfc'] ? ('RFC: ' . $datos['emisor_rfc']) : 'info@bananagroup.mx';

if (!$datos) die("El pago no existe o fue eliminado.");

// 3. Obtener el DESGLOSE (Los servicios específicos)
// Buscamos en la tabla 'cargo_items' que guarda qué se cobró exactamente
$items = [];
if (!empty($datos['cargo_id'])) {
    $stItems = $pdo->prepare("SELECT concepto, total FROM cargo_items WHERE cargo_id = ?");
    $stItems->execute([$datos['cargo_id']]);
    $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
}



// 4. Preparar variables para el PDF
$folio         = str_pad((string)$datos['pago_id'], 6, '0', STR_PAD_LEFT); // Ej: 000123
// Limpiamos el nombre de la empresa para que sea seguro en el nombre del archivo
$empresaSafe   = preg_replace('/[^a-zA-Z0-9]/', '_', $datos['empresa']); 
$nombreArchivo = "Recibo_Pago_{$empresaSafe}_{$folio}.pdf";

$fecha      = date('d/m/Y H:i', strtotime($datos['fecha_pago']));
$montoTotal = number_format((float)$datos['monto'], 2);

$periodoTxt = "Pago a cuenta";
if ($datos['periodo_inicio']) {
    $periodoTxt = date('d/m/Y', strtotime($datos['periodo_inicio'])) . ' al ' . date('d/m/Y', strtotime($datos['periodo_fin']));
}

// 5. Cargar el Logo (Conversión a Base64 para evitar errores de rutas en PDF)
$rutaLogo = __DIR__ . '/../../Public/assets/logo.png'; 
$logoBase64 = '';
if (file_exists($rutaLogo)) {
    $type = pathinfo($rutaLogo, PATHINFO_EXTENSION);
    $data = file_get_contents($rutaLogo);
    $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// 6. Construir el HTML con el diseño de Banana Group
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #333; margin: 0; padding: 0; }
        
        /* Encabezado Amarillo Institucional */
        .header-bg {
            background-color: #fff2a8; /* Color Banana */
            padding: 30px;
            border-bottom: 4px solid #fdd835; /* Borde más oscuro */
        }
        
        .header-table { width: 100%; }
        .logo-img { max-height: 60px; }
        
        .title { text-align: right; font-size: 22px; font-weight: bold; color: #444; text-transform: uppercase; }
        .subtitle { text-align: right; font-size: 12px; color: #666; margin-top: 5px; }

        .info-section { margin: 30px; }
        .info-table { width: 100%; }
        .info-table td { vertical-align: top; width: 50%; }
        
        .label { font-size: 10px; text-transform: uppercase; color: #888; font-weight: bold; margin-bottom: 3px; }
        .value { font-size: 14px; color: #000; margin-bottom: 15px; }

        /* Tabla de Conceptos (Desglose) */
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 30px; width: calc(100% - 60px); }
        .items-table th { background: #f8f9fa; padding: 10px; text-align: left; font-size: 11px; border-bottom: 2px solid #ddd; color: #555; text-transform: uppercase; }
        .items-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        .items-table .amount { text-align: right; font-weight: bold; }

        .total-section { text-align: right; margin: 30px; border-top: 2px solid #fdd835; padding-top: 15px; }
        .total-lbl { font-size: 14px; color: #666; margin-right: 15px; }
        .total-val { font-size: 24px; font-weight: bold; color: #000; }

        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #f9f9f9; padding: 15px; text-align: center;
            font-size: 10px; color: #999; border-top: 1px solid #eee;
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <table class="header-table">
            <tr>
                <td>
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo-img">' : '<h2>BANANA GROUP</h2>') . '
                </td>
                <td class="title">
                    Recibo de Pago<br>
                    <div class="subtitle">Folio: #' . $folio . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td>
                    <div class="label">EMISOR</div>
                    <div class="value">
                        <strong>' . htmlspecialchars($emisorNombre) . '</strong><br>
                        ' . htmlspecialchars($emisorRFC) . '<br>
                        info@bananagroup.mx
                    </div>
                </td>
                <td>
                    <div class="label">CLIENTE</div>
                    <div class="value">
                        <strong>' . htmlspecialchars($datos['empresa']) . '</strong><br>
                        ' . htmlspecialchars($datos['correo']) . '<br>
                        ' . htmlspecialchars($datos['telefono'] ?? '') . '
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">FECHA DE PAGO</div>
                    <div class="value">' . $fecha . '</div>
                </td>
                <td>
                    <div class="label">MÉTODO / REFERENCIA</div>
                    <div class="value">
                        ' . strtoupper($datos['metodo']) . '<br>
                        ' . htmlspecialchars($datos['referencia'] ?: 'Sin referencia') . '
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>DESCRIPCIÓN</th>
                <th style="text-align: right;">IMPORTE</th>
            </tr>
        </thead>
        <tbody>';

        // Lógica del desglose: Si hay items en el cargo, los listamos uno por uno
        if (!empty($items)) {
            foreach ($items as $it) {
                $html .= '
                <tr>
                    <td>
                        ' . htmlspecialchars($it['concepto']) . '
                        <div style="font-size:10px; color:#888; margin-top:2px;">Periodo: ' . $periodoTxt . '</div>
                    </td>
                    <td class="amount">$' . number_format((float)$it['total'], 2) . '</td>
                </tr>';
            }
        } else {
            // Fallback para pagos antiguos sin detalle
            $html .= '
            <tr>
                <td>Servicios Digitales (' . $periodoTxt . ')</td>
                <td class="amount">$' . $montoTotal . '</td>
            </tr>';
        }

$html .= '
        </tbody>
    </table>

    <div class="total-section">
        <span class="total-lbl">TOTAL PAGADO:</span>
        <span class="total-val">$' . $montoTotal . ' MXN</span>
    </div>

    <div class="footer">
        Este documento es un comprobante de pago interno y no sustituye a una factura fiscal (CFDI).<br>
        Gracias por su preferencia.
    </div>

</body>
</html>';

// 7. Generar y Descargar PDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Permitir imágenes
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Enviar al navegador con el nombre personalizado
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
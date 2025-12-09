<?php
// Public/api/descargar_recibo.php
declare(strict_types=1);

// 1. Cargar dependencias
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Seguridad: Solo usuarios logueados
if (session_status() === PHP_SESSION_NONE) session_start();
if (!current_user()) {
    die("Acceso denegado");
}

$pagoId = (int)($_GET['id'] ?? 0);
if ($pagoId <= 0) die("ID de pago inválido");

$pdo = db();

// 2. Obtener datos del Pago, Cliente y Cargo
$sql = "
    SELECT 
        p.id as pago_id, p.monto, p.metodo, p.referencia, p.creado_en as fecha_pago,
        c.empresa, c.correo, c.telefono,
        cg.periodo_inicio, cg.periodo_fin,
        o.id as orden_id
    FROM pagos p
    JOIN ordenes o ON o.id = p.orden_id
    JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN cargos cg ON cg.id = p.cargo_id
    WHERE p.id = ?
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$pagoId]);
$datos = $st->fetch(PDO::FETCH_ASSOC);

if (!$datos) die("Pago no encontrado");

// Formatos
$folio    = str_pad((string)$datos['pago_id'], 6, '0', STR_PAD_LEFT);
$fecha    = date('d/m/Y H:i', strtotime($datos['fecha_pago']));
$monto    = '$' . number_format((float)$datos['monto'], 2);
$periodo  = "Pago general";
if ($datos['periodo_inicio']) {
    $periodo = date('d/m/Y', strtotime($datos['periodo_inicio'])) . ' al ' . date('d/m/Y', strtotime($datos['periodo_fin']));
}

// 3. Crear el HTML del Recibo (Diseño)
// Puedes poner tu logo real en base64 o ruta absoluta
$html = '
<html>
<head>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #fdd835; padding-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #000; }
        .titulo { font-size: 18px; color: #555; margin-top: 10px; }
        
        .info-box { width: 100%; margin-bottom: 30px; }
        .info-box td { vertical-align: top; width: 50%; }
        
        .detalles { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .detalles th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .detalles td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .total-box { text-align: right; margin-top: 20px; }
        .total-label { font-size: 14px; color: #777; }
        .total-amount { font-size: 24px; font-weight: bold; color: #000; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #aaa; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">BANANA GROUP</div>
        <div class="titulo">Comprobante de Pago</div>
    </div>

    <table class="info-box">
        <tr>
            <td>
                <strong>De:</strong><br>
                Banana Group<br>
                contacto@bananagroup.mx
            </td>
            <td style="text-align: right;">
                <strong>Recibido de:</strong><br>
                ' . htmlspecialchars($datos['empresa']) . '<br>
                ' . htmlspecialchars($datos['correo']) . '<br>
                <br>
                <strong>Folio Pago:</strong> #' . $folio . '<br>
                <strong>Fecha:</strong> ' . $fecha . '
            </td>
        </tr>
    </table>

    <table class="detalles">
        <thead>
            <tr>
                <th>Concepto / Periodo</th>
                <th>Método</th>
                <th>Referencia</th>
                <th style="text-align: right;">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Servicios Digitales<br><small style="color:#777">' . $periodo . '</small></td>
                <td>' . strtoupper($datos['metodo']) . '</td>
                <td>' . ($datos['referencia'] ?: '—') . '</td>
                <td style="text-align: right;">' . $monto . '</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        <span class="total-label">Total Pagado:</span><br>
        <span class="total-amount">' . $monto . ' MXN</span>
    </div>

    <div class="footer">
        Este documento es un comprobante de pago interno y no sustituye a una factura fiscal.
        <br>Generado automáticamente por el sistema Banana Group.
    </div>
</body>
</html>';

// 4. Generar PDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Para cargar imágenes si usas url
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// 5. Descargar (Stream)
$dompdf->stream("Recibo_Banana_Pago_{$folio}.pdf", ["Attachment" => true]);
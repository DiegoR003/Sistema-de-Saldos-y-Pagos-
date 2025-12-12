<?php
// Public/api/cotizaciones_export_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = db();

// 1. FILTROS (Igual que Excel)
$q      = trim($_GET['q']   ?? '');
$estado = trim($_GET['est'] ?? '');
$desde  = trim($_GET['f1']  ?? '');
$hasta  = trim($_GET['f2']  ?? '');

$where=[]; $args=[];
if ($q!=='') { $where[]="(empresa LIKE ? OR correo LIKE ? OR id = ?)"; $args[]="%$q%"; $args[]="%$q%"; $args[]=ctype_digit($q)?(int)$q:0; }
if (in_array($estado,['pendiente','aprobada','rechazada'], true)) { $where[]="estado=?"; $args[]=$estado; }
if ($desde!=='') { $where[]="DATE(creado_en) >= ?"; $args[]=$desde; }
if ($hasta!=='') { $where[]="DATE(creado_en) <= ?"; $args[]=$hasta; }
$W = $where ? 'WHERE '.implode(' AND ',$where) : '';

// 2. CONSULTA
$st = $pdo->prepare("SELECT * FROM cotizaciones $W ORDER BY creado_en DESC");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// 3. HTML DEL REPORTE
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 12px; color: #333; }
    h2 { border-bottom: 3px solid #fdd835; padding-bottom: 10px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #f4f4f4; text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
    td { padding: 8px; border-bottom: 1px solid #eee; }
    .text-end { text-align: right; }
    .badge { padding: 3px 6px; border-radius: 4px; font-size: 10px; color: #fff; font-weight: bold; }
    .aprobada { background-color: #198754; }
    .pendiente { background-color: #ffc107; color: #000; }
    .rechazada { background-color: #dc3545; }
</style>
</head>
<body>
    <h2>Reporte de Cotizaciones</h2>
    <p>Fecha de emisión: <?= date('d/m/Y H:i') ?></p>
    
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $r): 
                $stClass = $r['estado']; 
                $folio = 'COT-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT);
            ?>
            <tr>
                <td><?= $folio ?></td>
                <td>
                    <?= htmlspecialchars($r['empresa']) ?><br>
                    <small style="color:#666;"><?= htmlspecialchars($r['correo']) ?></small>
                </td>
                <td><?= date('d/m/Y', strtotime($r['creado_en'])) ?></td>
                <td><span class="badge <?= $stClass ?>"><?= ucfirst($r['estado']) ?></span></td>
                <td class="text-end">$<?= number_format((float)$r['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// 4. GENERAR PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Reporte_Cotizaciones.pdf", ["Attachment" => true]);
?>
<?php
// Public/api/corte_diario_export_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = db();

$fechaInput  = $_GET['fecha'] ?? date('Y-m-d');
$clienteBusq = trim($_GET['cliente'] ?? '');

// CONSULTA
$where = "WHERE DATE(p.creado_en) = :f";
$params = [':f' => $fechaInput];
if ($clienteBusq !== '') {
    $where .= " AND c.empresa LIKE :cli";
    $params[':cli'] = "%{$clienteBusq}%";
}

$sql = "
SELECT
    LPAD(cg.id, 6, '0') AS folio,
    c.empresa           AS cliente,
    p.creado_en         AS fecha_pago,
    p.monto             AS importe,
    p.metodo            AS metodo,
    GROUP_CONCAT(DISTINCT oi.concepto SEPARATOR ', ') AS conceptos
FROM pagos p
JOIN cargos cg  ON cg.id = p.cargo_id
JOIN ordenes o  ON o.id = cg.orden_id
JOIN clientes c ON c.id = o.cliente_id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
{$where}
GROUP BY p.id
ORDER BY p.creado_en DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Totales
$total = 0; $efectivo = 0;
foreach($rows as $r) {
    $m = (float)$r['importe'];
    $total += $m;
    if(strtolower($r['metodo']) === 'efectivo') $efectivo += $m;
}

// HTML PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 11px; color: #333; }
    h2 { border-bottom: 3px solid #fdd835; padding-bottom: 10px; margin-bottom: 15px; }
    .resumen { margin-bottom: 20px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #f4f4f4; text-align: left; padding: 6px; border-bottom: 1px solid #ccc; font-size:10px; text-transform:uppercase; }
    td { padding: 6px; border-bottom: 1px solid #eee; }
    .text-end { text-align: right; }
    .total-row td { border-top: 2px solid #333; font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
    <h2>Corte Diario</h2>
    <div class="resumen">
        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($fechaInput)) ?><br>
        <strong>Generado:</strong> <?= date('H:i') ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th width="40%">Servicios</th>
                <th>Método</th>
                <th class="text-end">Importe</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $r): ?>
            <tr>
                <td><?= $r['folio'] ?></td>
                <td><?= htmlspecialchars($r['cliente']) ?></td>
                <td><?= htmlspecialchars(mb_strimwidth($r['conceptos'], 0, 50, '...')) ?></td>
                <td><?= strtoupper($r['metodo']) ?></td>
                <td class="text-end">$<?= number_format((float)$r['importe'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="total-row">
                <td colspan="4" class="text-end">TOTAL COBRADO:</td>
                <td class="text-end">$<?= number_format($total, 2) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="text-end" style="color:#666;">En Efectivo:</td>
                <td class="text-end" style="color:#666;">$<?= number_format($efectivo, 2) ?></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Corte_$fechaInput.pdf", ["Attachment" => true]);
?>
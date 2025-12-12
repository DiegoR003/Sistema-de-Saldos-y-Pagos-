<?php
// Public/api/corte_diario_export_excel.php
require_once __DIR__ . '/../../App/bd.php';
$pdo = db();

// 1. RECUPERAR FILTROS
$fechaInput  = $_GET['fecha'] ?? date('Y-m-d');
$clienteBusq = trim($_GET['cliente'] ?? '');

// 2. CONSULTA (Misma lógica que la vista)
$where  = "WHERE DATE(p.creado_en) = :f";
$params = [':f' => $fechaInput];

if ($clienteBusq !== '') {
    $where .= " AND c.empresa LIKE :cli";
    $params[':cli'] = "%{$clienteBusq}%";
}

$sql = "
SELECT
    LPAD(cg.id, 6, '0')                  AS folio,
    c.empresa                            AS cliente,
    p.creado_en                          AS fecha_pago,
    p.monto                              AS importe,
    p.metodo                             AS metodo,
    p.referencia                         AS referencia,
    GROUP_CONCAT(DISTINCT oi.concepto SEPARATOR ', ') AS conceptos
FROM pagos p
JOIN cargos cg        ON cg.id = p.cargo_id
JOIN ordenes o        ON o.id = cg.orden_id
JOIN clientes c       ON c.id = o.cliente_id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
{$where}
GROUP BY p.id, cg.id, c.empresa, p.creado_en, p.monto, p.metodo, p.referencia
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

// 3. HEADERS EXCEL
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Corte_$fechaInput.xls");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
<style>
    th { background-color: #fdd835; color: #000; border: 1px solid #000; }
    td { border: 1px solid #ccc; }
</style>
<h3>Corte Diario - <?= date('d/m/Y', strtotime($fechaInput)) ?></h3>
<table>
    <thead>
        <tr>
            <th>Folio</th>
            <th>Cliente</th>
            <th>Servicios</th>
            <th>Fecha/Hora</th>
            <th>Método</th>
            <th>Referencia</th>
            <th>Importe</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
            <td><?= $r['folio'] ?></td>
            <td><?= htmlspecialchars($r['cliente']) ?></td>
            <td><?= htmlspecialchars($r['conceptos']) ?></td>
            <td><?= date('H:i:s', strtotime($r['fecha_pago'])) ?></td>
            <td><?= strtoupper($r['metodo']) ?></td>
            <td><?= $r['referencia'] ?></td>
            <td>$<?= number_format((float)$r['importe'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="6" align="right"><strong>TOTAL COBRADO:</strong></td>
            <td><strong>$<?= number_format($total, 2) ?></strong></td>
        </tr>
        <tr>
            <td colspan="6" align="right"><strong>EFECTIVO:</strong></td>
            <td>$<?= number_format($efectivo, 2) ?></td>
        </tr>
    </tbody>
</table>
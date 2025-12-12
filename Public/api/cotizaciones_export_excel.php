<?php
// Public/api/cotizaciones_export_excel.php
require_once __DIR__ . '/../../App/bd.php';
$pdo = db();

// 1. RECUPERAR FILTROS (Misma lógica que en tu vista)
$q      = trim($_GET['q']   ?? '');
$estado = trim($_GET['est'] ?? '');
$desde  = trim($_GET['f1']  ?? '');
$hasta  = trim($_GET['f2']  ?? '');

$where = []; 
$args = [];

if ($q !== '') {
    $where[] = "(empresa LIKE ? OR correo LIKE ? OR id = ?)";
    $args[] = "%$q%"; $args[] = "%$q%"; $args[] = ctype_digit($q) ? (int)$q : 0;
}
if (in_array($estado, ['pendiente', 'aprobada', 'rechazada'], true)) {
    $where[] = "estado=?"; 
    $args[] = $estado;
}
if ($desde !== '') { $where[] = "DATE(creado_en) >= ?"; $args[] = $desde; }
if ($hasta !== '') { $where[] = "DATE(creado_en) <= ?"; $args[] = $hasta; }

$W = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 2. CONSULTA (Sin límites de paginación)
$sql = "
  SELECT id, empresa, correo, subtotal, impuestos, total, estado, creado_en
  FROM cotizaciones
  $W
  ORDER BY creado_en DESC, id DESC
";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// 3. HEADERS PARA DESCARGA EXCEL
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Reporte_Cotizaciones_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// 4. GENERAR TABLA HTML (Excel la interpreta)
?>
<meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
<table border="1">
    <thead>
        <tr style="background-color: #fdd835; color: #000;">
            <th>Folio</th>
            <th>Cliente</th>
            <th>Correo</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Subtotal</th>
            <th>IVA</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
            <td><?= 'COT-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($r['empresa']) ?></td>
            <td><?= htmlspecialchars($r['correo']) ?></td>
            <td><?= date('d/m/Y', strtotime($r['creado_en'])) ?></td>
            <td><?= ucfirst($r['estado']) ?></td>
            <td><?= number_format((float)$r['subtotal'], 2) ?></td>
            <td><?= number_format((float)$r['impuestos'], 2) ?></td>
            <td><?= number_format((float)$r['total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
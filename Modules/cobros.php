<?php
// Modules/cobros.php
require_once __DIR__ . '/../App/bd.php';
$pdo = db();

/* =========================
   Filtro por cliente
   ========================= */
$clienteId = (int)($_GET['cliente_id'] ?? 0);

/* =========================
   Lista de clientes que tienen pagos
   ========================= */
$stCli = $pdo->query("
  SELECT DISTINCT c.id, c.empresa
  FROM cargos cg
  JOIN ordenes o ON o.id = cg.orden_id
  JOIN clientes c ON c.id = o.cliente_id
  ORDER BY c.empresa
");
$clientes = $stCli->fetchAll(PDO::FETCH_ASSOC);

/* =========================
    PAGINACIÓN
   ========================= */
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 5;
$offset = ($page - 1) * $limit;

/* =========================
   Filtro "Mostrar" (rango de fechas)
   ========================= */
$rango = $_GET['rango'] ?? 'todos';

/* =========================
   Consulta de cobros
   ========================= */
$where = "WHERE 1=1";
$args  = [];

if ($clienteId > 0) {
    $where .= " AND c.id = :cid";
    $args[':cid'] = $clienteId;
}

switch ($rango) {
  case 'hoy':   $where .= " AND DATE(cg.creado_en) = CURDATE()"; break;
  case '7dias': $where .= " AND cg.creado_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
  case 'mes':   $where .= " AND YEAR(cg.creado_en) = YEAR(CURDATE()) AND MONTH(cg.creado_en) = MONTH(CURDATE())"; break;
}

// ✅ CORRECCIÓN EN LA CONSULTA SQL: Agregamos MAX(p.id) as pago_id
$sql = "
SELECT SQL_CALC_FOUND_ROWS
  cg.id,
  LPAD(cg.id, 6, '0')              AS folio,
  cg.periodo_inicio,
  cg.periodo_fin,
  cg.total,
  cg.estatus                      AS estatus_cargo,
  cg.creado_en                    AS creado_en,
  c.empresa                       AS cliente,
  GROUP_CONCAT(DISTINCT oi.concepto ORDER BY oi.id SEPARATOR '||') AS items_raw,
  COUNT(DISTINCT oi.id)           AS items_count,
  MAX(p.metodo)                   AS pago_metodo,
  MAX(p.referencia)               AS pago_ref,
  MAX(p.creado_en)                AS pago_fecha,
  MAX(p.id)                       AS pago_id  /* <--- ESTO FALTABA */
FROM cargos cg
JOIN ordenes o      ON o.id = cg.orden_id
JOIN clientes c     ON c.id = o.cliente_id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
LEFT JOIN pagos p        ON p.cargo_id = cg.id
{$where}
GROUP BY
  cg.id, cg.periodo_inicio, cg.periodo_fin,
  cg.total, cg.estatus, cg.creado_en, c.empresa
ORDER BY cg.periodo_inicio DESC
LIMIT $limit OFFSET $offset
";

$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$totalRows = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

function money_mx($v){ return '$'.number_format((float)$v, 2, '.', ','); }
function fmt_date($d){ return date('Y-m-d', strtotime($d)); }

function compress_paquete(string $itemsRaw, int $itemsCount, int $max = 2): string {
    if ($itemsCount <= 0 || $itemsRaw === '') return '—';
    $items = explode('||', $itemsRaw);
    $itemsCount = max($itemsCount, count($items));
    $preview = array_slice($items, 0, $max);
    $preview = array_map('trim', $preview);
    $extra = $itemsCount - count($preview);
    $txt = implode(' · ', $preview);
    if ($extra > 0) $txt .= " · +{$extra} más";
    return $txt;
}

function cobrosUrl(string $rangoValue, int $clienteId): string {
    $base = "/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobros&rango={$rangoValue}";
    if ($clienteId > 0) $base .= "&cliente_id={$clienteId}";
    return $base;
}
?>

<style>
  .cobros .topbar{gap:.5rem;}
  .cobros .filters .form-select, .cobros .filters .form-control{height:44px;}
  .cobros .filters .btn-go{ height:44px; display:inline-flex; align-items:center; background:#fdd835; border-color:#fdd835; color:#000; }
  .cobros .filters .btn-go:hover{filter:brightness(.95);}
  .cobros .card-header{font-weight:600;}
  .cobros table{width:100%;}
  .cobros th,.cobros td{white-space:nowrap; vertical-align:middle;}
  
  @media (max-width: 768px){
    .cobros thead{position:absolute; left:-9999px; top:-9999px;}
    .cobros table, .cobros tbody, .cobros tr, .cobros td{display:block; width:100%;}
    .cobros tr{ background:#fff; border:1px solid #e9ecef; border-radius:.5rem; padding:.5rem .75rem; margin-bottom:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .cobros td{ border:0; border-bottom:1px solid #f1f3f5; position:relative; padding:.5rem 0 .5rem 7.75rem; white-space:normal; text-align:right; }
    .cobros td:last-child{border-bottom:0;}
    .cobros td::before{ content:attr(data-label); position:absolute; left:.75rem; top:.5rem; width:6.8rem; font-weight:600; color:#6b7280; text-align:left; }
  }
</style>

<div class="container-fluid cobros">
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cobros</h3>
    <div class="dropdown">
         <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">Mostrar</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item <?= $rango === 'todos' ? 'active' : '' ?>" href="<?= cobrosUrl('todos', $clienteId) ?>">Todos</a></li>
            <li><a class="dropdown-item <?= $rango === 'hoy' ? 'active' : '' ?>" href="<?= cobrosUrl('hoy', $clienteId) ?>">Hoy</a></li>
            <li><a class="dropdown-item <?= $rango === '7dias' ? 'active' : '' ?>" href="<?= cobrosUrl('7dias', $clienteId) ?>">Últimos 7 días</a></li>
            <li><a class="dropdown-item <?= $rango === 'mes' ? 'active' : '' ?>" href="<?= cobrosUrl('mes', $clienteId) ?>">Este mes</a></li>
          </ul>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body filters">
      <form class="row g-2 align-items-center" method="get" action="/Sistema-de-Saldos-y-Pagos-/Public/index.php">
        <input type="hidden" name="m" value="cobros">
        <div class="col-12 col-md-6">
          <select name="cliente_id" class="form-select">
            <option value="">--Selecciona Cliente--</option>
            <?php foreach ($clientes as $cli): ?>
              <option value="<?= (int)$cli['id'] ?>" <?= $clienteId === (int)$cli['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cli['empresa']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-go" type="submit"><i class="bi bi-search me-1"></i> Buscar!</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">Historial de Cobros</div>
    <div class="table-wrap scroll-container">
      <table class="table align-middle mb-0" id="tblCobros">
        <thead class="table-light">
          <tr>
            <th>Folio</th><th>Cliente</th><th>Paquete</th><th>Fecha</th><th>Importe</th><th>Método</th><th>Ref.</th><th class="text-end">Estado</th><th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted">Aún no hay cobros registrados.</td></tr>
        <?php else: ?>
        <?php foreach ($rows as $r): 
          $paquete = compress_paquete($r['items_raw'] ?? '', (int)$r['items_count']);
          $fechaTxt = $r['pago_fecha'] ? fmt_date($r['pago_fecha']) : fmt_date($r['creado_en']);
          $estatus = $r['estatus_cargo'] ?? 'pendiente';
        ?>
        <tr>
          <td data-label="Folio"><a href="#" class="text-decoration-none"><?= htmlspecialchars($r['folio']) ?></a></td>
          <td data-label="Cliente" class="cli-name"><a href="#" class="text-decoration-none"><?= htmlspecialchars($r['cliente']) ?></a></td>
          <td data-label="Paquete"><?= htmlspecialchars($paquete) ?></td>
          <td data-label="Fecha"><?= htmlspecialchars($fechaTxt) ?></td>
          <td data-label="Importe"><?= money_mx($r['total']) ?></td>
          <td data-label="Método"><?= htmlspecialchars($r['pago_metodo'] ?: '—') ?></td>
          <td data-label="Ref"><?= htmlspecialchars($r['pago_ref'] ?: '—') ?></td>
          <td data-label="Estado" class="text-end">
            <span class="badge <?= $estatus === 'pagado' ? 'text-bg-success' : ($estatus === 'emitido' ? 'text-bg-info' : 'text-bg-warning') ?>">
              <?= htmlspecialchars($estatus) ?>
            </span>
          </td>
          <td class="text-end" data-label="Acciones">
            <?php if ($estatus === 'pagado'): ?>
                <a href="/Sistema-de-Saldos-y-Pagos-/Public/api/descargar_recibo.php?id=<?= $r['pago_id'] ?>" 
                   target="_blank" 
                   class="btn btn-sm btn-outline-danger" 
                   title="Descargar Recibo PDF">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <small class="text-muted">Mostrando <?= count($rows) ?> de <?= $totalRows ?> registros</small>
      <?php if ($totalPages > 1): ?>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?m=cobros&p=<?= $page - 1 ?>&cliente_id=<?= $clienteId ?>&rango=<?= $rango ?>">Anterior</a></li>
        <li class="page-item disabled"><span class="page-link"><?= $page ?></span></li>
        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>"><a class="page-link" href="?m=cobros&p=<?= $page + 1 ?>&cliente_id=<?= $clienteId ?>&rango=<?= $rango ?>">Siguiente</a></li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
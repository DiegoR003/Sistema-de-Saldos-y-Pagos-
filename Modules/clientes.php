<?php
// Modules/clientes.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';

$pdo = db();

$q = trim($_GET['q'] ?? '');
$where = "WHERE o.estado='activa'";
$args  = [];

if ($q !== '') {
  $where .= " AND (c.empresa LIKE ? OR c.correo LIKE ? OR c.telefono LIKE ?)";
  $searchTerm = "%{$q}%";
  $args[] = $searchTerm;
  $args[] = $searchTerm;
  $args[] = $searchTerm;
}

// --- PAGINACIÓN ---
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 5; // Cantidad de clientes por página
$offset = ($page - 1) * $limit;

$vista = $_GET['vista'] ?? 'todos';
$having = '';

if ($vista === 'activos') {
  $having = 'HAVING cargos_pagados > 0 AND cargos_pendientes = 0';
} elseif ($vista === 'pendientes') {
  $having = 'HAVING cargos_pendientes > 0';
}

$sql = "
SELECT SQL_CALC_FOUND_ROWS
  c.id, c.empresa, c.correo, c.telefono,
  MIN(o.creado_en) AS fecha_alta,
  SUM(CASE WHEN o.estado = 'activa' THEN 1 ELSE 0 END) AS ordenes_activas,
  COALESCE(SUM(CASE
      WHEN o.estado = 'activa' AND oi.billing_type = 'recurrente' AND oi.pausado = 0
      THEN oi.monto ELSE 0 END), 0) AS mensual_base,
  MIN(CASE WHEN o.estado = 'activa' THEN o.id END) AS orden_id,
  MIN(CASE WHEN o.estado = 'activa' THEN o.periodicidad END) AS periodicidad_activa,
  MIN(CASE WHEN o.estado = 'activa' THEN o.billing_policy END) AS billing_policy_activa,
  MIN(CASE WHEN o.estado = 'activa' THEN o.cut_day END) AS cut_day_activo,
  MIN(CASE WHEN o.estado = 'activa' THEN o.proxima_facturacion END) AS proxima_facturacion_activa
FROM clientes c
JOIN ordenes o ON o.cliente_id = c.id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
{$where}
GROUP BY c.id, c.empresa, c.correo, c.telefono
ORDER BY c.empresa ASC
LIMIT $limit OFFSET $offset
";

$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Contar total de registros para calcular páginas
$totalRows = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$totalPages = ceil($totalRows / $limit);

function money_mx($v): string { return '$' . number_format((float)$v, 2, '.', ','); }

function prettify_periodo(array $r): string {
    $per = $r['periodicidad_activa'] ?? '';
    $pol = $r['billing_policy_activa'] ?? '';
    $cut = $r['cut_day_activo'] ?? null;
    switch ($per) {
        case 'mensual': $base = 'Mensual'; break;
        case 'bimestral': $base = 'Bimestral'; break;
        case 'unico': $base = 'Único'; break;
        default: $base = '—'; break;
    }
    if ($base === '—') return $base;
    $detalle = '';
    if ($pol === 'fixed_day' && $cut) $detalle = "corte día " . (int)$cut;
    elseif ($pol === 'prepaid_anchor') $detalle = "prepago";
    return $detalle ? "{$base} ({$detalle})" : $base;
}

function fmt_date(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
}
?>

<style>
.clientes-page h3 { font-weight: 600; }
.clientes-page .search-card .card-body { padding: 1rem; }
.clientes-page .search-card .form-control { height: 44px; }
.clientes-page .search-card .btn-search {
  height: 44px; display: inline-flex; align-items: center;
  background: #e74c3c; border-color: #e74c3c;
}
.clientes-page .search-card .btn-search:hover { background: #d83e2e; }

.clientes-page .estado-badge {
  font-size: .75rem; font-weight: 600; border-radius: 999px; padding: .25rem .6rem;
}
.clientes-page .estado-activo { background:#28a745; color:#fff; }
.clientes-page .estado-pendiente { background:#ffc107; color:#212529; }

/* Cards móvil */
.clientes-page .cliente-card {
  display: none;
  background: white;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 0.75rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #667eea;
}
.clientes-page .cliente-card .nombre {
  font-weight: 700;
  font-size: 1.05rem;
  margin-bottom: 0.5rem;
  color: #1a202c;
}
.clientes-page .cliente-card .info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  border-bottom: 1px solid #f0f0f0;
  font-size: 0.9rem;
}
.clientes-page .cliente-card .info-row:last-of-type { border-bottom: none; }
.clientes-page .cliente-card .label { color: #6b7280; font-weight: 500; }
.clientes-page .cliente-card .value { font-weight: 600; text-align: right; }
.clientes-page .cliente-card .actions {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f0f0f0;
}

@media (max-width: 768px) {
  .clientes-page .table-wrapper { display: none; }
  .clientes-page .cliente-card { display: block; }
}
</style>

<div class="container-fluid clientes-page">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Clientes</h3>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <form class="input-group" action="/Sistema-de-Saldos-y-Pagos-/Public/index.php" method="get">
        <input type="hidden" name="m" value="clientes">
        <input type="text" class="form-control" name="q" placeholder="Nombre, correo o teléfono…" value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-danger btn-search" type="submit">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </form>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info mb-0">Aún no hay clientes con órdenes activas.</div>
  <?php else: ?>

  <!-- Tabla desktop -->
  <div class="card border-0 shadow-sm table-wrapper">
    <div class="card-body">
      <h5 class="mb-3">Clientes en Proceso</h5>
      <div class="table-responsive ">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Correo</th>
              <th>Teléfono</th>
              <th>Servicio</th>
              <th>Periodo</th>
              <th>Próx. fact.</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): 
            $activo = (int)$r['ordenes_activas'] > 0;
            $montoMensual = (float)$r['mensual_base'];
            $montoConIVA = $montoMensual * 1.16;
            $periodoTxt = prettify_periodo($r);
            $hasProxima = !empty($r['proxima_facturacion_activa']);
            $proximaTxt = fmt_date($r['proxima_facturacion_activa'] ?? null);
          ?>
            <tr>
              <td><?= htmlspecialchars($r['empresa']) ?></td>
              <td><?= htmlspecialchars($r['correo']) ?></td>
              <td><?= htmlspecialchars($r['telefono'] ?? '—') ?></td>
              <td><?= $montoMensual > 0 ? 'Mensual ' . money_mx($montoConIVA) : '—' ?></td>
              <td><?= htmlspecialchars($periodoTxt) ?></td>
              <td><?= htmlspecialchars($proximaTxt) ?></td>
              <td>
                <?php if ($activo): ?>
                  <?php if ($hasProxima): ?>
                    <span class="estado-badge estado-activo">Activo</span>
                  <?php else: ?>
                    <span class="estado-badge estado-pendiente">Pendiente</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="estado-badge">Sin orden</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if (!empty($r['orden_id'])): ?>
                  <a href="/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>" class="btn btn-primary btn-sm">Cobrar</a>
                <?php else: ?>
                  <span class="text-muted small">Sin orden</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

   <!-- Paginación -->
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4 pb-4">
    <ul class="pagination justify-content-center">
      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="?m=clientes&p=<?= $page - 1 ?>&q=<?= htmlspecialchars($q) ?>">
          Anterior
        </a>
      </li>
      
      <li class="page-item disabled">
        <span class="page-link text-muted">
          Página <?= $page ?> de <?= $totalPages ?> (Total: <?= $totalRows ?>)
        </span>
      </li>

      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
        <a class="page-link" href="?m=clientes&p=<?= $page + 1 ?>&q=<?= htmlspecialchars($q) ?>">
          Siguiente
        </a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>

  <!-- Cards móvil -->
  <div>
    <?php foreach ($rows as $r): 
      $activo = (int)$r['ordenes_activas'] > 0;
      $montoMensual = (float)$r['mensual_base'];
      $montoConIVA = $montoMensual * 1.16;
      $hasProxima = !empty($r['proxima_facturacion_activa']);
    ?>
      <div class="cliente-card">
        <div class="nombre"><?= htmlspecialchars($r['empresa']) ?></div>
        
        <div class="info-row">
          <span class="label">Correo:</span>
          <span class="value"><?= htmlspecialchars($r['correo']) ?></span>
        </div>
        
        <div class="info-row">
          <span class="label">Servicio:</span>
          <span class="value"><?= $montoMensual > 0 ? money_mx($montoConIVA) : '—' ?></span>
        </div>
        
        <div class="info-row">
          <span class="label">Estado:</span>
          <span class="value">
            <?php if ($activo): ?>
              <?php if ($hasProxima): ?>
                <span class="estado-badge estado-activo">Activo</span>
              <?php else: ?>
                <span class="estado-badge estado-pendiente">Pendiente</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="estado-badge">Sin orden</span>
            <?php endif; ?>
          </span>
        </div>
        
        <?php if (!empty($r['orden_id'])): ?>
        <div class="actions d-grid">
          <a href="/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-cash-coin me-1"></i> Cobrar
          </a>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>
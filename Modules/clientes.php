<?php
// Modules/clientes.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';

$pdo = db();

/* =========================
   Búsqueda
   ========================= */
$q = trim($_GET['q'] ?? '');

$where = "WHERE o.estado = 'activa'";
$args  = [];

if ($q !== '') {
    // un solo named param :q
    $where .= " AND (c.empresa LIKE :q OR c.correo LIKE :q OR c.telefono LIKE :q)";
    $args[':q'] = "%{$q}%";
}

/* =========================
   Consulta: clientes con órdenes activas
   ========================= */
/*
   Tomamos:
   - ordenes_activas: cuántas órdenes activas tiene el cliente
   - mensual_base: suma de los montos recurrentes no pausados (estimado mensual)
   - orden_id: alguna orden activa representativa (MIN id)
   - periodicidad_activa: periodicidad de esa orden (unico/mensual/bimestral)
   - billing_policy_activa: prepaid_anchor / fixed_day
   - cut_day_activo: día de corte si aplica
   - proxima_facturacion_activa: próxima fecha de facturación
*/
$sql = "
SELECT
  c.id,
  c.empresa,
  c.correo,
  c.telefono,
  MIN(o.creado_en) AS fecha_alta,
  SUM(CASE WHEN o.estado = 'activa' THEN 1 ELSE 0 END) AS ordenes_activas,

  COALESCE(SUM(CASE
      WHEN o.estado = 'activa'
           AND oi.billing_type = 'recurrente'
           AND oi.pausado = 0
      THEN oi.monto
      ELSE 0
  END), 0) AS mensual_base,

  MIN(CASE WHEN o.estado = 'activa' THEN o.id END)                      AS orden_id,
  MIN(CASE WHEN o.estado = 'activa' THEN o.periodicidad END)            AS periodicidad_activa,
  MIN(CASE WHEN o.estado = 'activa' THEN o.billing_policy END)          AS billing_policy_activa,
  MIN(CASE WHEN o.estado = 'activa' THEN o.cut_day END)                 AS cut_day_activo,

  -- AQUÍ el cambio importante:
  MIN(
    CASE
      WHEN o.estado = 'activa'
           AND oi.billing_type = 'recurrente'
           AND oi.pausado = 0
      THEN oi.next_run
    END
  ) AS proxima_facturacion_activa

FROM clientes c
JOIN ordenes o         ON o.cliente_id = c.id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
{$where}
GROUP BY c.id, c.empresa, c.correo, c.telefono
ORDER BY c.empresa ASC
";


$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function money_mx($v): string {
    return '$' . number_format((float)$v, 2, '.', ',');
}

/**
 * Periodo de facturación bonito
 */
function prettify_periodo(array $r): string {
    $per  = $r['periodicidad_activa'] ?? '';
    $pol  = $r['billing_policy_activa'] ?? '';
    $cut  = $r['cut_day_activo'] ?? null;

    switch ($per) {
        case 'mensual':   $base = 'Mensual';   break;
        case 'bimestral': $base = 'Bimestral'; break;
        case 'unico':     $base = 'Único';     break;
        default:          $base = '—';         break;
    }

    if ($base === '—') return $base;

    // detalles de política de facturación
    $detalle = '';
    if ($pol === 'fixed_day' && $cut) {
        $detalle = "corte día " . (int)$cut;
    } elseif ($pol === 'prepaid_anchor') {
        $detalle = "prepago";
    }

    return $detalle ? "{$base} ({$detalle})" : $base;
}

/**
 * Formato corto para fecha
 */
function fmt_date(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return '—';
    return date('d/m/Y', $ts);
}
?>

<style>
.clientes-page h3 {
  font-weight: 600;
}

.clientes-page .search-card .card-body { padding: 1rem; }
.clientes-page .search-card .form-control { height: 44px; }
.clientes-page .search-card .btn-search {
  height: 44px;
  display: inline-flex;
  align-items: center;
  background: #e74c3c;
  border-color: #e74c3c;
}
.clientes-page .search-card .btn-search:hover {
  background: #d83e2e;
  border-color: #d83e2e;
}

.clientes-page .estado-badge {
  font-size: .75rem;
  font-weight: 600;
  border-radius: 999px;
  padding: .15rem .6rem;
}

.clientes-page .estado-activo {
  background:#28a745;
  color:#fff;
}

.clientes-page .estado-sin-orden {
  background:#6c757d;
  color:#fff;
}
</style>

<div class="container-fluid clientes-page">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Clientes</h3>

    <div class="dropdown">
      <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Mostrar
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="?m=clientes">Todos</a></li>
      </ul>
    </div>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <form class="input-group" action="/Sistema-de-Saldos-y-Pagos-/Public/index.php" method="get">
        <input type="hidden" name="m" value="clientes">
        <input type="text"
               class="form-control"
               name="q"
               placeholder="Nombre, correo o teléfono…"
               value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-danger btn-search" type="submit">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </form>
    </div>
  </div>

  <!-- Tabla de clientes en proceso -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h5 class="mb-3"> Clientes en Proceso</h5>

      <?php if (!$rows): ?>
        <div class="alert alert-info mb-0">
          Aún no hay clientes con órdenes activas (o no hay coincidencias con la búsqueda).
        </div>
      <?php else: ?>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
  <tr>
    <th>Cliente</th>
    <th class="d-none d-lg-table-cell">Correo</th>
    <th class="d-none d-md-table-cell">Teléfono</th>
    <th>Servicio</th>
    <th class="d-none d-lg-table-cell">Periodo</th>
    <th class="d-none d-md-table-cell">Próx. facturación</th>
    <th>Estado</th>
    <th class="text-end">Acciones</th>
  </tr>
</thead>

          <tbody>
<?php foreach ($rows as $r): 
  $activo        = (int)$r['ordenes_activas'] > 0;
  $montoMensual  = (float)$r['mensual_base'];
  $periodoTxt    = prettify_periodo($r);
  $proximaTxt    = fmt_date($r['proxima_facturacion_activa'] ?? null);
?>
  <tr>
    <td><?= htmlspecialchars($r['empresa']) ?></td>

    <td class="d-none d-lg-table-cell">
      <?= htmlspecialchars($r['correo']) ?>
    </td>

    <td class="d-none d-md-table-cell">
      <?= htmlspecialchars($r['telefono'] ?? '—') ?>
    </td>

    <td>
      <?php if ($montoMensual > 0): ?>
        Mensual <?= money_mx($montoMensual) ?>
      <?php else: ?>
        —
      <?php endif; ?>
    </td>

    <td class="d-none d-lg-table-cell">
      <?= htmlspecialchars($periodoTxt) ?>
    </td>

    <td class="d-none d-md-table-cell">
      <?= htmlspecialchars($proximaTxt) ?>
    </td>

    <td>
      <?php if ($activo): ?>
        <span class="estado-badge estado-activo">Activo</span>
      <?php else: ?>
        <span class="estado-badge estado-sin-orden">Sin orden activa</span>
      <?php endif; ?>
    </td>

    <td class="text-end">
      <?php if (!empty($r['orden_id'])): ?>
        <a href="/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>"
           class="btn btn-primary btn-sm">
          Cobrar
        </a>
      <?php else: ?>
        <span class="text-muted small">Sin orden activa</span>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>

        </table>
      </div>

      <?php endif; ?>
    </div>
  </div>

</div>

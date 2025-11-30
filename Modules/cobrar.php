<?php
// Modules/cobrar.php
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/date_utils.php'; // define end_by_interval()
$pdo = db();

// --- Filtros / búsqueda ---
$q = trim($_GET['q'] ?? '');
$params = [];
$where = "WHERE o.estado='activa'";

if ($q !== '') {
  $where .= " AND (c.empresa LIKE ? OR c.correo LIKE ? OR c.telefono LIKE ?)";
  $searchTerm = "%{$q}%";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
}


  // --- Filtro Mostrar --- 
$vista = $_GET['vista'] ?? 'todos';
$having = '';

if ($vista === 'pagados') {
  // órdenes que tienen cargos y todos están pagados
  $having = 'HAVING cargos_pagados > 0 AND cargos_pendientes = 0';
} elseif ($vista === 'vencidos') {
  // órdenes con al menos un cargo pendiente
  // (si quieres ser más estricto, luego podemos meter la fecha de proxima_facturacion)
  $having = 'HAVING cargos_pendientes > 0';
}


// --- Consulta: órdenes activas + cliente + suma mensual base (items recurrentes no pausados) ---
$sql = "
SELECT
  o.id                                                AS orden_id,
  ANY_VALUE(o.estado)                                 AS estado,
  ANY_VALUE(o.proxima_facturacion)                    AS proxima_facturacion,
  ANY_VALUE(o.billing_policy)                         AS billing_policy,
  ANY_VALUE(o.rfc_id)                                 AS rfc_id,

  ANY_VALUE(c.id)                                     AS cliente_id,
  ANY_VALUE(c.empresa)                                AS empresa,
  ANY_VALUE(c.correo)                                 AS correo,
  ANY_VALUE(c.telefono)                               AS telefono,

  COALESCE(SUM(CASE
    WHEN oi.billing_type='recurrente' AND oi.pausado=0 THEN oi.monto
    ELSE 0 END),0)                                    AS mensual_base,

  COALESCE(SUM(CASE
    WHEN cg.estatus='pagado' THEN 1
    ELSE 0 END),0)                                    AS cargos_pagados,

  COALESCE(SUM(CASE
    WHEN cg.id IS NOT NULL AND cg.estatus <> 'pagado' THEN 1
    ELSE 0 END),0)                                    AS cargos_pendientes

FROM ordenes o
JOIN clientes c          ON c.id = o.cliente_id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
LEFT JOIN cargos cg      ON cg.orden_id = o.id
{$where}
GROUP BY o.id
{$having}
ORDER BY ANY_VALUE(c.empresa) ASC
LIMIT 200
";



$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// helpers
function money_mx($v){ return '$'.number_format((float)$v,2,'.',','); }

/**
 * Devuelve un resumen corto de hasta $limit servicios activos (recurrentes)
 * para una orden. Ej: "cuenta ($1,575) · publicaciones ($2,363)  +1 más"
 */
function servicios_resumen(PDO $pdo, int $ordenId, int $limit = 2): string {
  // Trae solo los que pintan el mes (recurrentes y no pausados)
  $st = $pdo->prepare("
    SELECT concepto, monto
    FROM orden_items
    WHERE orden_id=? AND pausado=0 AND billing_type='recurrente'
    ORDER BY id
    LIMIT ".(int)$limit."
  ");
  $st->execute([$ordenId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$rows) return '—';

  $trozos = [];
  foreach ($rows as $r) {
    $nom = trim($r['concepto'] ?? 'Servicio');
    $trozos[] = "{$nom} (".money_mx((float)$r['monto']).")";
  }

  // ¿Hay más de los que se mostraron?
  $st2 = $pdo->prepare("
    SELECT COUNT(*) FROM orden_items
    WHERE orden_id=? AND pausado=0 AND billing_type='recurrente'
  ");
  $st2->execute([$ordenId]);
  $total = (int)$st2->fetchColumn();
  if ($total > $limit) {
    $trozos[] = "+".($total - $limit)." más";
  }

  return implode(' · ', $trozos);
}


// Fechas para “Emitir/Notificar mes actual”
$hoy      = new DateTimeImmutable('today');
$y        = (int)$hoy->format('Y');
$m        = (int)$hoy->format('m');
$iniMesDT = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m));
$finMesDT = end_by_interval($iniMesDT,'mensual',1);
$iniMes   = $iniMesDT->format('Y-m-d');
$finMes   = $finMesDT->format('Y-m-d');
?>
<style>
/* ===== Cobrar (ligero) ===== */
.cobrar .search-card .card-body{padding:1rem}
.cobrar .search-card .form-control{height:44px}
.cobrar .search-card .btn-search{height:44px;display:inline-flex;align-items:center;background:#e74c3c;border-color:#e74c3c}
.cobrar .search-card .btn-search:hover{background:#d83e2e;border-color:#d83e2e}
.cobrar .cliente-card .card-body{padding:1.25rem}
.cobrar .cliente-card .link-primary{font-weight:600}
.cobrar .cliente-card .badge{font-weight:600;font-size:.75rem}
</style>

<div class="container-fluid cobrar">
  <div class="d-flex align-items-center justify-content-between topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cobrar <span class="text-muted fs-6">Control panel</span></h3>


    <div class="dropdown">
      <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
    Mostrar
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li>
      <a class="dropdown-item <?= $vista==='todos' ? 'active' : '' ?>"
         href="?m=cobrar&vista=todos">Todos</a>
    </li>
    <li>
      <a class="dropdown-item <?= $vista==='vencidos' ? 'active' : '' ?>"
         href="?m=cobrar&vista=vencidos">Solo vencidos</a>
    </li>
    <li>
      <a class="dropdown-item <?= $vista==='pagados' ? 'active' : '' ?>"
         href="?m=cobrar&vista=pagados">Pagados</a>
    </li>
  </ul>
    </div>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <form class="input-group" method="get" action="/Sistema-de-Saldos-y-Pagos-/Public/index.php">
        <input type="hidden" name="m" value="cobrar">
        <input type="text" class="form-control" name="q" placeholder="Buscar por nombre, correo o teléfono…" value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-danger btn-search" type="submit">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </form>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info">No hay órdenes activas (o no hay coincidencias con la búsqueda).</div>
  <?php endif; ?>

    <!-- Listado de clientes / órdenes -->
  <div class="vstack gap-3">
    <?php foreach ($rows as $r):
      // total mensual estimado con IVA (sólo para mostrar en lista)
      $mensualConIVA     = round((float)$r['mensual_base'] * 1.16, 2);
      $resumenServicios  = servicios_resumen($pdo, (int)$r['orden_id'], 2);

      // Estado de pago según cargos (pagados / pendientes)
      $pagados    = (int)$r['cargos_pagados'];
      $pendientes = (int)$r['cargos_pendientes'];

      $badgeTxt   = 'Nuevo';
      $badgeClass = 'secondary';

      if ($pagados === 0 && $pendientes === 0) {
        $badgeTxt   = 'Nuevo';
        $badgeClass = 'secondary';
      } elseif ($pendientes === 0 && $pagados > 0) {
        $badgeTxt   = 'Al corriente';
        $badgeClass = 'success';
      } else {
        $badgeTxt   = 'En servicio';
        $badgeClass = 'primary';
      }
    ?>

      <div class="card border-0 shadow-sm cliente-card">
        <div class="card-body">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center">
            <div class="me-lg-3">
              <a class="h5 link-primary d-inline-block mb-2"
                href="/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>">
                <?= htmlspecialchars($r['empresa']) ?>
              </a>
              <div class="text-muted">
                <div>Servicios: <?= htmlspecialchars($resumenServicios) ?></div>
                <div>Mensual: <span class="fw-semibold"><?= money_mx($mensualConIVA) ?></span></div>
              </div>
              <div class="mt-1">
                Estado de pago:
                <span class="badge rounded-pill bg-<?= $badgeClass ?>"><?= $badgeTxt ?></span>
                <?php if (!empty($r['proxima_facturacion'])): ?>
                  <span class="small text-muted ms-2">
                    Próx. fact.: <?= htmlspecialchars($r['proxima_facturacion']) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="d-flex gap-1 mt-2 mt-lg-0">
              <!-- Botón 1: Ver cobro (detalle mes a mes) -->
              <a class="btn btn-outline-primary"
                href="/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php?m=cobro&orden_id=<?= (int)$r['orden_id'] ?>">
                <i class="bi bi-receipt"></i> Ver cobro
              </a>

              <!-- Botón 2: Emitir / Notificar (mes actual) -->
              <form method="post"
                    action="/Sistema-de-Saldos-y-Pagos-/Public/api/cargos_emitir.php"
                    onsubmit="return confirm('¿Emitir/actualizar el cargo de este mes y notificar al cliente?');">
                <input type="hidden" name="orden_id" value="<?= (int)$r['orden_id'] ?>">
                <input type="hidden" name="periodo_inicio" value="<?= $iniMes ?>">
                <input type="hidden" name="periodo_fin" value="<?= $finMes ?>">
                <input type="hidden" name="periodo_mes" value="<?= (int)$m ?>">
                <button class="btn btn-warning">
                  <i class="bi bi-megaphone"></i> Emitir / Notificar
                </button>
              </form>
            </div> <!-- /acciones -->
          </div>   <!-- /d-flex -->
        </div>     <!-- /card-body -->
      </div>       <!-- /card -->

    <?php endforeach; ?>
  </div> <!-- /vstack -->

  
</div>

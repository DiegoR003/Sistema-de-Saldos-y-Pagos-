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
   Consulta de cobros
   ========================= */
$where = "WHERE 1=1";
$args  = [];

if ($clienteId > 0) {
    $where .= " AND c.id = :cid";
    $args[':cid'] = $clienteId;
}

$sql = "
SELECT
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
  MAX(p.creado_en)                AS pago_fecha
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
";




$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function money_mx($v){ return '$'.number_format((float)$v, 2, '.', ','); }
function fmt_date($d){ return date('Y-m-d', strtotime($d)); }

/**
 * Comprime la lista de servicios tipo:
 *   "cuenta - 1575, publicaciones - 2363, ..." -> "cuenta - 1575 · publicaciones - 2363 · +9 más"
 */
function compress_paquete(string $itemsRaw, int $itemsCount, int $max = 2): string {
    if ($itemsCount <= 0 || $itemsRaw === '') {
        return '—';
    }

    // reconstruimos la lista desde el GROUP_CONCAT
    $items = explode('||', $itemsRaw);

    // por si acaso hay diferencia entre COUNT y el explode
    $itemsCount = max($itemsCount, count($items));

    // tomamos solo los primeros $max
    $preview = array_slice($items, 0, $max);

    // limpiamos espacios
    $preview = array_map('trim', $preview);

    $extra = $itemsCount - count($preview);

    $txt = implode(' · ', $preview);
    if ($extra > 0) {
        $txt .= " · +{$extra} más";
    }
    return $txt;
}
?>

<style>
  /* ===== Cobros (mismo estilo que tenías) ===== */
  .cobros .topbar{gap:.5rem;}
  .cobros .filters .form-select,
  .cobros .filters .form-control{height:44px;}
  .cobros .filters .btn-go{
    height:44px; display:inline-flex; align-items:center;
    background:#fdd835; border-color:#fdd835; color:#000000;
  }
  .cobros .filters .btn-go:hover{filter:brightness(.95);}
  .cobros .card-header{font-weight:600;}

  /* Acciones */
  .cobros .btn-cancel{background:#dc3545; border-color:#dc3545; color:#fff;}
  .cobros .btn-cancel:hover{filter:brightness(.95);}

  /* Tabla desktop */
  .cobros table{width:100%;}
  .cobros th,.cobros td{white-space:nowrap; vertical-align:middle;}

  /* ===== Versión móvil: filas apiladas como tarjetas ===== */
  @media (max-width: 768px){
    .cobros thead{position:absolute; left:-9999px; top:-9999px;}

    .cobros table,
    .cobros tbody,
    .cobros tr,
    .cobros td{display:block; width:100%;}
    .cobros tbody{display:block;}

    .cobros tr{
      background:#fff; border:1px solid #e9ecef; border-radius:.5rem;
      padding:.5rem .75rem; margin-bottom:.75rem;
      box-shadow:0 1px 3px rgba(0,0,0,.04);
    }

    .cobros td{
      border:0; border-bottom:1px solid #f1f3f5;
      position:relative; padding:.5rem 0 .5rem 7.75rem;
      white-space:normal; text-align:right;
    }
    .cobros td:last-child{border-bottom:0;}

    .cobros td::before{
      content:attr(data-label);
      position:absolute; left:.75rem; top:.5rem; width:6.8rem;
      font-weight:600; color:#6b7280; text-align:left; white-space:normal;
    }

    .cobros .table-wrap{overflow:visible;}
    .cobros .text-end{justify-content:flex-end;}
  }
</style>

<div class="container-fluid cobros">
  <!-- Título + acciones -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cobros</h3>

    <div class="d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">
          Mostrar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#">Hoy</a></li>
          <li><a class="dropdown-item" href="#">Últimos 7 días</a></li>
          <li><a class="dropdown-item" href="#">Este mes</a></li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body filters">
      <form class="row g-2 align-items-center"
            method="get"
            action="/Sistema-de-Saldos-y-Pagos-/Public/index.php">
        <input type="hidden" name="m" value="cobros">

        <div class="col-12 col-md-6">
          <select name="cliente_id" class="form-select">
            <option value="">--Selecciona Cliente--</option>
            <?php foreach ($clientes as $cli): ?>
              <option value="<?= (int)$cli['id'] ?>"
                <?= $clienteId === (int)$cli['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cli['empresa']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-go" type="submit">
            <i class="bi bi-search me-1"></i> Buscar!
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de cobros -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
      Historial de Cobros
    </div>

    <div class="table-wrap">
      <table class="table align-middle mb-0" id="tblCobros">
        <thead class="table-light">
          <tr>
            <th>Folio</th>
            <th>Cliente</th>
            <th>Paquete</th>
            <th>Fecha</th>
            <th>Importe</th>
            <th>Método de pago</th>
            <th># Depósito</th>
            <th class="text-end">Estado</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="8" class="text-center text-muted">
              Aún no hay cobros registrados.
            </td>
          </tr>
        <?php else: ?>
        <?php foreach ($rows as $r): 
  $paquete   = compress_paquete($r['items_raw'] ?? '', (int)$r['items_count']);
  $fechaTxt  = $r['pago_fecha'] ? fmt_date($r['pago_fecha']) : fmt_date($r['creado_en']);
  $importe   = money_mx($r['total']);
  $metodo    = $r['pago_metodo'] ?: '—';
  $ref       = $r['pago_ref'] ?: '—';

  $estatus   = $r['estatus_cargo'] ?? 'pendiente'; // emitido / pagado / pendiente
?>
<tr>
  <td data-label="Folio">
    <a href="#" class="text-decoration-none"><?= htmlspecialchars($r['folio']) ?></a>
  </td>
  <td data-label="Cliente" class="cli-name">
    <a href="#" class="text-decoration-none"><?= htmlspecialchars($r['cliente']) ?></a>
  </td>
  <td data-label="Paquete">
    <?= htmlspecialchars($paquete) ?>
  </td>
  <td data-label="Fecha"><?= htmlspecialchars($fechaTxt) ?></td>
  <td data-label="Importe"><?= $importe ?></td>
  <td data-label="Método de pago"><?= htmlspecialchars($metodo) ?></td>
  <td data-label="# Depósito"><?= htmlspecialchars($ref) ?></td>
  <td data-label="Estado" class="text-end">
    <span class="badge 
      <?= $estatus === 'pagado' ? 'text-bg-success' : ($estatus === 'emitido' ? 'text-bg-info' : 'text-bg-warning') ?>">
      <?= htmlspecialchars($estatus) ?>
    </span>
  </td>
</tr>
<?php endforeach; ?>


        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación simple (decorativa por ahora) -->
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <?php $total = count($rows); ?>
      <small class="text-muted" id="lblCount">
        Mostrando <?= $total ? "1 a {$total} de {$total}" : "0 de 0" ?> registros
      </small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>
</div>

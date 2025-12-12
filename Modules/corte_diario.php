<?php
// Modules/corte_diario.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';

$pdo = db();

/* =========================
   Helpers de fecha
   ========================= */
function parse_fecha_input(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') return null;

    // Formato dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    // Asumimos que viene yyyy-mm-dd (input type=date)
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    return null;
}

function fmt_fecha_humano(?string $sqlDateTime): string {
    if (!$sqlDateTime) return '—';
    $ts = strtotime($sqlDateTime);
    if (!$ts) return '—';
    return date('d/m/Y H:i', $ts);
}

function fmt_fecha_corta(?string $sqlDate): string {
    if (!$sqlDate) return '';
    $ts = strtotime($sqlDate);
    if (!$ts) return '';
    return date('d/m/Y', $ts);
}

function money_mx($v): string {
    return '$' . number_format((float)$v, 2, '.', ',');
}

/**
 * Comprime la lista de servicios tipo:
 *   "cuenta - 1575||publicaciones - 2363||..." -> "cuenta - 1575 · publicaciones - 2363 · +9 más"
 */
function compress_paquete(string $itemsRaw, int $itemsCount, int $max = 2): string {
    if ($itemsCount <= 0 || $itemsRaw === '') {
        return '—';
    }

    $items = explode('||', $itemsRaw);
    $itemsCount = max($itemsCount, count($items));

    $preview = array_slice($items, 0, $max);
    $preview = array_map('trim', $preview);

    $extra = $itemsCount - count($preview);

    $txt = implode(' · ', $preview);
    if ($extra > 0) {
        $txt .= " · +{$extra} más";
    }
    return $txt;
}

/* =========================
   Filtros
   ========================= */

// Fecha del corte (un solo día). Por defecto hoy.
$fechaInput  = $_GET['fecha'] ?? date('Y-m-d');
$fechaSql    = parse_fecha_input($fechaInput) ?? date('Y-m-d');
$fechaLabel  = fmt_fecha_corta($fechaSql);

// Filtro opcional por nombre de cliente
$clienteBusq = trim($_GET['cliente'] ?? '');

/* =========================
   Consulta
   =========================
   Traemos los PAGOS registrados ese día,
   junto con info del cliente, cargo e items.
*/
$where  = "WHERE DATE(p.creado_en) = :f";
$params = [':f' => $fechaSql];

if ($clienteBusq !== '') {
    $where .= " AND c.empresa LIKE :cli";
    $params[':cli'] = "%{$clienteBusq}%";
}

$sql = "
SELECT
    p.id                                 AS pago_id,
    LPAD(cg.id, 6, '0')                  AS folio,
    c.empresa                            AS cliente,
    p.creado_en                          AS fecha_pago,
    p.monto                              AS importe,
    p.metodo                             AS metodo,
    p.referencia                         AS referencia,

    GROUP_CONCAT(DISTINCT oi.concepto
                 ORDER BY oi.id
                 SEPARATOR '||')         AS items_raw,
    COUNT(DISTINCT oi.id)                AS items_count
FROM pagos p
JOIN cargos cg        ON cg.id = p.cargo_id
JOIN ordenes o        ON o.id = cg.orden_id
JOIN clientes c       ON c.id = o.cliente_id
LEFT JOIN orden_items oi ON oi.orden_id = o.id
{$where}
GROUP BY
    p.id, cg.id, c.empresa, p.creado_en, p.monto, p.metodo, p.referencia
ORDER BY p.creado_en DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Totales del corte
   ========================= */
$transacciones = count($rows);
$totalCobrado  = 0.0;
$efectivo      = 0.0;

foreach ($rows as $r) {
    $totalCobrado += (float)$r['importe'];
    if (($r['metodo'] ?? '') === 'efectivo') {
        $efectivo += (float)$r['importe'];
    }
}
$noEfectivo = $totalCobrado - $efectivo;


$qsExport = http_build_query([
              'fecha' => $fechaSql, // Usamos la variable ya procesada en tu archivo
              'cliente' => $clienteBusq
          ]);
?>

<style>
.corte-diario .filters .form-control,
.corte-diario .filters .btn-go{
  height:44px;
}
.corte-diario .filters .btn-go{
  display:inline-flex;align-items:center;
  background:#fdd835;border-color:#fdd835;color:#000;
}
.corte-diario .filters .btn-go:hover{filter:brightness(.95);}
.corte-diario .card-header{font-weight:600;}

/* Cards móvil para corte diario */
.corte-diario .cobro-card {
  display: none;
  background: white;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 0.75rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #fdd835;
}
.corte-diario .cobro-card .folio {
  font-weight: 700;
  font-size: 1.05rem;
  margin-bottom: 0.5rem;
  color: #1a202c;
}
.corte-diario .cobro-card .info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  border-bottom: 1px solid #f0f0f0;
  font-size: 0.9rem;
}
.corte-diario .cobro-card .info-row:last-child { border-bottom: none; }
.corte-diario .cobro-card .label { color: #6b7280; font-weight: 500; }
.corte-diario .cobro-card .value { font-weight: 600; text-align: right; }

@media (max-width: 768px) {
  .corte-diario .table-wrap { display: none; }
  .corte-diario .cobro-card { display: block; }
  .corte-diario .filters .row { flex-direction: column; }
  .corte-diario .filters .col-12 { width: 100% !important; max-width: 100% !important; }
  .corte-diario .filters .btn-go { margin-top: 0.5rem; }
}
</style>

<div class="container-fluid corte-diario">
  <div class="d-flex align-items-center justify-content-between topbar mb-3">
    <h3 class="mb-0 fw-semibold">Corte Diario <span class="text-muted fs-6">Control panel</span></h3>

    <div class="d-flex align-items-center gap-2">
      <div class="dropdown me-2">
        <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
          Mostrar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text text-muted">Solo informativo</span></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="?m=corte_diario">Hoy</a></li>
        </ul>
      </div>

      <a class="btn btn-outline-danger btn-sm" target="_blank"
         href="/Sistema-de-Saldos-y-Pagos-/Public/api/corte_diario_export_pdf.php?<?= $qsExport ?>">
         <i class="bi bi-file-pdf me-1"></i> Exportar PDF
      </a>
      <a class="btn btn-outline-success btn-sm" target="_blank"
         href="/Sistema-de-Saldos-y-Pagos-/Public/api/corte_diario_export_excel.php?<?= $qsExport ?>">
         <i class="bi bi-file-excel me-1"></i> Exportar Excel
      </a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body filters">
      <form class="row g-2 align-items-center"
            method="get"
            action="/Sistema-de-Saldos-y-Pagos-/Public/index.php">
        <input type="hidden" name="m" value="corte_diario">

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Fecha</label>
          <input type="date"
                 name="fecha"
                 class="form-control"
                 value="<?= htmlspecialchars($fechaSql) ?>">
        </div>

        <div class="col-12 col-md-5">
          <label class="form-label mb-1">Cliente</label>
          <input type="text"
                 name="cliente"
                 class="form-control"
                 placeholder="Nombre del cliente..."
                 value="<?= htmlspecialchars($clienteBusq) ?>">
        </div>

        <div class="col-12 col-md-2 d-grid mt-4 mt-md-4">
          <button class="btn btn-go" type="submit">
            <i class="bi bi-calculator me-1"></i> Generar corte
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Historial de cobros del día -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
      Historial de Cobros del <?= htmlspecialchars($fechaLabel) ?>
    </div>

    <div class="table-wrap">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Folio</th>
            <th>Cliente</th>
            <th>Servicio</th>
            <th>Fecha</th>
            <th>Importe</th>
            <th>Método de pago</th>
            <th># Depósito</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="7" class="text-center text-muted">
              No hay cobros registrados para esta fecha.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r):
            $paquete = compress_paquete($r['items_raw'] ?? '', (int)$r['items_count']);
          ?>
            <tr>
              <td><?= htmlspecialchars($r['folio']) ?></td>
              <td><?= htmlspecialchars($r['cliente']) ?></td>
              <td><?= htmlspecialchars($paquete) ?></td>
              <td><?= htmlspecialchars(fmt_fecha_humano($r['fecha_pago'])) ?></td>
              <td><?= money_mx($r['importe']) ?></td>
              <td><?= htmlspecialchars(strtoupper($r['metodo'] ?? '—')) ?></td>
              <td><?= htmlspecialchars($r['referencia'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Cards móvil -->
<div>
  <?php foreach ($rows as $r): ?>
    <div class="cobro-card">
      <div class="folio">Folio: <?= htmlspecialchars($r['folio']) ?></div>
      
      <div class="info-row">
        <span class="label">Cliente:</span>
        <span class="value"><?= htmlspecialchars($r['cliente']) ?></span>
      </div>
      
      <div class="info-row">
        <span class="label">Importe:</span>
        <span class="value"><?= money_mx($r['importe']) ?></span>
      </div>
      
      <div class="info-row">
        <span class="label">Método:</span>
        <span class="value"><?= htmlspecialchars(strtoupper($r['metodo'] ?? '—')) ?></span>
      </div>
      
      <div class="info-row">
        <span class="label">Fecha:</span>
        <span class="value"><?= htmlspecialchars(fmt_fecha_humano($r['fecha_pago'])) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

  <!-- Resumen del corte -->
  <div class="row g-3">
    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Transacciones</div>
          <div class="fs-4 fw-semibold"><?= (int)$transacciones ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total cobrado</div>
          <div class="fs-4 fw-semibold"><?= money_mx($totalCobrado) ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Efectivo</div>
          <div class="fs-4 fw-semibold"><?= money_mx($efectivo) ?></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">No efectivo</div>
          <div class="fs-4 fw-semibold"><?= money_mx($noEfectivo) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

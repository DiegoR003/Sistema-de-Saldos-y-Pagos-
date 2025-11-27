<?php
// Modules/cobro.php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';

// Usa la utilidad si existe; si no, define un fallback seguro
if (file_exists(__DIR__ . '/../App/date_utils.php')) {
  require_once __DIR__ . '/../App/date_utils.php';
}
if (!function_exists('end_by_interval')) {
  function end_by_interval(DateTimeImmutable $start, string $unit, int $count): DateTimeImmutable {
    if ($unit === 'anual') return $start->modify('+1 year')->modify('-1 day');
    $count = max(1, (int)$count);
    return $start->modify("+{$count} month")->modify('-1 day');
  }
}

$pdo = db();

/* =========================
   Parámetros / constantes
   ========================= */
$ordenId = (int)($_GET['orden_id'] ?? 0);
if ($ordenId <= 0) { http_response_code(400); exit('Falta orden_id válido'); }

$anio = (int)($_GET['y'] ?? date('Y'));
if ($anio < 2000) $anio = (int)date('Y');

$MESN = [
  1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
  7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
];

function money_mx($v){ return '$'.number_format((float)$v, 2, '.', ','); }

/* =========================
   Datos
   ========================= */
// Orden + cliente
$st = $pdo->prepare("
  SELECT o.*, c.empresa, c.correo, c.telefono
  FROM ordenes o
  JOIN clientes c ON c.id=o.cliente_id
  WHERE o.id = ?
");
$st->execute([$ordenId]);
$orden = $st->fetch(PDO::FETCH_ASSOC);
if (!$orden) { http_response_code(404); exit('Orden no encontrada'); }

// Items activos (recurrentes o una_vez sin end_at)
$st = $pdo->prepare("
  SELECT *
  FROM orden_items
  WHERE orden_id = ? AND pausado = 0
    AND (billing_type='recurrente' OR (billing_type='una_vez' AND end_at IS NULL))
  ORDER BY id
");
$st->execute([$ordenId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

// Estimado con IVA (si no hay cargo del mes)
$subtotal = 0.0;
foreach ($items as $it) $subtotal += (float)$it['monto'];
$estimadoConIVA = round($subtotal * 1.16, 2);

// Cargos del año seleccionado
$desde = sprintf('%04d-01-01', $anio);
$hasta = sprintf('%04d-01-01', $anio+1);

$st = $pdo->prepare("
  SELECT *
  FROM cargos
  WHERE orden_id = ?
    AND periodo_inicio >= ? AND periodo_inicio < ?
  ORDER BY periodo_inicio
");
$st->execute([$ordenId, $desde, $hasta]);
$cargos = $st->fetchAll(PDO::FETCH_ASSOC);

// Indexar cargos por mes (1..12)

$byMonth = array_fill(1, 12, null);
foreach ($cargos as $cg) {
    $fechaBase = $cg['periodo_fin'] ?? $cg['periodo_inicio'];
    $m = (int)date('n', strtotime($fechaBase));
    $byMonth[$m] = $cg;
}


// --- Obtener items de la orden (SERVICIOS) ---
$st = $pdo->prepare("
  SELECT
    id, concepto, monto, billing_type, interval_unit, interval_count,
    pausado, pausa_desde, reanudar_en, end_at
  FROM orden_items
  WHERE orden_id = ?
  ORDER BY id ASC
");
$st->execute([$ordenId]);
$servicios = $st->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Mes inicial a mostrar
   ========================= */
$hayCargosEnAnio = false;
foreach ($byMonth as $cg) { if ($cg) { $hayCargosEnAnio = true; break; } }

$anioActual = (int)date('Y');
$mesActual  = (int)date('n');

$tsAlta   = strtotime($orden['creado_en'] ?? 'today');
$anioAlta = (int)date('Y', $tsAlta);
$mesAlta  = (int)date('n', $tsAlta);

$mesInicio = 1;

if ($hayCargosEnAnio) {
  foreach ($byMonth as $idx => $cg) {
    if ($cg) { $mesInicio = (int)$idx; break; }
  }
} else {
  if ($anio < $anioAlta) {
    $mesInicio = 13;
  } elseif ($anio == $anioAlta) {
    $mesInicio = max($mesAlta, ($anio == $anioActual ? $mesActual : 1));
  } elseif ($anio == $anioActual) {
    $mesInicio = $mesActual;
  } else {
    $mesInicio = 1;
  }
}

/* =========================
   Defaults para Emitir/Notificar
   ========================= */
$mDefault = ($mesInicio <= 12) ? $mesInicio : 1;
$iniMesDT = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mDefault));
$finMesDT = end_by_interval($iniMesDT, 'mensual', 1);
$iniMes   = $iniMesDT->format('Y-m-d');
$finMes   = $finMesDT->format('Y-m-d');

/* =========================
   Vista rápida (máx 2)
   ========================= */
$vistaRapida = [];
foreach ($items as $ix => $it) {
  if ($ix >= 2) break;
  $nombre = trim($it['concepto'] ?? 'Servicio');
  $vistaRapida[] = $nombre.' ('.money_mx((float)$it['monto']).')';
}
$vistaRapidaTxt = $vistaRapida ? implode(' · ', $vistaRapida) : '—';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cobro detalle</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    :root {
      --brand: #F9CF00;
      --brand-600: #E0B700;
      --brand-light: #FFF9E0;
      --ink: #202124;
      --ink-light: #5F6368;
      --muted: #6B7280;
      --bg-content: #F4F5F7;
      --bg-white: #FFFFFF;
      --border-color: #E8EAED;
      --accent: #1A73E8;
      --success: #34A853;
      --warning: #FBBC04;
      --danger: #EA4335;
    }

    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background-color: var(--bg-content);
      color: var(--ink);
      font-size: 15px;
      scroll-behavior: smooth; /* Scroll suave al navegar con anclas */
    }

    /* Resaltar el card cuando llegas por ancla */
    #servicios:target {
      animation: highlight 1.5s ease-in-out;
    }

    @keyframes highlight {
      0%, 100% { 
        box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
      }
      50% { 
        box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.3), 0 4px 24px rgba(26, 115, 232, .2);
        transform: translateY(-2px);
      }
    }

    /* Breadcrumb personalizado */
    .breadcrumb-custom {
      background: var(--bg-white);
      padding: 1rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .breadcrumb-custom a {
      color: var(--ink-light);
      text-decoration: none;
      transition: color .2s;
    }
    .breadcrumb-custom a:hover {
      color: var(--accent);
    }
    .breadcrumb-custom .separator {
      color: var(--muted);
      margin: 0 .5rem;
    }
    .breadcrumb-custom .current {
      color: var(--ink);
      font-weight: 600;
    }

    /* Botón de volver mejorado */
    .btn-back {
      background: var(--bg-white);
      border: 2px solid var(--border-color);
      color: var(--ink);
      font-weight: 600;
      padding: .5rem 1.25rem;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      transition: all .2s;
    }
    .btn-back:hover {
      background: var(--brand);
      border-color: var(--brand);
      color: #000;
      transform: translateX(-4px);
    }

    .cobro {
      color: var(--ink);
    }
    .cobro h4 {
      color: var(--ink);
    }
    .cobro .text-muted {
      color: var(--muted) !important;
    }

    .card {
      border-radius: 14px;
      border: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
      background-color: var(--bg-white);
      margin-bottom: 1.5rem;
    }
    .card-header {
      background-color: transparent;
      border-bottom: 1px solid var(--border-color);
      padding: 1.25rem 1.5rem;
    }
    .card-body {
      padding: 1.5rem;
    }

    /* Card especial de total */
    .card-total {
      border: 1px solid rgba(249, 207, 0, .3);
      background: linear-gradient(180deg, rgba(249, 207, 0, .10), rgba(249, 207, 0, .03));
      box-shadow: none;
    }

    /* Panel de cobro sticky */
    .cobro-panel-sticky {
      position: sticky;
      top: 24px;
      max-height: calc(100vh - 48px);
      overflow-y: auto;
    }

    .badge-mxn {
      background: var(--brand);
      color: #000;
      font-weight: 700;
    }

    .btn-brand {
      background: var(--brand);
      border-color: var(--brand);
      color: #000;
      font-weight: 600;
    }
    .btn-brand:hover {
      background: var(--brand-600);
      border-color: var(--brand-600);
      color: #000;
    }

    .btn-outline-brand {
      background: #fff;
      color: #000;
      border: 2px solid var(--brand);
      font-weight: 600;
    }
    .btn-outline-brand:hover {
      background: var(--brand);
      color: #000;
    }

    .month-tile {
      border: 1px solid var(--border-color);
      background: var(--bg-white);
      border-radius: 10px;
      transition: .15s ease;
      padding: 10px 15px;
    }
    .month-tile:hover {
      transform: translateY(-2px);
      box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .06);
    }

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: capitalize;
    }
    .status-badge.pendiente {
      color: var(--warning);
      background-color: rgba(251, 188, 4, 0.1);
    }
    .status-badge.aprobada {
      color: var(--success);
      background-color: rgba(52, 168, 83, 0.1);
    }
    .status-badge.rechazada {
      color: var(--danger);
      background-color: rgba(234, 67, 53, 0.1);
    }

    .btn-primary {
      background-color: var(--accent);
      border-color: var(--accent);
    }
    .btn-primary:hover {
      background-color: #166AE8;
      border-color: #166AE8;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.15);
    }

    .table-services td, .table-services th { 
      vertical-align: middle; 
    }
    .table-services .svc-name { 
      max-width: 360px; 
    }
    .table-services .svc-name .text-truncate { 
      display: inline-block; 
      max-width: 100%; 
    }

    .actions-inline form { 
      display: inline-block; 
      margin: 0 .25rem 0 0; 
    }
    .actions-inline .btn { 
      white-space: nowrap;
      transition: all .2s ease;
    }
    .actions-inline .btn:active {
      transform: scale(0.95);
    }

    /* Alertas con animación de entrada */
    .alert {
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Selector de año mejorado */
    .year-selector {
      display: flex;
      align-items: center;
      gap: .75rem;
      background: var(--bg-white);
      padding: .5rem 1rem;
      border-radius: 10px;
      border: 2px solid var(--border-color);
    }
    .year-selector label {
      margin: 0;
      font-weight: 600;
      font-size: 14px;
      color: var(--ink-light);
    }
    .year-selector select {
      border: none;
      background: transparent;
      font-weight: 600;
      color: var(--ink);
      cursor: pointer;
    }
    .year-selector select:focus {
      outline: none;
      box-shadow: none;
    }
  </style>
</head>
<body>

<div class="container my-4 cobro">
  
  <!-- Breadcrumb/Navegación -->
  <div class="breadcrumb-custom">
    <a href="/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar">
      ← Cobrar
    </a>
    <span class="separator">/</span>
    <span class="current">Orden #<?= (int)$orden['id'] ?></span>
  </div>

  <!-- Header con selector de año -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-1 fw-semibold">Detalle de Cobro</h4>
      <small class="text-muted">Gestiona los pagos y servicios de la orden</small>
    </div>

    <form method="get" class="year-selector">
      <input type="hidden" name="m" value="cobro">
      <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
      <label>Año:</label>
      <select name="y" class="form-select-sm" onchange="this.form.submit()">
        <?php for($yy=(int)date('Y')-2; $yy<=(int)date('Y')+1; $yy++): ?>
          <option value="<?= $yy ?>" <?= $yy===$anio?'selected':'' ?>><?= $yy ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>

  <div class="row g-3">
    <!-- Columna Izquierda: Cliente, Total Estimado y Servicios -->
    <div class="col-12 col-lg-8">
      
      <!-- Cliente -->
      <div class="card">
        <div class="card-body">
          <div class="text-uppercase text-muted small mb-2">Cliente</div>
          <div class="d-flex align-items-start justify-content-between">
            <div>
              <div class="fs-5 fw-semibold mb-1"><?= htmlspecialchars($orden['empresa'] ?? '—') ?></div>
              <div class="text-muted">
                <?= htmlspecialchars($orden['correo'] ?? '—') ?>
                <?php if (!empty($orden['telefono'])): ?> · <?= htmlspecialchars($orden['telefono']) ?><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Total Estimado con IVA -->
      <div class="card card-total">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase text-muted small mb-1">Total Estimado con IVA*</div>
              <div class="fs-2 fw-bold" id="totalDisplay"><?= money_mx($estimadoConIVA) ?></div>
              <div class="small text-muted mt-1">* Se actualiza al emitir el cargo del mes</div>
            </div>
            <div class="badge badge-mxn rounded-pill" style="font-size: 16px; padding: 8px 16px;">MXN</div>
          </div>
        </div>
      </div>

      <!-- Servicios -->
      <div class="card" id="servicios">
        <div class="card-body">
          <?php 
          // Mostrar alertas de éxito/error
          if (isset($_GET['action'])):
            $action = htmlspecialchars($_GET['action']);
            $messages = [
              'pausado' => '⏸️ Servicio pausado correctamente',
              'reanudado' => '▶️ Servicio reanudado correctamente',
              'cancelado' => '❌ Servicio cancelado definitivamente'
            ];
            $message = $messages[$action] ?? 'Acción realizada';
          ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <strong><?= $message ?></strong>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="text-uppercase text-muted small">Servicios de la orden</div>
            <small class="text-muted">Pausa = temporal · Cancelar = baja definitiva</small>
          </div>

          <?php if (!$servicios): ?>
            <div class="alert alert-info mb-0">No hay servicios en esta orden todavía.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table align-middle table-services">
                <thead class="table-light">
                  <tr>
                    <th class="svc-name" style="min-width: 260px;">Servicio</th>
                    <th class="text-end" style="width:140px;">Monto</th>
                    <th style="width:160px;">Estado</th>
                    <th class="text-end" style="width:240px;">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($servicios as $sv):
                  $estado = 'Activo';  $badge = 'success';
                  if (!empty($sv['end_at']))      { $estado='Cancelado'; $badge='secondary'; }
                  elseif ((int)$sv['pausado']===1){ $estado='Pausado';   $badge='warning';  }

                  $esRec = ($sv['billing_type']==='recurrente');
                  $freq  = $esRec ? ($sv['interval_unit'] ?: 'mensual') : '—';
                  $count = $esRec && (int)$sv['interval_count']>1 ? ' × '.(int)$sv['interval_count'] : '';
                ?>
                  <tr>
                    <td class="svc-name">
                      <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($sv['concepto'] ?: 'Servicio') ?>">
                        <?= htmlspecialchars($sv['concepto'] ?: 'Servicio') ?>
                      </div>
                      <div class="text-muted small">
                        <?= $esRec ? 'Recurrente · '.htmlspecialchars($freq).$count : 'Único pago' ?>
                      </div>
                    </td>

                    <td class="text-end"><?= money_mx($sv['monto']) ?></td>

                    <td>
                      <span class="badge text-bg-<?= $badge ?>"><?= $estado ?></span>
                      <?php if (!empty($sv['pausa_desde']) && (int)$sv['pausado']===1): ?>
                        <div class="small text-muted">Desde: <?= htmlspecialchars($sv['pausa_desde']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($sv['end_at'])): ?>
                        <div class="small text-muted">Baja: <?= htmlspecialchars($sv['end_at']) ?></div>
                      <?php endif; ?>
                    </td>

                    <td class="text-end">
                      <?php if (empty($sv['end_at'])): ?>
                        <div class="actions-inline">
                          <form method="post"
                                action="/Sistema-de-Saldos-y-Pagos-/Public/api/orden_item_pause.php"
                                onsubmit="return confirm('¿Seguro que quieres <?= ((int)$sv['pausado']===1?'reanudar':'pausar') ?> este servicio?');">
                            <input type="hidden" name="orden_id" value="<?= (int)$ordenId ?>">
                            <input type="hidden" name="item_id"  value="<?= (int)$sv['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning me-1">
                              <?= ((int)$sv['pausado']===1 ? 'Reanudar' : 'Pausar') ?>
                            </button>
                          </form>

                          <form method="post"
                                action="/Sistema-de-Saldos-y-Pagos-/Public/api/orden_item_cancel.php"
                                onsubmit="return confirm('Esta acción da de baja definitiva el servicio. ¿Continuar?');">
                            <input type="hidden" name="orden_id" value="<?= (int)$ordenId ?>">
                            <input type="hidden" name="item_id"  value="<?= (int)$sv['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="text-muted small">Sin acciones</span>
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

    <!-- Columna Derecha: Panel de Cobro (Sticky) -->
    <div class="col-12 col-lg-4">
      <div class="cobro-panel-sticky">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="text-muted">Total a cobrar</div>
              <div class="badge rounded-pill text-bg-warning">MXN</div>
            </div>

            <div class="text-end mb-4">
              <div class="display-6 fw-bold" id="totalCobrar"><?= money_mx($estimadoConIVA) ?></div>
            </div>

            <!-- Cobrar -->
            <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/pagos_registrar.php"
                  id="formCobro" onsubmit="return confirm('¿Confirmar cobro del periodo seleccionado?');">
              <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
              <input type="hidden" name="cargo_id" id="cargoId" value="">
              <input type="hidden" name="periodo_inicio" id="periodoInicio" value="">
              <input type="hidden" name="periodo_fin" id="periodoFin" value="">

              <label class="form-label fw-semibold">Periodo</label>
              <select class="form-select mb-3" id="selPeriodo" name="periodo_mes" required>
                <option value="">Elige un mes…</option>
                <?php for ($m=$mesInicio; $m<=12; $m++):
  $cg = $byMonth[$m];

  // Estatus “real” de la BD
  $estatusBD = $cg['estatus'] ?? null;

  // Para la UI solo queremos: pagado / pendiente
  $estatusUI = ($estatusBD === 'pagado') ? 'pagado' : 'pendiente';

  $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $m));
  $fin   = end_by_interval($start, 'mensual', 1);
  $cargoId = $cg['id'] ?? '';
  $totalMes = isset($cg) ? (float)$cg['total'] : $estimadoConIVA;

  // Solo deshabilitamos si ya está pagado
  $disabled = ($estatusUI === 'pagado') ? 'disabled' : '';
?>
<option
  value="<?= $m ?>"
  data-cargo-id="<?= htmlspecialchars((string)$cargoId) ?>"
  data-inicio="<?= $start->format('Y-m-d') ?>"
  data-fin="<?= $fin->format('Y-m-d') ?>"
  data-total="<?= number_format($totalMes,2,'.','') ?>"
  <?= $disabled ?>
>
  <?= $MESN[$m] ?> <?= isset($cg) ? '('.$estatusUI.')' : '' ?>
</option>
<?php endfor; ?>

              </select>

              <label class="form-label fw-semibold">Método de pago</label>
              <select name="metodo" class="form-select mb-3">
                <option>EFECTIVO</option>
                <option>TRANSFERENCIA</option>
                <option>TARJETA</option>
                <option>OTRO</option>
              </select>

              <label class="form-label fw-semibold">Referencia (opcional)</label>
              <input name="referencia" class="form-control mb-3" placeholder="# Depósito / Referencia">

              <label class="form-label fw-semibold">Monto</label>
              <input type="number" step="0.01" min="0" name="monto" id="montoInput"
                     class="form-control mb-3" placeholder="$ 0.00" required>

              <button class="btn btn-primary w-100 mb-2" type="submit">
                💳 Registrar Cobro
              </button>
            </form>

            <!-- Emitir / Notificar -->
            <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cargos_emitir.php"
                  onsubmit="return confirm('¿Emitir/actualizar el cargo y notificar al cliente?');">
              <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
              <input type="hidden" name="periodo_inicio" id="emitIni" value="<?= htmlspecialchars($iniMes) ?>">
              <input type="hidden" name="periodo_fin" id="emitFin" value="<?= htmlspecialchars($finMes) ?>">
              <input type="hidden" name="periodo_mes" id="emitMes" value="<?= (int)$mDefault ?>">
              <button class="btn btn-warning w-100" type="submit">
                📧 Emitir / Notificar
              </button>
            </form>

            <div class="small text-muted mt-3">
              Selecciona un periodo arriba. El botón "Emitir/Notificar" genera o actualiza el cargo y envía notificación al cliente.
            </div>

            <?php if ($mesInicio > 12): ?>
              <div class="alert alert-info mt-3 mb-0">
                No hay periodos aplicables para <?= (int)$anio ?> (la orden se dio de alta en <?= $MESN[$mesAlta] ?>/<?= $anioAlta ?>).
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Pagos del año (Debajo de todo, ancho completo) -->
  <div class="card">
          <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0 fw-semibold">📅 Pagos del año <?= (int)$anio ?></h6>
        <small class="text-muted">Vista mensual del estado de los pagos</small>
      </div>

      <div class="row g-2">
        <?php for($m=$mesInicio; $m<=12; $m++):
  $cg = $byMonth[$m];
  $estatusBD = $cg['estatus'] ?? null;

  // UI solo: pagado / pendiente
  $estatusUI  = ($estatusBD === 'pagado') ? 'pagado' : 'pendiente';
  $badgeTxt   = ($estatusUI === 'pagado') ? 'Pagado' : 'Pendiente';
  $badgeClass = ($estatusUI === 'pagado') ? 'success' : 'warning';
?>
  <div class="col-6 col-md-4 col-lg-3 col-xl-2">
    <div class="px-2 py-2 d-flex justify-content-between align-items-center month-tile">
      <span class="fw-semibold"><?= $MESN[$m] ?></span>
      <span class="badge text-bg-<?= $badgeClass ?>"><?= $badgeTxt ?></span>
    </div>
  </div>
<?php endfor; ?>

      </div>
    </div>
  </div>

</div>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const money = v => Number(v||0).toLocaleString('es-MX',{style:'currency', currency:'MXN'});

  const sel   = $('#selPeriodo');
  const monto = $('#montoInput');
  const disp1 = $('#totalDisplay');
  const disp2 = $('#totalCobrar');
  const cargoId = $('#cargoId');
  const pIni = $('#periodoInicio');
  const pFin = $('#periodoFin');

  const emitIni = $('#emitIni');
  const emitFin = $('#emitFin');
  const emitMes = $('#emitMes');

  function updateFromOption(opt){
    if (!opt) return;
    const total = opt.dataset.total || '0';
    monto.value = total;
    if (disp1) disp1.textContent = money(total);
    if (disp2) disp2.textContent = money(total);
    if (cargoId) cargoId.value = opt.dataset.cargoId || '';
    if (pIni) pIni.value = opt.dataset.inicio || '';
    if (pFin) pFin.value = opt.dataset.fin || '';
    if (emitIni) emitIni.value = opt.dataset.inicio || '';
    if (emitFin) emitFin.value = opt.dataset.fin || '';
    if (emitMes) emitMes.value = opt.value || '';
  }

  if (sel) {
    sel.addEventListener('change', ()=>{
      updateFromOption(sel.options[sel.selectedIndex]);
    });

    // Autoseleccionar el primer mes útil (pendiente/emitido)
    (function autoPick(){
      let idx = -1;
      for (let i=0;i<sel.options.length;i++){
        const o = sel.options[i];
        if (!o.value || o.disabled) continue;
        const txt = (o.textContent||'').toLowerCase();
        if (txt.includes('(pendiente)') || txt.includes('(emitido)')) { idx = i; break; }
        if (idx === -1) idx = i; // fallback al primero disponible
      }
      if (idx > 0) {
        sel.selectedIndex = idx;
        updateFromOption(sel.options[idx]);
      }
    })();
  }

  // Auto-cerrar alertas después de 5 segundos
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }, 5000);
  });

  // Manejar scroll después de redirección
  // Leer parámetro ?scroll=XXX de la URL
  const urlParams = new URLSearchParams(window.location.search);
  const scrollTo = urlParams.get('scroll');
  
  if (scrollTo) {
    // Esperar a que la página cargue completamente
    setTimeout(() => {
      const target = document.getElementById(scrollTo);
      if (target) {
        // Hacer scroll suave al elemento
        target.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center' 
        });
        
        // Limpiar la URL sin recargar la página
        const cleanUrl = window.location.protocol + "//" + 
                         window.location.host + 
                         window.location.pathname + 
                         '?m=cobro&orden_id=' + urlParams.get('orden_id') +
                         (urlParams.get('action') ? '&action=' + urlParams.get('action') : '') +
                         (urlParams.get('error') ? '&error=' + urlParams.get('error') : '');
        window.history.replaceState({}, '', cleanUrl);
      }
    }, 300); // Dar tiempo a que se rendericen las alertas
  }
})();
</script>
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
  $m = (int)date('n', strtotime($cg['periodo_inicio']));
  $byMonth[$m] = $cg;
}

/* =========================
   Mes inicial a mostrar
   ========================= */
$hayCargosEnAnio = false;
foreach ($byMonth as $cg) { if ($cg) { $hayCargosEnAnio = true; break; } }

$anioActual = (int)date('Y');
$mesActual  = (int)date('n');

$tsAlta   = strtotime($orden['creado_en'] ?? 'today'); // campo en 'ordenes'
$anioAlta = (int)date('Y', $tsAlta);
$mesAlta  = (int)date('n', $tsAlta);

$mesInicio = 1;

if ($hayCargosEnAnio) {
  // Si hay cargos en el año, inicia desde el primer mes que tenga cargo
  foreach ($byMonth as $idx => $cg) {
    if ($cg) { $mesInicio = (int)$idx; break; }
  }
} else {
  // Sin cargos en el año
  if ($anio < $anioAlta) {
    $mesInicio = 13; // ningún mes aplicable
  } elseif ($anio == $anioAlta) {
    // Mismo año del alta: desde el mes de alta (nunca antes del mes actual si es este año)
    $mesInicio = max($mesAlta, ($anio == $anioActual ? $mesActual : 1));
  } elseif ($anio == $anioActual) {
    // Año actual y alta fue antes -> desde el mes actual
    $mesInicio = $mesActual;
  } else {
    // Años futuros -> desde enero
    $mesInicio = 1;
  }
}

/* =========================
   Defaults para Emitir/Notificar
   ========================= */
$mDefault = ($mesInicio <= 12) ? $mesInicio : 1;  // si no hay meses, 1 como fallback
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
  <!-- Bootstrap 5 (demo) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
     :root {
    /* Paleta de Colores "Banana Group" */
    --brand: #F9CF00;
    --brand-600: #E0B700;
    --brand-light: #FFF9E0; /* Un amarillo muy pálido para fondos */

    /* Paleta de Grises (UI) */
    --ink: #202124;         /* Texto principal */
    --ink-light: #5F6368;  /* Texto secundario */
    --muted: #6B7280;        /* Texto deshabilitado o placeholders */
    --bg-content: #F4F5F7; /* Fondo gris claro del área de contenido */
    --bg-white: #FFFFFF;
    --border-color: #E8EAED; /* Borde sutil para divisiones */

    /* Colores de Estatus (basado en tu captura) */
    --accent: #1A73E8;      /* Botones de acción, enlaces */
    --success: #34A853;     /* Verde para 'Aprobado' */
    --warning: #FBBC04;     /* Amarillo para 'Pendiente' */
    --danger: #EA4335;      /* Rojo para 'Rechazado' */
    
    /* Variables de Layout */
    --header-height: 60px;
    --sidebar-width: 250px;
}

/* --------------------------------- */
/* Reseteo Básico y Body             */
/* --------------------------------- */
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background-color: var(--bg-content); /* Fondo gris claro */
    color: var(--ink);
    font-size: 15px;
}

/* --------------------------------- */
/* Layout Principal (Grid)           */
/* --------------------------------- */
.app-layout {
    display: grid;
    grid-template-areas:
        "header header"
        "sidebar content";
    grid-template-columns: var(--sidebar-width) 1fr; /* Ancho del sidebar */
    grid-template-rows: var(--header-height) 1fr; /* Alto del header */
    min-height: 100vh;
}

/* --------------------------------- */
/* 1. Cabecera (Header)              */
/* --------------------------------- */
.app-header {
    grid-area: header;
    background-color: var(--brand); /* Fondo amarillo de marca */
    border-bottom: 1px solid #E0B700; /* Borde inferior más oscuro */
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 100;
}

.app-header .logo {
    font-weight: 700;
    font-size: 20px;
    color: #000;
}

.app-header .user-menu {
    /* Estilos para tu menú de usuario "Leonel Pimentel" */
    font-weight: 500;
    color: #000;
    background-color: rgba(0, 0, 0, 0.05);
    padding: 8px 12px;
    border-radius: 20px;
    cursor: pointer;
}

/* --------------------------------- */
/* 2. Menú Lateral (Sidebar)         */
/* --------------------------------- */
.app-sidebar {
    grid-area: sidebar;
    background-color: var(--bg-white); /* Sidebar blanco */
    border-right: 1px solid var(--border-color);
    padding: 20px 15px;
    display: flex;
    flex-direction: column;
}

/* Perfil de usuario en sidebar */
.user-profile {
    padding: 10px;
    text-align: left;
    margin-bottom: 20px;
}
.user-profile div {
    font-weight: 600;
    color: var(--ink);
    font-size: 16px;
}
.user-profile small {
    font-size: 13px;
    color: var(--success); /* Verde "En Línea" */
    font-weight: 500;
}
.user-profile small::before {
    content: '●';
    color: var(--success);
    margin-right: 6px;
    font-size: 10px;
}


/* Menú de navegación */
.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex-grow: 1;
}
.nav-menu .nav-label {
    text-transform: uppercase;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
    padding: 10px 15px;
    margin-top: 15px;
}
.nav-menu li a {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    text-decoration: none;
    color: var(--ink-light);
    font-weight: 500;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: background-color 0.2s ease, color 0.2s ease;
}
.nav-menu li a:hover {
    background-color: #F8F9FA;
    color: var(--ink);
}
.nav-menu li.active a {
    background-color: var(--brand-light); /* Fondo amarillo pálido */
    color: var(--brand-600); /* Texto amarillo oscuro */
    font-weight: 600;
}
/* (Opcional) Íconos - asumiendo que usas FontAwesome o similar */
.nav-menu li a i {
    width: 28px;
    font-size: 18px;
    margin-right: 10px;
    text-align: center;
}


/* --------------------------------- */
/* 3. Área de Contenido              */
/* --------------------------------- */
.app-content {
    grid-area: content;
    background-color: var(--bg-content); /* Fondo gris claro */
    padding: 24px 30px;
    overflow-y: auto; /* Scroll solo en esta área */
}

/* Estilo global para tarjetas (como en tu captura) */
.app-content .card {
    border-radius: 14px;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
    background-color: var(--bg-white);
    margin-bottom: 24px;
}
.app-content .card-header {
    background-color: transparent;
    border-bottom: 1px solid var(--border-color);
    padding: 1.25rem 1.5rem;
}
.app-content .card-body {
    padding: 1.5rem;
}

/* --------------------------------- */
/* TUS ESTILOS ORIGINALES DE COBRO   */
/* --------------------------------- */

.cobro {
    color: var(--ink)
}
.cobro h4 {
    color: var(--ink)
}
.cobro .text-muted {
    color: var(--muted) !important
}

/* Tarjeta especial de total en "Cobro" */
.cobro .card-total {
    border: 1px solid rgba(249, 207, 0, .3);
    background: linear-gradient(180deg, rgba(249, 207, 0, .10), rgba(249, 207, 0, .03));
    box-shadow: none; /* Sobrescribir el shadow global si no se desea */
}

/* Chapita MXN */
.badge-mxn {
    background: var(--brand);
    color: #000;
    font-weight: 700;
}

/* Botones de marca */
.btn-brand {
    background: var(--brand);
    border-color: var(--brand);
    color: #000;
    font-weight: 600;
}
.btn-brand:hover {
    background: var(--brand-600);
    border-color: var(--brand-600);
    color: #000
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

/* Tiles de meses */
.month-tile {
    border: 1px solid var(--border-color);
    background: var(--bg-white);
    border-radius: 10px;
    transition: .15s ease;
    padding: 10px 15px; /* Ajuste de padding */
}
.month-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .06)
}

/* --------------------------------- */
/* ESTATUS (de tu captura)           */
/* --------------------------------- */
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

/* --------------------------------- */
/* Ajustes de Bootstrap (opcional)   */
/* --------------------------------- */
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
  </style>
</head>
<body>

<a href="/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar" class="btn btn-light border">
  ← Volver
</a>

<div class="container my-3 cobro">
 
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0 fw-semibold">Cobro <span class="text-muted">detalle</span></h4>
      <small class="text-muted">Orden #<?= (int)$orden['id'] ?></small>
    </div>

    <form method="get" class="d-flex align-items-center gap-2">
      <input type="hidden" name="m" value="cobro">
      <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
      <select name="y" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php for($yy=(int)date('Y')-2; $yy<=(int)date('Y')+1; $yy++): ?>
          <option value="<?= $yy ?>" <?= $yy===$anio?'selected':'' ?>><?= $yy ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>

  <div class="row g-3">
    <!-- Cliente + resumen -->
    <div class="col-12 col-lg-7">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="text-uppercase text-muted small mb-1">Cliente</div>
              <div class="fw-semibold"><?= htmlspecialchars($orden['empresa'] ?? '—') ?></div>
              <div class="text-muted">
                <?= htmlspecialchars($orden['correo'] ?? '—') ?>
                <?php if (!empty($orden['telefono'])): ?> · <?= htmlspecialchars($orden['telefono']) ?><?php endif; ?>
              </div>
            </div>
            <div class="text-end">
              <div class="text-uppercase text-muted small mb-1">Total estimado con IVA*</div>
              <div class="fs-2 fw-bold" id="totalDisplay"><?= money_mx($estimadoConIVA) ?></div>
              <div class="small text-muted">* Se reemplaza si ya existe cargo del mes</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="text-uppercase text-muted small mb-1">Servicios (vista rápida)</div>
          <div><?= htmlspecialchars($vistaRapidaTxt) ?></div>
        </div>
      </div>
    </div>

    <!-- Panel Cobro -->
    <div class="col-12 col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="text-muted">Total a cobrar</div>
            <div class="badge rounded-pill text-bg-warning">MXN</div>
          </div>

          <div class="text-end mb-3">
            <div class="display-6 fw-bold" id="totalCobrar"><?= money_mx($estimadoConIVA) ?></div>
          </div>

          <!-- Cobrar -->
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/pagos_registrar.php"
                id="formCobro" onsubmit="return confirm('¿Confirmar cobro del periodo seleccionado?');">
            <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
            <input type="hidden" name="cargo_id" id="cargoId" value="">
            <input type="hidden" name="periodo_inicio" id="periodoInicio" value="">
            <input type="hidden" name="periodo_fin" id="periodoFin" value="">

            <label class="form-label">Periodo</label>
            <select class="form-select mb-2" id="selPeriodo" name="periodo_mes" required>
              <option value="">Elige un mes…</option>
              <?php for ($m=$mesInicio; $m<=12; $m++):
                $cg = $byMonth[$m];
                $estatus = $cg['estatus'] ?? 'pendiente';
                $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $m));
                $fin   = end_by_interval($start, 'mensual', 1);
                $cargoId = $cg['id'] ?? '';
                $totalMes = isset($cg) ? (float)$cg['total'] : $estimadoConIVA;
                $disabled = ($estatus === 'pagado') ? 'disabled' : '';
              ?>
              <option
                value="<?= $m ?>"
                data-cargo-id="<?= htmlspecialchars((string)$cargoId) ?>"
                data-inicio="<?= $start->format('Y-m-d') ?>"
                data-fin="<?= $fin->format('Y-m-d') ?>"
                data-total="<?= number_format($totalMes,2,'.','') ?>"
                <?= $disabled ?>
              >
                <?= $MESN[$m] ?> <?= isset($cg) ? '('.$estatus.')' : '' ?>
              </option>
              <?php endfor; ?>
            </select>

            <label class="form-label">Método</label>
            <select name="metodo" class="form-select mb-2">
              <option>EFECTIVO</option>
              <option>TRANSFERENCIA</option>
              <option>TARJETA</option>
              <option>OTRO</option>
            </select>

            <input name="referencia" class="form-control mb-2" placeholder="# Depósito / Referencia (opcional)">

            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0" name="monto" id="montoInput"
                   class="form-control mb-3" placeholder="$ 0.00" required>

            <button class="btn btn-primary w-100" type="submit">
              Cobrar
            </button>
          </form>

          <!-- Emitir / Notificar -->
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cargos_emitir.php"
                class="mt-2" onsubmit="return confirm('¿Emitir/actualizar el cargo y notificar al cliente?');">
            <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
            <input type="hidden" name="periodo_inicio" id="emitIni" value="<?= htmlspecialchars($iniMes) ?>">
            <input type="hidden" name="periodo_fin" id="emitFin" value="<?= htmlspecialchars($finMes) ?>">
            <input type="hidden" name="periodo_mes" id="emitMes" value="<?= (int)$mDefault ?>">
            <button class="btn btn-warning w-100" type="submit">
              Emitir / Notificar
            </button>
          </form>

          <div class="small text-muted mt-2">
            Elige un mes arriba y usa este botón si solo quieres generar/actualizar el cargo y notificar.
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

  <!-- Pagos del año -->
  <div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">Pagos del año <?= (int)$anio ?></h6>
      </div>

      <div class="row g-2">
        <?php for($m=$mesInicio; $m<=12; $m++):
          $cg = $byMonth[$m];
          $estatus = $cg['estatus'] ?? 'pendiente';
          $badgeTxt   = ($estatus==='pagado'?'Pagado':($estatus==='emitido'?'Emitido':'Pendiente'));
          $badgeClass = ($estatus==='pagado'?'success':($estatus==='emitido'?'info':'warning'));
        ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="px-2 py-2 d-flex justify-content-between align-items-center month-tile">
            <span><?= $MESN[$m] ?></span>
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
})();
</script>
</body>
</html>

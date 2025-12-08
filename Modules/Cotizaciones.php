<?php
// Modules/cotizaciones.php
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../Includes/footer.php'; 
$pdo = db();

/* -------- Filtros -------- */
$q      = trim($_GET['q']   ?? '');
$estado = trim($_GET['est'] ?? '');
$desde  = trim($_GET['f1']  ?? '');
$hasta  = trim($_GET['f2']  ?? '');

/* -------- Paginación -------- */
$pag    = max(1, (int)($_GET['p'] ?? 1));
$pp     = 4;
$offset = ($pag-1)*$pp;

/* -------- WHERE dinámico -------- */
$where=[]; $args=[];
if ($q!=='') {
  $where[]="(empresa LIKE ? OR correo LIKE ? OR id = ?)";
  $args[]="%$q%"; $args[]="%$q%"; $args[] = ctype_digit($q)?(int)$q:0;
}
if (in_array($estado,['pendiente','aprobada','rechazada'], true)) { $where[]="estado=?"; $args[]=$estado; }
if ($desde!=='') { $where[]="DATE(creado_en) >= ?"; $args[]=$desde; }
if ($hasta!=='') { $where[]="DATE(creado_en) <= ?"; $args[]=$hasta; }
$W = $where ? 'WHERE '.implode(' AND ',$where) : '';

/* -------- KPIs -------- */
$kpi = ['pendiente'=>0,'aprobada'=>0,'rechazada'=>0];
foreach ($pdo->query("SELECT estado, COUNT(*) c FROM cotizaciones GROUP BY estado") as $r) {
  if (isset($kpi[$r['estado']])) $kpi[$r['estado']] = (int)$r['c'];
}

/* ---  Contar total de registros reales para la paginación --- */
$sqlCount = "
  SELECT COUNT(*) 
  FROM cotizaciones c 
  $W
";
$stCount = $pdo->prepare($sqlCount);
$stCount->execute($args);
$totalRows = (int)$stCount->fetchColumn(); // <--- Ahora sí tenemos el total real

/* -------- Total rows + listado -------- */
$st = $pdo->prepare("
  SELECT 
    c.id, c.empresa, c.correo, c.subtotal, c.impuestos, c.total, c.estado, c.creado_en,
    COALESCE(
      CONCAT(
        COALESCE(s.resumen, '—'),
        CASE WHEN s.cnt > 2 THEN CONCAT('  (+', s.cnt-2, ' más)') ELSE '' END
      ),
      '—'
    ) AS conceptos_resumen
  FROM cotizaciones c
  LEFT JOIN (
    SELECT
      t.cotizacion_id,
      /* primeros 2 conceptos, orden por id (ajusta si quieres otro criterio) */
      GROUP_CONCAT(CASE WHEN t.rn <= 2 THEN t.lbl END ORDER BY t.rn SEPARATOR ', ') AS resumen,
      MAX(t.cnt) AS cnt
    FROM (
      SELECT
        ci.cotizacion_id,
        /* etiqueta bonita: grupo - opcion (si existe) */
        CONCAT(ci.grupo, IF(ci.opcion IS NULL OR ci.opcion='', '', CONCAT(' - ', ci.opcion))) AS lbl,
        ROW_NUMBER() OVER (PARTITION BY ci.cotizacion_id ORDER BY ci.id)       AS rn,
        COUNT(*)    OVER (PARTITION BY ci.cotizacion_id)                        AS cnt
      FROM cotizacion_items ci
    ) AS t
    GROUP BY t.cotizacion_id
  ) AS s  ON s.cotizacion_id = c.id
  $W
  ORDER BY c.creado_en DESC, c.id DESC
  LIMIT $pp OFFSET $offset
");
$st->execute($args);
$rows = $st->fetchAll();
$totalRows = $totalRows ?? 0;


/* -------- Helpers -------- */
function money($n){ return '$'.number_format((float)$n,2); }
function folio($id){ return 'COT-'.str_pad((string)$id,5,'0',STR_PAD_LEFT); }

/* -------- QS base para paginación -------- */
$qs = $_GET; unset($qs['p']);
?>
<!-- ==== ESTILOS DEL MÓDULO ==== -->
<style>
.cotz .topbar { gap:.5rem; }
.cotz .btn-go{height:44px; display:inline-flex; align-items:center; background:#fdd835; border-color:#fdd835; color:#000}
.cotz .btn-go:hover{ filter:brightness(.95); }
.cotz .search-card .form-control{ height:44px; }
.cotz .kpi .card-body{ padding:1rem 1.1rem }
.cotz .kpi .value{ font-weight:800; font-size:1.6rem; line-height:1 }
.cotz .kpi .label{ opacity:.9 }
.cotz .table th, .cotz .table td{ vertical-align:middle }
.cotz .table-nowrap{ white-space:nowrap }
.cotz .col-acciones{ width:1%; }
.cotz .badge-pend { background:#ffe08a; color:#7a5d00; }
.cotz .badge-aprb { background:#d1fae5; color:#065f46; }
.cotz .badge-rech { background:#fee2e2; color:#7f1d1d; }
@media (max-width: 767.98px){ .cotz .d-mobile-none{ display:none!important; } }
@media (min-width: 768px){ .cotz .d-desktop-none{ display:none!important; } }
.cotz .quote-card{ background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:.75rem; padding:1rem; }
.cotz .quote-card .title{ font-weight:700 }
.cotz .quote-card .muted{ color:#6b7280; font-size:.95rem }
.cotz .quote-card .total{ font-weight:800 }
.cotz .offcanvas .table-sm th, .cotz .offcanvas .table-sm td{ padding:.35rem .5rem; }
</style>

<div class="container-fluid cotz">

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-<?= $_GET['ok']=='1'?'success':'danger' ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_GET['msg'] ?? $_GET['err'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Topbar -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cotizaciones <span class="text-muted fs-6">Cotizaciones Recibidas</span></h3>
    <div class="d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">Exportar</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#">Excel (.xlsx)</a></li>
          <li><a class="dropdown-item" href="#">PDF</a></li>
        </ul>
      </div>
      <!-- <a class="btn btn-primary btn-sm" href="?m=cotizaciones_nueva">
        <i class="bi bi-plus-lg me-1"></i> Nueva Cotización
      </a> -->
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 kpi mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="value"><?= (int)$kpi['pendiente'] ?></div><div class="label text-muted">Pendientes</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="value"><?= (int)$kpi['aprobada'] ?></div><div class="label text-muted">Aprobadas</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="value"><?= (int)$kpi['rechazada'] ?></div><div class="label text-muted">Rechazadas</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="value">—</div><div class="label text-muted">Vencidas</div>
    </div></div></div>
  </div>

  <!-- Filtros -->
  <form class="card border-0 shadow-sm search-card mb-3" method="get">
    <div class="card-body">
      <div class="row g-2">
        <input type="hidden" name="m" value="cotizaciones">
        <div class="col-12 col-md-4">
          <input name="q" value="<?= htmlspecialchars($q) ?>" type="text" class="form-control" placeholder="Cliente, folio o concepto…">
        </div>
        <div class="col-6 col-md-2">
          <select name="est" class="form-select">
            <option value="">Todos</option>
            <option value="pendiente"  <?= $estado==='pendiente'?'selected':'' ?>>Pendiente</option>
            <option value="aprobada"   <?= $estado==='aprobada' ?'selected':'' ?>>Aprobada</option>
            <option value="rechazada"  <?= $estado==='rechazada'?'selected':'' ?>>Rechazada</option>
          </select>
        </div>
        <div class="col-6 col-md-2"><input name="f1" value="<?= htmlspecialchars($desde) ?>" type="date" class="form-control"></div>
        <div class="col-6 col-md-2"><input name="f2" value="<?= htmlspecialchars($hasta) ?>" type="date" class="form-control"></div>
        <div class="col-6 col-md-2 d-grid"><button class="btn btn-go"><i class="bi bi-search me-1"></i> Buscar</button></div>
      </div>
    </div>
  </form>

  <!-- Tabla desktop -->
  <div class="card border-0 shadow-sm d-mobile-none">
    <div class="card-header bg-white">Historial de Cotizaciones</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0 table-nowrap">
        <thead class="table-light">
          <tr>
            <th>Folio</th><th>Cliente</th><th>Fecha</th><th>Conceptos</th>
            <th class="text-end">Subtotal</th><th class="text-end">IVA</th><th class="text-end">Total</th>
            <th>Estado</th><th class="text-end col-acciones">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Sin resultados</td></tr>
        <?php else: foreach ($rows as $r):
          $badge = ['pendiente'=>'badge-pend','aprobada'=>'badge-aprb','rechazada'=>'badge-rech'][$r['estado']] ?? 'badge-pend';
          $id=(int)$r['id'];
        ?>
          <tr data-id="<?= $id ?>">
            <td>
              <a href="#" class="link-primary fw-semibold js-ver" data-id="<?= $id ?>" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot"><?= folio($id) ?></a><br>
              <small class="text-muted">Origen: <span class="badge text-bg-info-subtle">Cotizador</span></small>
            </td>
            <td><?= htmlspecialchars($r['empresa']) ?><br><small class="text-muted"><?= htmlspecialchars($r['correo']) ?></small></td>
            <td><span class="fecha"><?= date('Y-m-d', strtotime($r['creado_en'])) ?></span></td>
            <td><small class="text-end"><?= htmlspecialchars($r['conceptos_resumen']) ?></small></td>
            <td class="text-end"><?= money($r['subtotal']) ?></td>
            <td class="text-end"><?= money($r['impuestos']) ?></td>
            <td class="text-end fw-semibold"><?= money($r['total']) ?></td>
            <td><span class="badge <?= $badge ?>"><?= ucfirst($r['estado']) ?></span></td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary js-ver" data-id="<?= $id ?>" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
                <?php if ($r['estado']==='pendiente'): ?>
                 <!-- <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php" class="d-inline" onsubmit="return confirm('¿Aprobar esta cotización?');">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-sm btn-success">Aprobar</button>
                  </form>
                  <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php" class="d-inline" onsubmit="return confirm('¿Rechazar esta cotización?');">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-sm btn-danger">Rechazar</button>
                  </form>-->
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Descargar PDF</a></li>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="card-body d-flex justify-content-between align-items-center">
      <small class="text-muted">Mostrando <?= $totalRows ? ($offset+1) : 0 ?> a <?= min($offset+count($rows),$totalRows) ?> de <?= $totalRows ?> cotizaciones</small>
      <?php $totalPages = max(1,(int)ceil($totalRows/$pp)); ?>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item <?= $pag<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query($qs+['p'=>$pag-1]) ?>">Anterior</a></li>
        <li class="page-item active"><span class="page-link"><?= $pag ?></span></li>
        <li class="page-item <?= $pag>=$totalPages?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query($qs+['p'=>$pag+1]) ?>">Siguiente</a></li>
      </ul>
    </div>
  </div>

  <!-- Cards móvil -->
  <div class="d-desktop-none" id="cardsCot">
    <?php foreach ($rows as $r):
      $id=(int)$r['id'];
      $badge = ['pendiente'=>'badge-pend','aprobada'=>'badge-aprb','rechazada'=>'badge-rech'][$r['estado']] ?? 'badge-pend';
       $editable = ($r['estado'] === 'pendiente');
    ?>
    <div class="quote-card mb-2">
      <div class="d-flex justify-content-between align-items-center">
        <div class="title"><?= folio($id) ?></div>
        <span class="badge <?= $badge ?>"><?= ucfirst($r['estado']) ?></span>
      </div>
      <div class="muted"><?= htmlspecialchars($r['empresa']) ?> • <?= date('Y-m-d', strtotime($r['creado_en'])) ?></div>
      <div class="mt-1 text-muted small">—</div>
      <div class="d-flex align-items-center justify-content-between mt-2">
        <span class="muted">Total</span><span class="total"><?= money($r['total']) ?></span>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn btn-sm btn-outline-secondary js-ver" data-id="<?= $id ?>" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
        <?php if ($r['estado']==='pendiente'): ?>
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php" class="d-inline" onsubmit="aprobarConRFC(event)">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm btn-success">Aprobar</button>
          </form>
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php" class="d-inline" onsubmit="confirmarAccion(event, '¿Rechazar cotización?', 'La cotización quedará marcada como rechazada.', 'Sí, rechazar', '#dc3545')">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm btn-danger">Rechazar</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

 
  <!-- Offcanvas Detalle -->
<div class="offcanvas offcanvas-end" id="ocDetalleCot" tabindex="-1">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span id="dFolio">COT-XXXX</span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <div class="mb-1"><strong>Cliente:</strong> <span id="dCliente">—</span> (<span id="dCorreo">—</span>)</div>
    <div class="mb-2"><strong>Fecha:</strong> <span id="dFecha">—</span></div>
    <hr>

    <!-- Detalle conceptos actuales -->
  <table class="table table-sm">
  <thead>
    <tr>
      <th>Concepto</th>
      <th>Tipo</th> <!-- NUEVO -->
      <th class="text-end">Importe</th>
    </tr>
  </thead>
  <tbody id="dItems">
    <tr><td>—</td><td>—</td><td class="text-end">$0.00</td></tr>
  </tbody>
  <tfoot>
    <tr><th>Subtotal</th><th></th><th class="text-end" id="dSubtotal">$0.00</th></tr>
    <tr><th>IVA <span id="dTasa">16%</span></th><th></th><th class="text-end" id="dIva">$0.00</th></tr>
    <tr><th>Total</th><th></th><th class="text-end" id="dTotal">$0.00</th></tr>
  </tfoot>
</table>



    <!-- Actualizar adicionales 
    <form class="mb-3" method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_update_totales.php">
      <input type="hidden" name="id" id="updId">
      <label class="form-label">Adicionales</label>
      <div class="input-group">
        <span class="input-group-text">$</span>
        <input type="number" step="0.01" min="0" class="form-control" name="adicionales" id="updAdic">
        <button class="btn btn-outline-secondary" type="submit">Actualizar</button>
      </div>
      <div class="form-text">Al actualizar, se recalculan IVA y Total.</div>
    </form>   -->

    <hr>

  
    <!-- ===== Editar conceptos (contenedor autocontenido) ===== -->
<div class="card mb-3" id="boxEditarConceptos">
  <div class="card-header d-flex align-items-center justify-content-between">
    <strong class="m-0">Editar conceptos</strong>
    <button class="btn btn-sm btn-light" id="btnToggleEdit" type="button"
            aria-expanded="false" aria-controls="editCollapse">
      <span class="me-1">Mostrar</span>
      <i class="bi bi-chevron-down" id="chevronEdit"></i>
    </button>
  </div>

  <div id="editCollapse" class="collapse">
    <div class="card-body">
      <!-- Aquí se pinta tu acordeón -->
      <div class="accordion" id="accordionConceptos"></div>
      <div class="form-text">
        Toca un concepto, elige la opción y ajusta el tipo de cobro; se recalcula automáticamente.
      </div>
      <!-- Si usas el JSON oculto para aprobar -->
      <input type="hidden" name="billing_json" id="billingJson">
    </div>
  </div>

  <!-- Mensaje cuando NO es editable (aprobada / rechazada) -->
  <div class="card-body text-muted small d-none" id="msgNoEditable">
    Esta Cotización Ya Ha Sido Aprobada/Rechazada
  </div>
</div>




 <!-- Selector de RFC de la empresa (emisor) -->
<div class="mb-3">
  <label for="aprRfc" class="form-label">RFC emisor (para facturar)</label>
  <select id="aprRfc" class="form-select" disabled>
    <option value="">Cargando RFCs…</option>
  </select>
  <input type="hidden" name="rfc_id" id="aprRfcId" value="">
  <div class="form-text">Selecciona el RFC de la empresa con el que se emitirá la factura.</div>
</div>





  <div class="d-grid gap-2">
  <form id="fApr" method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php"
        onsubmit="aprobarConRFC(event)">
    <input type="hidden" name="id" id="aprId">
    <input type="hidden" name="billing_json" id="billingJson">
    <!-- ✅ Este es el input que debe tener el valor del RFC -->
    <input type="hidden" name="rfc_id" id="aprRfcIdHidden">
    <button class="btn btn-success" id="btnApr" type="submit" disabled>Aprobar</button>
  </form>

  <form id="fRej" method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php"
       onsubmit="confirmarAccion(event, '¿Rechazar cotización?', 'La cotización quedará marcada como rechazada.', 'Sí, rechazar', '#dc3545')"> 
    <input type="hidden" name="id" id="rejId">
    <button class="btn btn-outline-danger" id="btnRej" disabled>Rechazar</button>
  </form>
</div>
<script>
(function(){
  const BASE = '/Sistema-de-Saldos-y-Pagos-/Public/api';

  // ============ Helpers únicos (NO duplicar) ============
  const $  = (sel, ctx=document)=>ctx.querySelector(sel);
  const $$ = (sel, ctx=document)=>Array.from(ctx.querySelectorAll(sel));
  const money = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});
  if (!window.CSS) window.CSS = {};
  if (!CSS.escape) CSS.escape = s => String(s).replace(/[^a-zA-Z0-9_\-]/g, m=>`\\${m}`);

  // ============= FACTURACIÓN: selector tipo de cobro =============
  const BILLING_RULES = {
    web:           ['una_vez','recurrente_mensual','recurrente_anual'],
    publicaciones: ['recurrente_mensual'],
    'campañas':    ['recurrente_mensual'],
    stories:       ['recurrente_mensual'],
    imprenta:      ['una_vez'],
    fotos:         ['una_vez'],
    video:         ['una_vez'],
    ads:           ['recurrente_mensual'],
    mkt:           ['una_vez'],
    _default:      ['recurrente_mensual']
  };
  const LABEL = {
    una_vez:'Único pago',
    recurrente_mensual:'Recurrente mensual',
    recurrente_bimestral:'Recurrente bimestral',
    recurrente_anual:'Recurrente anual'
  };
  const rulesFor = g => BILLING_RULES[g] || BILLING_RULES._default;
  const pretty   = k => LABEL[k] || k;
  const mapType  = (typeKey, maint=false)=>{
    switch(typeKey){
      case 'una_vez':              return {typeKey, type:'una_vez',    unit:null,     count:null, maint:!!maint};
      case 'recurrente_mensual':   return {typeKey, type:'recurrente', unit:'mensual',count:1,   maint:!!maint};
      case 'recurrente_bimestral': return {typeKey, type:'recurrente', unit:'mensual',count:2,   maint:!!maint};
      case 'recurrente_anual':     return {typeKey, type:'recurrente', unit:'anual',  count:1,   maint:!!maint};
      default:                     return {typeKey, type:'recurrente', unit:'mensual',count:1,   maint:!!maint};
    }
  };

  let billingDraft = {};
  let currentId    = null;

  const syncHidden = ()=>{
    const input = $('#billingJson');
    if (input) input.value = JSON.stringify(billingDraft);
  };
  const setTipoCell = (grupo, typeKey)=>{
    const cell = $(`[data-tipo="${CSS.escape(grupo)}"]`);
    if (cell) cell.textContent = pretty(typeKey);
  };

  // ============= API: cargar cotización =============
  async function fetchCot(id){
    const u = `${BASE}/cotizacion_show.php?id=${encodeURIComponent(id)}`;
    const r = await fetch(u, {cache:'no-store'});
    if (!r.ok) throw new Error(`HTTP ${r.status} al cargar ${u}`);
    const j = await r.json().catch(()=>{ throw new Error('Respuesta no es JSON'); });
    if (!j.ok) throw new Error(j.msg || 'Error en API');
    return j;
  }

  // ============= Pintado cabecera / items / totales =============
  function fillHeader(d){
  $('#dFolio').textContent   = d.folio || ('COT-'+String(d.id).padStart(5,'0'));
  $('#dCliente').textContent = d.empresa || '—';
  $('#dCorreo').textContent  = d.correo  || '—';
  $('#dFecha').textContent   = d.fecha   || '—';

  // IDs para aprobar/rechazar
  $('#aprId').value = d.id;
  $('#rejId').value = d.id;

  // habilitar/inhabilitar botones según estado
  const pend   = (d.estado === 'pendiente');
  const btnApr = $('#btnApr');
  const btnRej = $('#btnRej');
  if (btnApr) btnApr.disabled = !pend;
  if (btnRej) btnRej.disabled = !pend;

  // RFC emisor sólo editable si está pendiente
  const rfcSel = $('#aprRfc');
  if (rfcSel) rfcSel.disabled = !pend;

  // Mensaje "ya aprobada/rechazada"
  const msgNoEdit   = $('#msgNoEditable');
  const btnToggleEd = $('#btnToggleEdit');
  if (msgNoEdit) {
    if (pend) {
      msgNoEdit.classList.add('d-none');
      if (btnToggleEd) btnToggleEd.disabled = false;
    } else {
      msgNoEdit.classList.remove('d-none');
      if (btnToggleEd) btnToggleEd.disabled = true;
    }
  }
}

  function fillItems(d){
    const tb = $('#dItems');
    if (!tb) return;
    tb.innerHTML = '';
    (d.items || []).forEach(it=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.grupo} - ${it.opcion}</td>
        <td data-tipo="${it.grupo}">—</td>
        <td class="text-end">${money(it.valor)}</td>`;
      tb.appendChild(tr);
    });
  }

  function fillTotals(d){
    $('#dTasa').textContent     = (d.tasa_iva ?? 16) + '%';
    $('#dSubtotal').textContent = money(d.subtotal);
    $('#dIva').textContent      = money(d.impuestos);
    $('#dTotal').textContent    = money(d.total);
  }

  // ============= Radios del cotizador (valor por grupo) =============
  function conceptOptions(group){
    const MAP = {
      cuenta:        [{v:1575,l:'Obligatorio'}],
      publicaciones: [{v:1181,l:'3 veces/semana'},{v:2363,l:'6 veces/semana'}],
      'campañas':    [{v:0,l:'No'},{v:630,l:'1'},{v:1260,l:'2'}],
      reposteo:      [{v:1050,l:'Sí'},{v:0,l:'No'}],
      stories:       [{v:0,l:'No'},{v:788,l:'3'},{v:1575,l:'6'}],
      imprenta:      [{v:0,l:'No'},{v:525,l:'1 a la vez'},{v:1050,l:'2 a la vez'}],
      fotos:         [{v:1750,l:'Sí'},{v:0,l:'No'},{v:875,l:'Cada 2 meses'}],
      video:         [{v:1925,l:'Sí'},{v:0,l:'No'},{v:963,l:'Cada 2 meses'}],
      ads:           [{v:1969,l:'Sí'},{v:0,l:'No'}],
      web:           [{v:525,l:'Informativa'},{v:1575,l:'Sistema'},{v:0,l:'No'}],
      mkt:           [{v:525,l:'1 al mes'},{v:1050,l:'2 al mes'},{v:0,l:'No'}],
    };
    return MAP[group] || [];
  }

  async function updateItemValor(cotId, grupo, valor){
    const body = new URLSearchParams({id:String(cotId), grupo, valor:String(valor)});
    const res  = await fetch(`${BASE}/cotizacion_item_update.php`, {
      method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body
    });
    const j = await res.json();
    if (!j.ok) throw new Error(j.msg||'Error al guardar');
    return j;
  }

  // ============= Acordeón interno (dentro de "Editar conceptos") =============
  function buildInnerAccordion(d){
    const acc = $('#accordionConceptos');
    if (!acc) return;

    acc.innerHTML = '';

    // defaults de facturación por grupo
    (d.items||[]).forEach(it=>{
      const g = it.grupo;
      if (!billingDraft[g]) billingDraft[g] = mapType(rulesFor(g)[0]);
    });

    (d.items||[]).forEach((it, idx)=>{
      const g   = it.grupo;
      const cur = Number(it.valor||0);
      const cid = `acc-${g}-${idx}`;

      const ropts = conceptOptions(g).length ? conceptOptions(g) : [{v:cur,l:'Actual'}];
      const radios = ropts.map((o,i)=>`
        <div class="form-check">
          <input class="form-check-input js-opt" type="radio"
                 name="opt-${CSS.escape(g)}" id="${cid}-r${i}" value="${o.v}"
                 data-grupo="${g}" ${Number(o.v)===cur?'checked':''}>
          <label class="form-check-label" for="${cid}-r${i}">
            ${o.l} <span class="text-muted">(${money(o.v)})</span>
          </label>
        </div>`).join('');

      const bill = rulesFor(g);
      const selectBilling = `
        <div class="border-top pt-2 mt-2">
          <label class="form-label small mb-1">Tipo de cobro</label>
          <select class="form-select form-select-sm js-bill" data-grupo="${g}">
            ${bill.map(k=>`<option value="${k}" ${billingDraft[g]?.typeKey===k?'selected':''}>${pretty(k)}</option>`).join('')}
          </select>
          ${g==='web'?`
            <div class="form-check mt-2">
              <input class="form-check-input js-maint" type="checkbox" data-grupo="${g}" ${billingDraft[g]?.maint?'checked':''}>
              <label class="form-check-label small">Incluir mantenimiento anual $2,999</label>
            </div>`:''}
        </div>`;

      const item = document.createElement('div');
      item.className = 'accordion-item';
      item.innerHTML = `
        <h2 class="accordion-header" id="h-${cid}">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-${cid}">
            ${g} · <small class="ms-2 text-muted">${money(cur)}</small>
          </button>
        </h2>
        <div id="c-${cid}" class="accordion-collapse collapse" data-bs-parent="#accordionConceptsInner">
          <div class="accordion-body">
            <div class="mb-2">${radios || '<div class="text-muted">Sin opciones.</div>'}</div>
            ${selectBilling}
          </div>
        </div>`;
      acc.appendChild(item);
    });

    // pinta columna "Tipo" y sincroniza hidden
    Object.keys(billingDraft).forEach(gr=> setTipoCell(gr, billingDraft[gr].typeKey));
    syncHidden();

    // Atamos el listener SOLO una vez al contenedor
    if (!acc.dataset.bound) {
      acc.addEventListener('change', async ev=>{
        const g = ev.target?.dataset?.grupo;
        if (!g) return;
        try{
          if (ev.target.matches('.js-opt')) {
            const val = Number(ev.target.value||0);
            await updateItemValor(currentId, g, val);
            const ref = await fetchCot(currentId);
            fillItems(ref); fillTotals(ref);
            const small = ev.target.closest('.accordion-item')?.querySelector('.accordion-button small');
            if (small) small.textContent = money(val);
          }
          if (ev.target.matches('.js-bill')) {
            const key = ev.target.value;
            billingDraft[g] = mapType(key, billingDraft[g]?.maint);
            setTipoCell(g, billingDraft[g].typeKey);
            syncHidden();
          }
          if (ev.target.matches('.js-maint')) {
            billingDraft[g].maint = !!ev.target.checked;
            syncHidden();
          }
        }catch(e){ alert(e.message); }
      });
      acc.dataset.bound = '1';
    }
  }

   
// ============= Cargar RFCs de la empresa desde la API ============= 
async function loadCompanyRfcs(){
  const u = '/Sistema-de-Saldos-y-Pagos-/Public/api/company_rfcs_list.php';
  const r = await fetch(u, {cache:'no-store'});
  if (!r.ok) throw new Error('No se pudieron cargar los RFCs');
  const j = await r.json();
  if (!j.ok) throw new Error(j.msg || 'Error al obtener RFCs');
  return j.rows || [];
}

// Rellenar selector y control de estado del botón Aprobar
function fillCompanyRfcSelector(rows, preselectId) {
  const sel = document.getElementById('aprRfc');
  const hid = document.getElementById('aprRfcId');
  const hidForm = document.getElementById('aprRfcIdHidden'); // ✅ NUEVO
  const btn = document.getElementById('btnApr');
  if (!sel || !hid || !hidForm || !btn) return;

  sel.innerHTML = '<option value="">Selecciona RFC emisor…</option>';
  rows.forEach(r=>{
    const opt = document.createElement('option');
    opt.value = String(r.id);
    opt.textContent = `${r.rfc} — ${r.razon_social}`;
    sel.appendChild(opt);
  });

  if (preselectId) sel.value = String(preselectId);

  function updateState(){
    const val = sel.value || '';
    hid.value = val;
    hidForm.value = val; // ✅ ACTUALIZA EL HIDDEN DEL FORM
    btn.disabled = !val; // solo habilita si hay rfc seleccionado
  }
  sel.addEventListener('change', updateState);
  updateState();
}

// ✅ Función de confirmación que valida el RFC antes de enviar
window.confirmarAprobacion = function() {
  const rfcId = document.getElementById('aprRfcIdHidden')?.value;
  if (!rfcId) {
    alert('Por favor selecciona el RFC emisor antes de aprobar.');
    return false;
  }
  return confirm('¿Aprobar esta cotización?');
};


  // ============= Orquestación: abrir detalle =============
  async function openDetail(id){
    currentId    = id;
    billingDraft = {};
    try{
      const d = await fetchCot(id);
      fillHeader(d); fillItems(d); fillTotals(d);

       // cargar RFCs de la empresa
      try {
        const rfcs = await loadCompanyRfcs();
        fillCompanyRfcSelector(rfcs, null); // null o si guardas último RFC usado puedes pasarlo
          } catch(err) {
           console.warn('No se cargaron RFCs:', err.message);
           fillCompanyRfcSelector([], null);
          }

      // Cierra el panel externo (tarjeta "Editar conceptos") por defecto
      const outer = $('#accEditPanel');
      if (outer) { try{ bootstrap?.Collapse?.getOrCreateInstance(outer,{toggle:false})?.hide(); }catch(_){} }

      buildInnerAccordion(d);
    }catch(e){
      alert('No se pudo cargar el detalle: '+e.message);
    }
  }

  

  // ============ 1) Delegación: click en cualquier .js-ver ============
  document.addEventListener('click', (ev)=>{
    const btn = ev.target.closest('.js-ver');
    if (!btn) return;
    const id = btn.dataset.id;
    if (id) openDetail(id);
  });

  // ============ 2) Fallback: al abrir el offcanvas por Bootstrap ============
  const oc = $('#ocDetalleCot');
  if (oc){
    oc.addEventListener('show.bs.offcanvas', (ev)=>{
      const id = ev.relatedTarget?.dataset?.id;
      if (id) openDetail(id);
    });
  }
})();
</script>


<script>
// --- Control de la tarjeta "Editar conceptos" (abre/cierra sin errores) ---
(function(){
  const btn      = document.getElementById('btnToggleEdit');
  const chevron  = document.getElementById('chevronEdit');
  const collapse = document.getElementById('editCollapse');

  if (!btn || !collapse) return;

  // Instancia Bootstrap Collapse sin auto-toggle
  const bs = (window.bootstrap && bootstrap.Collapse)
    ? bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false })
    : null;

  // Estado inicial: cerrado
  const setClosed = () => {
    btn.setAttribute('aria-expanded', 'false');
    // Usa el 2º parámetro de toggle para FORZAR el estado (evita el error)
    btn.classList.toggle('collapsed', true);
    btn.querySelector('span').textContent = 'Mostrar';
    if (chevron) { chevron.classList.remove('bi-chevron-up'); chevron.classList.add('bi-chevron-down'); }
  };

  const setOpen = () => {
    btn.setAttribute('aria-expanded', 'true');
    btn.classList.toggle('collapsed', false);
    btn.querySelector('span').textContent = 'Ocultar';
    if (chevron) { chevron.classList.remove('bi-chevron-down'); chevron.classList.add('bi-chevron-up'); }
  };

  setClosed();

  btn.addEventListener('click', (e)=>{
    e.preventDefault();
    if (bs) {
      const isOpen = collapse.classList.contains('show');
      isOpen ? bs.hide() : bs.show();
    } else {
      // Fallback sin Bootstrap
      const isOpen = collapse.classList.contains('show');
      collapse.classList.toggle('show', !isOpen);
      (isOpen ? setClosed : setOpen)();
    }
  });

  // Sincroniza estado cuando se usa Bootstrap
  if (bs) {
    collapse.addEventListener('shown.bs.collapse', setOpen);
    collapse.addEventListener('hidden.bs.collapse', setClosed);
  }
})();
</script>

<script>
// ... tus otros scripts ...

// ✅ Nueva función: Valida RFC y luego muestra SweetAlert
function aprobarConRFC(event) {
  event.preventDefault(); // Detenemos el envío
  const form = event.target;
  const rfcId = document.getElementById('aprRfcIdHidden')?.value;

  // 1. Validar RFC
  if (!rfcId) {
    Swal.fire({
      icon: 'warning',
      title: 'Falta RFC',
      text: 'Por favor selecciona el RFC emisor antes de aprobar.',
      confirmButtonColor: '#fdd835',
      color: '#000'
    });
    return;
  }

  // 2. Si hay RFC, mostramos la confirmación bonita
  Swal.fire({
    title: '¿Aprobar cotización?',
    text: "Se generará la orden de aprobación y se notificará al cliente.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#198754', // Verde éxito
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, aprobar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit(); // Enviamos manualmente
    }
  });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Verificar que Pusher esté activo y tengamos usuario
    if (typeof Pusher === 'undefined' || !window.PUSHER_CONFIG || !window.APP_USER) return;

    // 2. Conectar a Pusher (si no está conectado globalmente)
    const pusher = new Pusher(window.PUSHER_CONFIG.key, {
        cluster: window.PUSHER_CONFIG.cluster,
        forceTLS: true
    });

    // 3. Suscribirse al canal personal del usuario
    const channelName = 'notificaciones_user_' + window.APP_USER.id;
    const channel = pusher.subscribe(channelName);

    // 4. Escuchar el evento "nueva-notificacion"
    channel.bind('nueva-notificacion', function(data) {
        // Verificar si la notificación es sobre una COTIZACIÓN
        // (data.ref_tipo viene de la BD, asegurémonos que coincida)
        if (data.ref_tipo === 'cotizacion' && data.titulo.includes('Nueva')) {
            
            // Mostrar un Toast de aviso (Opcional, porque el header ya lo hace)
            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
            });
            Toast.fire({ icon: 'info', title: 'Nueva cotización recibida. Actualizando...' });

            // ⚡ RECARGAR LA PÁGINA AUTOMÁTICAMENTE
            setTimeout(() => {
                window.location.reload(); 
            }, 2000); // Esperamos 2 seg para que lean la notificación y luego recargamos
        }
    });
});
</script>

 </body>
</html>
<?php
// Modules/cotizaciones.php
require_once __DIR__ . '/../App/bd.php';
$pdo = db();

/* -------- Filtros -------- */
$q      = trim($_GET['q']   ?? '');
$estado = trim($_GET['est'] ?? '');
$desde  = trim($_GET['f1']  ?? '');
$hasta  = trim($_GET['f2']  ?? '');

/* -------- Paginación -------- */
$pag    = max(1, (int)($_GET['p'] ?? 1));
$pp     = 10;
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

/* -------- Total rows + listado -------- */
$st=$pdo->prepare("SELECT COUNT(*) FROM cotizaciones $W"); $st->execute($args);
$totalRows=(int)$st->fetchColumn();

$st=$pdo->prepare("
  SELECT id, empresa, correo, subtotal, impuestos, total, estado, creado_en
  FROM cotizaciones
  $W
  ORDER BY creado_en DESC, id DESC
  LIMIT $pp OFFSET $offset
");
$st->execute($args);
$rows=$st->fetchAll();

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
      <a class="btn btn-primary btn-sm" href="?m=cotizaciones_nueva">
        <i class="bi bi-plus-lg me-1"></i> Nueva manual
      </a>
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
            <td><small class="text-muted">—</small></td>
            <td class="text-end"><?= money($r['subtotal']) ?></td>
            <td class="text-end"><?= money($r['impuestos']) ?></td>
            <td class="text-end fw-semibold"><?= money($r['total']) ?></td>
            <td><span class="badge <?= $badge ?>"><?= ucfirst($r['estado']) ?></span></td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary js-ver" data-id="<?= $id ?>" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
                <?php if ($r['estado']==='pendiente'): ?>
                  <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php" class="d-inline" onsubmit="return confirm('¿Aprobar esta cotización?');">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-sm btn-success">Aprobar</button>
                  </form>
                  <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php" class="d-inline" onsubmit="return confirm('¿Rechazar esta cotización?');">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn-sm btn-danger">Rechazar</button>
                  </form>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Descargar PDF</a></li>
                  <li><a class="dropdown-item" href="#">Enviar por WhatsApp</a></li>
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
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php" class="d-inline" onsubmit="return confirm('¿Aprobar esta cotización?');">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm btn-success">Aprobar</button>
          </form>
          <form method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php" class="d-inline" onsubmit="return confirm('¿Rechazar esta cotización?');">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-sm btn-danger">Rechazar</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Offcanvas Detalle -->
  <!-- Offcanvas Detalle -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="ocDetalleCot">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span id="dFolio">COT-XXXX</span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <div class="mb-1"><strong>Cliente:</strong> <span id="dCliente">—</span> (<span id="dCorreo">—</span>)</div>
    <div class="mb-2"><strong>Fecha:</strong> <span id="dFecha">—</span></div>

    <hr>

    <!-- Detalle conceptos actuales -->
    <div class="table-responsive mb-3">
      <table class="table table-sm">
        <thead><tr><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
        <tbody id="dItems"><tr><td>—</td><td class="text-end">$0.00</td></tr></tbody>
        <tfoot>
          <tr><th>Subtotal</th><th class="text-end" id="dSubtotal">$0.00</th></tr>
          <tr><th>IVA <span id="dTasa">16%</span></th><th class="text-end" id="dIva">$0.00</th></tr>
          <tr><th>Total</th><th class="text-end" id="dTotal">$0.00</th></tr>
        </tfoot>
      </table>
    </div>


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

    <!-- Acordeón de edición de conceptos -->
     <!-- Editar conceptos (collapse) -->
    <div class="mb-3">
      <button class="btn btn-light w-100 text-start d-flex align-items-center justify-content-between"
              type="button" data-bs-toggle="collapse" data-bs-target="#boxEditarConceptos">
        <span>Editar conceptos</span>
        <i class="bi bi-chevron-down"></i>
      </button>
      <div class="collapse mt-2" id="boxEditarConceptos">
        <div class="accordion" id="accordionConceptos"><!-- JS --></div>
        <div class="form-text">Toca un concepto, elige la opción y se recalcula automáticamente.</div>
      </div>
    </div>

   <!-- Periodicidad + Aprobar / Rechazar -->
    <div class="mb-2">
      <label class="form-label">Periodicidad de cobro</label>
      <select class="form-select" name="periodicidad" id="aprPer">
        <option value="unico">Pago único</option>
        <option value="mensual" selected>Mensual</option>
        <option value="bimestral">Bimestral</option>
      </select>
    </div>


     <div class="d-grid gap-2">
      <form id="fApr" method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_approve.php"
            onsubmit="return confirm('¿Aprobar esta cotización?');">
        <input type="hidden" name="id" id="aprId">
        <button class="btn btn-success" id="btnApr">Aprobar</button>
      </form>
      <form id="fRej" method="post" action="/Sistema-de-Saldos-y-Pagos-/Public/api/cotizacion_reject.php"
            onsubmit="return confirm('¿Rechazar esta cotización?');">
        <input type="hidden" name="id" id="rejId">
        <button class="btn btn-outline-danger" id="btnRej">Rechazar</button>
      </form>
    </div>
  </div>
</div>


 <script>
(function(){
  const BASE = '/Sistema-de-Saldos-y-Pagos-/Public/api';
  const $ = (sel, ctx=document)=>ctx.querySelector(sel);
  const money = v => Number(v||0).toLocaleString('es-MX',{style:'currency',currency:'MXN'});

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

  async function loadCot(id){
    const r = await fetch(`${BASE}/cotizacion_show.php?id=${encodeURIComponent(id)}`, {cache:'no-store'});
    if (!r.ok) throw new Error('HTTP '+r.status);
    const j = await r.json();
    if (!j.ok) throw new Error(j.msg || 'Error');
    return j;
  }

  function fillHeader(d){
    $('#dFolio').textContent   = d.folio || ('COT-'+String(d.id).padStart(5,'0'));
    $('#dCliente').textContent = d.empresa || '—';
    $('#dCorreo').textContent  = d.correo  || '—';
    $('#dFecha').textContent   = d.fecha   || '—';
    $('#aprId').value = d.id; $('#rejId').value = d.id;
    if (d.periodicidad) $('#aprPer').value = d.periodicidad;
    const pend = (d.estado === 'pendiente');
    $('#btnApr').disabled = !pend; $('#btnRej').disabled = !pend;
  }

  function fillItems(d){
    const tb = $('#dItems'); tb.innerHTML = '';
    (d.items || []).forEach(it=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${it.grupo} - ${it.opcion}</td><td class="text-end">${money(it.valor)}</td>`;
      tb.appendChild(tr);
    });
  }

  function fillTotals(d){
    $('#dTasa').textContent     = (d.tasa_iva ?? 16) + '%';
    $('#dSubtotal').textContent = money(d.subtotal);
    $('#dIva').textContent      = money(d.impuestos);
    $('#dTotal').textContent    = money(d.total);
  }

  function buildAccordion(d){
    const cont = $('#accordionConceptos');
    
    const existingCollapses = cont.querySelectorAll('.accordion-collapse');
    existingCollapses.forEach(el => {
        const instance = bootstrap.Collapse.getInstance(el);
        if (instance) {
            instance.dispose();
        }
    });

    cont.innerHTML = '';

    (d.items || []).forEach((it,idx)=>{
      const gid = it.grupo;
      const cur = Number(it.valor||0);
      const cid = `acc-${gid}-${idx}`;
      const opts = conceptOptions(gid);
      const radios = opts.map((o,i)=>`<div class="form-check"><input class="form-check-input js-opt" type="radio" name="opt-${gid}" id="${cid}-${i}" value="${o.v}" data-grupo="${gid}" ${Number(o.v)===cur?'checked':''}><label class="form-check-label" for="${cid}-${i}">${o.l} <span class="text-muted">(${money(o.v)})</span></label></div>`).join('');
      const item = document.createElement('div');
      item.className = 'accordion-item';
      // **NOTA**: He quitado data-bs-toggle="collapse" del botón para evitar conflictos.
      item.innerHTML = `<h2 class="accordion-header" id="h-${cid}"><button class="accordion-button collapsed" type="button" data-bs-target="#c-${cid}">${gid} · <small class="ms-2 text-muted">${money(cur)}</small></button></h2><div id="c-${cid}" class="accordion-collapse collapse" data-bs-parent="#accordionConceptos"><div class="accordion-body">${radios || '<div class="text-muted">Sin opciones.</div>'}</div></div>`;
      cont.appendChild(item);
    });

    cont.querySelectorAll('.js-opt').forEach(r=>{
      r.addEventListener('change', async ev=>{
        const grupo = ev.target.dataset.grupo;
        const valor = Number(ev.target.value||0);
        try{
          const body = new URLSearchParams({id:d.id, grupo, valor:String(valor)});
          const res  = await fetch(`${BASE}/cotizacion_item_update.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if (!j.ok) throw new Error(j.msg||'Error');
          const ref = await loadCot(d.id);
          fillItems(ref); fillTotals(ref);
          const small = ev.target.closest('.accordion-item').querySelector('.accordion-button small');
          if (small) small.textContent = money(valor);
        }catch(e){
          alert('No se pudo actualizar: '+e.message);
        }
      });
    });
  }

  async function openDetail(id){
    const d = await loadCot(id);
    fillHeader(d); fillItems(d); fillTotals(d); buildAccordion(d);
  }
  
  // ============ INICIO DEL NUEVO CÓDIGO ============
  // Se añade un único listener al contenedor del acordeón
  const accordionContainer = document.getElementById('accordionConceptos');
  if (accordionContainer) {
    accordionContainer.addEventListener('click', function(event) {
      const button = event.target.closest('.accordion-button');
      if (!button) return; // Si no se hizo clic en un botón, no hace nada

      // Previene el comportamiento por defecto para tener control total
      event.preventDefault();

      const targetSelector = button.getAttribute('data-bs-target');
      if (!targetSelector) return;
      
      const targetElement = document.querySelector(targetSelector);
      if (!targetElement) return;

      // Obtiene o crea la instancia de Bootstrap y ejecuta el método .toggle()
      const collapseInstance = bootstrap.Collapse.getOrCreateInstance(targetElement);
      collapseInstance.toggle();
    });
  }
  // ============= FIN DEL NUEVO CÓDIGO =============

  document.querySelectorAll('.js-ver').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      const id = e.currentTarget.dataset.id;
      try { await openDetail(id); }
      catch(err){ alert('No se pudo cargar: '+err.message); }
    });
  });

  const off = document.getElementById('ocDetalleCot');
  if (off){
    off.addEventListener('click', (ev)=>{
      const acc = $('#accordionConceptos', off);
      if (!acc) return;
      if (!acc.contains(ev.target)){
        acc.querySelectorAll('.accordion-collapse.show').forEach(el=>{
          bootstrap.Collapse.getOrCreateInstance(el).hide();
        });
      }
    });
  }
})();
</script>


 </body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cotizaciones</title>
</head>

<style>
/* ===== Cotizaciones (UI estática y responsiva) ===== */
.cotz .topbar { gap:.5rem; }
.cotz .btn-go{height:44px; display:inline-flex; align-items:center; background:#fdd835; border-color:#fdd835; color:#000}
.cotz .btn-go:hover{ filter:brightness(.95); }
.cotz .search-card .form-control{ height:44px; }

/* Tarjetas KPI */
.cotz .kpi .card-body{ padding:1rem 1.1rem }
.cotz .kpi .value{ font-weight:800; font-size:1.6rem; line-height:1 }
.cotz .kpi .label{ opacity:.9 }

/* Tabla + acciones */
.cotz .table th, .cotz .table td{ vertical-align:middle }
.cotz .table-nowrap{ white-space:nowrap }
.cotz .col-acciones{ width:1%; } /* compactar acciones */
.cotz .badge-pend { background:#ffe08a; color:#7a5d00; }
.cotz .badge-aprb { background:#d1fae5; color:#065f46; }
.cotz .badge-rech { background:#fee2e2; color:#7f1d1d; }
.cotz .badge-venc { background:#fde68a; color:#78350f; }

/* Lista móvil (cards) */
@media (max-width: 767.98px){
  .cotz .d-mobile-none{ display:none!important; }
}
@media (min-width: 768px){
  .cotz .d-desktop-none{ display:none!important; }
}
.cotz .quote-card{ background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:.75rem; padding:1rem; }
.cotz .quote-card .title{ font-weight:700 }
.cotz .quote-card .muted{ color:#6b7280; font-size:.95rem }
.cotz .quote-card .total{ font-weight:800 }

/* Offcanvas detalle */
.cotz .offcanvas .table-sm th, .cotz .offcanvas .table-sm td{ padding:.35rem .5rem; }
</style>

<body>

<div class="container-fluid cotz">

  <!-- Título + acciones superiores -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cotizaciones <span class="text-muted fs-6">Cotizaciones Recibidas </span></h3>
    <div class="d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">Exportar</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#">Excel (.xlsx)</a></li>
          <li><a class="dropdown-item" href="#">PDF</a></li>
        </ul>
      </div>
      <button class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nueva manual
      </button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 kpi mb-3">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="value">8</div>
          <div class="label text-muted">Pendientes</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="value">12</div>
          <div class="label text-muted">Aprobadas</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="value">3</div>
          <div class="label text-muted">Rechazadas</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="value">2</div>
          <div class="label text-muted">Vencidas</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-12 col-md-4">
          <input id="q" type="text" class="form-control" placeholder="Cliente, folio o concepto…">
        </div>
        <div class="col-6 col-md-2">
          <select id="estado" class="form-select">
            <option value="">Todos</option>
            <option>Pendiente</option>
            <option>Aprobada</option>
            <option>Rechazada</option>
            <option>Vencida</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <input id="f1" type="date" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <input id="f2" type="date" class="form-control">
        </div>
        <div class="col-6 col-md-2 d-grid">
          <button id="buscar" class="btn btn-go"><i class="bi bi-search me-1"></i> Buscar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla desktop -->
  <div class="card border-0 shadow-sm d-mobile-none">
    <div class="card-header bg-white">Historial de Cotizaciones</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0 table-nowrap" id="tblCot">
        <thead class="table-light">
          <tr>
            <th>Folio</th>
            <th>Cliente</th>
            <th>Fecha</th>
            <th>Conceptos</th>
            <th class="text-end">Subtotal</th>
            <th class="text-end">IVA</th>
            <th class="text-end">Total</th>
            <th>Estado</th>
            <th class="text-end col-acciones">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <!-- Fila 1 -->
          <tr data-estado="Pendiente" data-fecha="2025-09-05" data-cliente="Josue Martinez" data-folio="COT-00123">
            <td>
              <a href="#" class="link-primary fw-semibold">COT-00123</a><br>
              <small class="text-muted">Origen: <span class="badge text-bg-info-subtle">Cotizador</span></small>
            </td>
            <td>Josue Martinez<br><small class="text-muted">josue@mail.com</small></td>
            <td><span class="fecha">2025-09-05</span></td>
            <td><small>Web Pro + Hosting</small></td>
            <td class="text-end">$3,000.00</td>
            <td class="text-end">$480.00</td>
            <td class="text-end fw-semibold">$3,480.00</td>
            <td><span class="badge badge-pend">Pendiente</span></td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary ver" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
                <button class="btn btn-sm btn-success">Aprobar</button>
                <button class="btn btn-sm btn-danger">Rechazar</button>
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Generar enlace de pago</a></li>
                  <li><a class="dropdown-item" href="#">Descargar PDF</a></li>
                  <li><a class="dropdown-item" href="#">Enviar por WhatsApp</a></li>
                </ul>
              </div>
            </td>
          </tr>

          <!-- Fila 2 -->
          <tr data-estado="Aprobada" data-fecha="2025-09-01" data-cliente="María López" data-folio="COT-00122">
            <td><a href="#" class="link-primary fw-semibold">COT-00122</a><br>
              <small class="text-muted">Origen: <span class="badge text-bg-secondary">Manual</span></small></td>
            <td>María López<br><small class="text-muted">maria@correo.com</small></td>
            <td><span class="fecha">2025-09-01</span></td>
            <td><small>Campaña Ads</small></td>
            <td class="text-end">$2,000.00</td>
            <td class="text-end">$320.00</td>
            <td class="text-end fw-semibold">$2,320.00</td>
            <td><span class="badge badge-aprb">Aprobada</span></td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary ver" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
                <button class="btn btn-sm btn-outline-primary">Enlace de pago</button>
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Descargar PDF</a></li>
                  <li><a class="dropdown-item" href="#">Enviar por WhatsApp</a></li>
                </ul>
              </div>
            </td>
          </tr>

          <!-- Fila 3 -->
          <tr data-estado="Rechazada" data-fecha="2025-08-28" data-cliente="Carlos Ruiz" data-folio="COT-00121">
            <td><a href="#" class="link-primary fw-semibold">COT-00121</a><br>
              <small class="text-muted">Origen: <span class="badge text-bg-info-subtle">Cotizador</span></small></td>
            <td>Carlos Ruiz<br><small class="text-muted">carlos@dom.com</small></td>
            <td><span class="fecha">2025-08-28</span></td>
            <td><small>Identidad + Sesión Foto</small></td>
            <td class="text-end">$1,000.00</td>
            <td class="text-end">$160.00</td>
            <td class="text-end fw-semibold">$1,160.00</td>
            <td><span class="badge badge-rech">Rechazada</span></td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary ver" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Más</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Descargar PDF</a></li>
                  <li><a class="dropdown-item" href="#">Reenviar por WhatsApp</a></li>
                </ul>
              </div>
            </td>
          </tr>

        </tbody>
      </table>
    </div>

    <!-- Paginación (placeholder) -->
    <div class="card-body d-flex justify-content-between align-items-center">
      <small class="text-muted">Mostrando 1 a 3 de 3 cotizaciones</small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>

  <!-- Lista móvil -->
  <div class="d-desktop-none" id="cardsCot">
    <div class="quote-card mb-2" data-estado="Pendiente" data-fecha="2025-09-05" data-cliente="Josue Martinez" data-folio="COT-00123" data-total="3480.00">
      <div class="d-flex justify-content-between align-items-center">
        <div class="title">COT-00123</div>
        <span class="badge badge-pend">Pendiente</span>
      </div>
      <div class="muted">Josue Martinez • 2025-09-05</div>
      <div class="mt-1">Web Pro + Hosting</div>
      <div class="d-flex align-items-center justify-content-between mt-2">
        <span class="muted">Total</span><span class="total">$3,480.00</span>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
        <button class="btn btn-sm btn-success">Aprobar</button>
        <button class="btn btn-sm btn-danger">Rechazar</button>
        <button class="btn btn-sm btn-outline-primary">Enlace de pago</button>
      </div>
    </div>

    <div class="quote-card mb-2" data-estado="Aprobada" data-fecha="2025-09-01" data-cliente="María López" data-folio="COT-00122" data-total="2320.00">
      <div class="d-flex justify-content-between align-items-center">
        <div class="title">COT-00122</div>
        <span class="badge badge-aprb">Aprobada</span>
      </div>
      <div class="muted">María López • 2025-09-01</div>
      <div class="mt-1">Campaña Ads</div>
      <div class="d-flex align-items-center justify-content-between mt-2">
        <span class="muted">Total</span><span class="total">$2,320.00</span>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#ocDetalleCot">Ver</button>
        <button class="btn btn-sm btn-outline-primary">Enlace de pago</button>
      </div>
    </div>
  </div>

  <!-- Offcanvas Detalle -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="ocDetalleCot">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="dFolio">COT-00123</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
      <div class="mb-1"><strong>Cliente:</strong> <span id="dCli">—</span></div>
      <div class="mb-2"><strong>Fecha:</strong> <span id="dFecha">—</span></div>
      <hr>
      <table class="table table-sm">
        <thead><tr><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
        <tbody id="dItems">
          <tr><td>Web Pro</td><td class="text-end">$3,000.00</td></tr>
          <tr><td>IVA 16%</td><td class="text-end">$480.00</td></tr>
          <tr><th>Total</th><th class="text-end">$3,480.00</th></tr>
        </tbody>
      </table>
      <div class="d-grid gap-2">
        <button class="btn btn-success">Aprobar</button>
        <button class="btn btn-outline-primary">Generar enlace de pago</button>
        <button class="btn btn-outline-secondary">Descargar PDF</button>
      </div>
    </div>
  </div>

</div>

<script>
/* ===== Filtro simple (front-end) ===== */
(function(){
  const q = document.getElementById('q');
  const estado = document.getElementById('estado');
  const f1 = document.getElementById('f1');
  const f2 = document.getElementById('f2');
  const btn = document.getElementById('buscar');

  const rows = Array.from(document.querySelectorAll('#tblCot tbody tr'));
  const cards = Array.from(document.querySelectorAll('#cardsCot .quote-card'));

  function inRange(dateStr, from, to){
    if(!from && !to) return true;
    const d = new Date(dateStr);
    if(from && d < new Date(from)) return false;
    if(to && d > new Date(to)) return false;
    return true;
  }

  function match(el, term, est, from, to){
    const cliente = (el.dataset.cliente || '').toLowerCase();
    const folio   = (el.dataset.folio || '').toLowerCase();
    const fecha   = el.dataset.fecha || el.querySelector('.fecha')?.textContent || '';
    const okTerm  = !term || cliente.includes(term) || folio.includes(term);
    const okEst   = !est || (el.dataset.estado === est);
    const okDate  = inRange(fecha, from, to);
    return okTerm && okEst && okDate;
  }

  function apply(){
    const term = (q.value||'').toLowerCase().trim();
    const est = estado.value || '';
    const from = f1.value || '';
    const to = f2.value || '';

    rows.forEach(r => r.style.display = match(r, term, est, from, to) ? '' : 'none');
    cards.forEach(c => c.style.display = match(c, term, est, from, to) ? '' : 'none');
  }

  [q, estado, f1, f2].forEach(el => el.addEventListener('input', apply));
  btn.addEventListener('click', apply);
})();

/* ===== Relleno rápido de offcanvas (demo) ===== */
document.querySelectorAll('.ver').forEach(btn=>{
  btn.addEventListener('click', e=>{
    const tr = e.target.closest('tr');
    if(!tr) return;
    document.getElementById('dFolio').textContent = tr.dataset.folio || 'COT-XXXX';
    document.getElementById('dCli').textContent   = tr.dataset.cliente || '—';
    document.getElementById('dFecha').textContent = tr.dataset.fecha || '—';
  });
});
</script>
</body>
</html>

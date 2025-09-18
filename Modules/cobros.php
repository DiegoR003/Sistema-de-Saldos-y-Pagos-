<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Corte Diario</title>

  <style>
  /* ===== Corte Diario (estático) ===== */
  .corte .topbar{gap:.5rem;}
  .corte .filters .form-control{height:44px;}
  .corte .filters .btn-go{
    height:44px; display:inline-flex; align-items:center;
    background:#22c55e; border-color:#22c55e; color:#fff;
  }
  .corte .filters .btn-go:hover{filter:brightness(.95);}
  .corte .card-header{font-weight:600;}

  /* Resumen */
  .corte .mini-card{
    border-radius:.5rem; border:1px solid #eef2f7; padding:.85rem 1rem;
    background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.03);
  }
  .corte .mini-title{font-size:.85rem; color:#6b7280; margin-bottom:.25rem;}
  .corte .mini-value{font-weight:700; font-size:1.15rem;}

  /* Tabla desktop */
  .corte table{width:100%;}
  .corte th,.corte td{white-space:nowrap; vertical-align:middle;}

  /* ===== Versión móvil: filas apiladas como tarjetas ===== */
  @media (max-width: 768px){
    .corte thead{position:absolute; left:-9999px; top:-9999px;}

    .corte table,
    .corte tbody,
    .corte tr,
    .corte td{display:block; width:100%;}
    .corte tbody{display:block;}

    .corte tr{
      background:#fff; border:1px solid #e9ecef; border-radius:.5rem;
      padding:.5rem .75rem; margin-bottom:.75rem;
      box-shadow:0 1px 3px rgba(0,0,0,.04);
    }

    .corte td{
      border:0; border-bottom:1px solid #f1f3f5;
      position:relative; padding:.5rem 0 .5rem 7.75rem;
      white-space:normal; text-align:right;
    }
    .corte td:last-child{border-bottom:0;}

    .corte td::before{
      content:attr(data-label);
      position:absolute; left:.75rem; top:.5rem; width:6.8rem;
      font-weight:600; color:#6b7280; text-align:left; white-space:normal;
    }

    .corte .table-wrap{overflow:visible;}
    .corte .actions{display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end;}
  }
  </style>
</head>
<body>

<div class="container-fluid corte">
  <!-- Título + acciones -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Corte Diario <span class="text-muted fs-6">Control panel</span></h3>

    <div class="d-flex align-items-center gap-2">
      <!-- Opcional: Mostrar -->
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

      <!-- Opcional: Exportar (placeholders) -->
      <div class="btn-group">
        <button class="btn btn-outline-secondary btn-sm">Exportar PDF</button>
        <button class="btn btn-outline-secondary btn-sm">Exportar Excel</button>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body filters">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Desde</label>
          <input id="fDesde" type="date" class="form-control" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Hasta</label>
          <input id="fHasta" type="date" class="form-control" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Cliente</label>
          <input id="fCliente" type="text" class="form-control" placeholder="Nombre del cliente…"/>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <label class="form-label mb-1 d-none d-md-block">&nbsp;</label>
          <button id="btnFiltrar" class="btn btn-go"><i class="bi bi-funnel me-1"></i> Filtrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">Historial de Cobros</div>

    <div class="table-wrap">
      <table class="table align-middle mb-0" id="tblCorte">
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
          <!-- Ejemplos estáticos -->
          <tr>
            <td data-label="Folio">000047</td>
            <td data-label="Cliente" class="cli-name">amner</td>
            <td data-label="Servicio">Tigo</td>
            <td data-label="Fecha" class="date-val">2019-01-05</td>
            <td data-label="Importe" class="amt-val">20.00</td>
            <td data-label="Método de pago" class="met-val">Efectivo</td>
            <td data-label="# Depósito">—</td>
          </tr>

          <tr>
            <td data-label="Folio">000046</td>
            <td data-label="Cliente" class="cli-name">Amado Saucedo</td>
            <td data-label="Servicio">Carwash</td>
            <td data-label="Fecha" class="date-val">2019-01-05</td>
            <td data-label="Importe" class="amt-val">45.00</td>
            <td data-label="Método de pago" class="met-val">Transferencia</td>
            <td data-label="# Depósito">TRX-93211</td>
          </tr>

          <tr>
            <td data-label="Folio">000045</td>
            <td data-label="Cliente" class="cli-name">Dayana Saucedo</td>
            <td data-label="Servicio">Web Services</td>
            <td data-label="Fecha" class="date-val">2019-01-04</td>
            <td data-label="Importe" class="amt-val">30.00</td>
            <td data-label="Método de pago" class="met-val">Tarjeta</td>
            <td data-label="# Depósito">POS-1287</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación simulada -->
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted" id="lblCount">Mostrando 1 a 3 de 3 registros</small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>

  <!-- Resumen del corte -->
  <div class="mt-3">
    <div class="row g-2">
      <div class="col-6 col-md-3">
        <div class="mini-card">
          <div class="mini-title">Transacciones</div>
          <div class="mini-value" id="sumTx">0</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-card">
          <div class="mini-title">Total cobrado</div>
          <div class="mini-value" id="sumTotal">$0.00</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-card">
          <div class="mini-title">Efectivo</div>
          <div class="mini-value" id="sumCash">$0.00</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-card">
          <div class="mini-title">No efectivo</div>
          <div class="mini-value" id="sumNonCash">$0.00</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* ========= Demo front-end: filtro + sumatorias ========= */
(function(){
  const $ = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  const fDesde   = $('#fDesde');
  const fHasta   = $('#fHasta');
  const fCliente = $('#fCliente');
  const btnFil   = $('#btnFiltrar');

  // Setea fechas por defecto (hoy)
  const hoy = new Date();
  const y  = hoy.getFullYear();
  const m  = String(hoy.getMonth()+1).padStart(2,'0');
  const d  = String(hoy.getDate()).padStart(2,'0');
  const hoyStr = `${y}-${m}-${d}`;
  if(!fDesde.value) fDesde.value = hoyStr;
  if(!fHasta.value) fHasta.value = hoyStr;

  function visibleRows(){
    return $$('#tblCorte tbody tr').filter(r => r.style.display !== 'none');
  }

  function filtrar(){
    const d1 = fDesde.value ? new Date(fDesde.value) : null;
    const d2 = fHasta.value ? new Date(fHasta.value) : null;
    const q  = (fCliente.value || '').toLowerCase();

    $$('#tblCorte tbody tr').forEach(tr => {
      const cli  = tr.querySelector('.cli-name').textContent.toLowerCase();
      const fstr = tr.querySelector('.date-val').textContent.trim();
      const f    = new Date(fstr);

      const okCli = !q || cli.includes(q);
      const okDesde = !d1 || f >= d1;
      const okHasta = !d2 || f <= d2;

      tr.style.display = (okCli && okDesde && okHasta) ? '' : 'none';
    });

    // Actualiza contador
    const total = $$('#tblCorte tbody tr').length;
    const vis   = visibleRows().length;
    $('#lblCount').textContent = `Mostrando ${vis} de ${total} registros`;

    // Recalcula sumas
    recalcular();
  }

  function recalcular(){
    const rows = visibleRows();
    let total=0, tx=0, cash=0, nonCash=0;

    rows.forEach(tr=>{
      const amt = parseFloat(tr.querySelector('.amt-val').textContent) || 0;
      const met = tr.querySelector('.met-val').textContent.trim().toLowerCase();
      total += amt; tx += 1;
      if (met === 'efectivo') cash += amt; else nonCash += amt;
    });

    $('#sumTx').textContent = tx;
    $('#sumTotal').textContent = `$${total.toFixed(2)}`;
    $('#sumCash').textContent = `$${cash.toFixed(2)}`;
    $('#sumNonCash').textContent = `$${nonCash.toFixed(2)}`;
  }

  btnFil.addEventListener('click', filtrar);
  fDesde.addEventListener('change', filtrar);
  fHasta.addEventListener('change', filtrar);
  fCliente.addEventListener('input', filtrar);

  // Primera pasada
  filtrar();
})();
</script>
</body>
</html>

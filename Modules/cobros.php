<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cobros</title>

  <style>
  /* ===== Cobros (estático) ===== */
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
</head>
<body>

<div class="container-fluid cobros">
  <!-- Título + acciones -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Cobros</h3>

    <div class="d-flex align-items-center gap-2">
      <!-- “Mostrar” solo decorativo -->
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
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-6">
          <select id="cliSelect" class="form-select">
            <option value="">--Selecciona Cliente--</option>
            <option value="amner">Ismael Jose</option>
            <option value="amado saucedo">Omar Cano</option>
            <option value="dayana saucedo">Leonel Marquez</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button id="btnGo" class="btn btn-go"><i class="bi bi-search me-1"></i> Buscar!</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla -->
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
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <!-- Filas de ejemplo (estático) -->
          <tr>
            <td data-label="Folio"><a href="#" class="text-decoration-none">000047</a></td>
            <td data-label="Cliente" class="cli-name"><a href="#" class="text-decoration-none">Ismael Jose</a></td>
            <td data-label="Paquete">Web Service</td>
            <td data-label="Fecha" class="date-val">2019-01-05</td>
            <td data-label="Importe" class="amt-val">20.00</td>
            <td data-label="Método de pago" class="met-val">Efectivo</td>
            <td data-label="# Depósito">—</td>
            <td data-label="Acción" class="text-end">
              <button class="btn btn-sm btn-cancel">Cancelar</button>
            </td>
          </tr>

          <tr>
            <td data-label="Folio"><a href="#" class="text-decoration-none">000046</a></td>
            <td data-label="Cliente" class="cli-name"><a href="#" class="text-decoration-none">Omar Cano</a></td>
            <td data-label="Paquete">Studio</td>
            <td data-label="Fecha" class="date-val">2019-01-05</td>
            <td data-label="Importe" class="amt-val">45.00</td>
            <td data-label="Método de pago" class="met-val">Efectivo</td>
            <td data-label="# Depósito">—</td>
            <td data-label="Acción" class="text-end">
              <button class="btn btn-sm btn-cancel">Cancelar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación simulada -->
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted" id="lblCount">Mostrando 1 a 2 de 2 registros</small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>
</div>

<script>
/* ===== Filtro por cliente (front-end) + contador ===== */
(function(){
  const sel  = document.getElementById('cliSelect');
  const btn  = document.getElementById('btnGo');
  const rows = Array.from(document.querySelectorAll('#tblCobros tbody tr'));
  const lbl  = document.getElementById('lblCount');

  function visibleRows(){ return rows.filter(r => r.style.display !== 'none'); }

  function filtrar(){
    const q = (sel.value || '').trim().toLowerCase();
    rows.forEach(r => {
      const name = r.querySelector('.cli-name').textContent.trim().toLowerCase();
      r.style.display = !q || name.includes(q) ? '' : 'none';
    });
    const vis = visibleRows().length;
    lbl.textContent = `Mostrando ${vis} de ${rows.length} registros`;
  }

  sel.addEventListener('change', filtrar);
  btn.addEventListener('click', filtrar);
})();
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Clientes</title>

  <style>
  /* ===== Clientes (estático) ===== */
  .clientes .topbar{gap:.5rem;}
  .clientes .search-card .card-body{padding:1rem;}
  .clientes .search-card .form-control{height:44px;}
  .clientes .search-card .btn-go{
    height:44px;display:inline-flex;align-items:center;
    background:#fdd835;border-color:#fdd835;color:#000;
  }
  .clientes .search-card .btn-go:hover{filter:brightness(.95);}
  .clientes .card-header{font-weight:600;}

  /* Acciones (estilo clásico) */
  .clientes .btn-hist{background:#28a745;border-color:#28a745;color:#fff;}
  .clientes .btn-det {background:#17a2b8;border-color:#17a2b8;color:#fff;}
  .clientes .btn-edit{background:#f0ad4e;border-color:#f0ad4e;color:#111;}
  .clientes .btn-del {background:#dc3545;border-color:#dc3545;color:#fff;}
  .clientes .btn-sm  {padding:.25rem .5rem;}

  /* ===== Tabla normal (desktop) ===== */
  .clientes table {width:100%;}
  .clientes th, .clientes td {white-space:nowrap; vertical-align:middle;}

  /* ===== Versión móvil: fila apilada tipo tarjeta ===== */
  @media (max-width: 768px){
    /* Oculta encabezados */
    .clientes thead{position:absolute;left:-9999px;top:-9999px;}

    /* Cada fila es una "card" */
    .clientes table,
    .clientes tbody,
    .clientes tr,
    .clientes td {display:block;width:100%;}
    .clientes tbody{display:block;}
    .clientes tr{
      background:#fff;
      border:1px solid #e9ecef;
      border-radius:.5rem;
      padding:.5rem .75rem;
      margin-bottom:.75rem;
      box-shadow:0 1px 3px rgba(0,0,0,.04);
    }

    /* Cada celda: etiqueta a la izquierda, valor a la derecha */
    .clientes td{
      border:0;
      border-bottom:1px solid #f1f3f5;
      position:relative;
      padding:.5rem 0 .5rem 7.5rem;    /* espacio para la etiqueta */
      white-space:normal;              /* permite saltos de línea */
      text-align:right;
    }
    .clientes td:last-child{border-bottom:0;}

    .clientes td::before{
      content:attr(data-label);
      position:absolute;
      left:.75rem; top:.5rem;
      width:6.5rem;
      font-weight:600;
      color:#6b7280;
      text-align:left;
      white-space:normal;
    }

    /* Acciones: en fila, con wrap si hace falta */
    .clientes .actions{
      display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end;
    }

    /* Quita padding extra al contenedor Bootstrap si existiera */
    .clientes .table-wrap{overflow:visible;}
  }
  </style>
</head>
<body>

<div class="container-fluid clientes">
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Clientes</h3>

    <div class="d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">
          Mostrar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#">10</a></li>
          <li><a class="dropdown-item" href="#">25</a></li>
          <li><a class="dropdown-item" href="#">50</a></li>
        </ul>
      </div>

      <button class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Nuevo
      </button>
    </div>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <div class="input-group">
        <input id="cliQuery" type="text" class="form-control" placeholder="Nombre">
        <button id="cliGo" class="btn btn-go" type="button">
          <i class="bi bi-search me-1"></i> Buscar!
        </button>
      </div>
    </div>
  </div>

  <!-- Tabla / Cards responsive -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
      Historial de Clientes
    </div>

    <div class="table-wrap">
      <table class="table align-middle mb-0" id="cliTable">
        <thead class="table-light">
          <tr>
            <th>Cliente</th>
            <th>RFC</th>
            <th>Dirección</th>
            <th>Teléfono</th>
            <th>Fecha Mensualidad</th>
            <th>Servicio</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <!-- Filas de ejemplo -->
          <tr>
            <td data-label="Cliente" class="cli-name">Ismael Jose</td>
            <td data-label="RFC">54asdas46d5</td>
            <td data-label="Dirección">asdasd</td>
            <td data-label="Teléfono">6546543235453</td>
            <td data-label="Fecha Mensualidad">20/02/2019</td>
            <td data-label="Servicio">Marketing $2000.00</td>
            <td data-label="Estado"><span class="badge bg-success">Activo</span></td>
            <td data-label="Acciones">
              <div class="actions text-end">
                <button class="btn btn-sm btn-hist">Historial</button>
                <button class="btn btn-sm btn-det">Detalles</button>
                <button class="btn btn-sm btn-edit">Editar</button>
                <button class="btn btn-sm btn-del" onclick="fakeDelete('amner')">Eliminar</button>
              </div>
            </td>
          </tr>

          <tr>
            <td data-label="Cliente" class="cli-name">Omar</td>
            <td data-label="RFC">645646</td>
            <td data-label="Dirección">guatemala</td>
            <td data-label="Teléfono">498932133453</td>
            <td data-label="Fecha Mensualidad">12/01/2019</td>
            <td data-label="Servicio">Web Service $3000.00</td>
            <td data-label="Estado"><span class="badge bg-secondary">Inactivo</span></td>
            <td data-label="Acciones">
              <div class="actions text-end">
                <button class="btn btn-sm btn-hist">Historial</button>
                <button class="btn btn-sm btn-det">Detalles</button>
                <button class="btn btn-sm btn-edit">Editar</button>
                <button class="btn btn-sm btn-del" onclick="fakeDelete('Amner')">Eliminar</button>
              </div>
            </td>
          </tr>

          <tr>
            <td data-label="Cliente" class="cli-name">Leonel Marquez</td>
            <td data-label="RFC">351435431</td>
            <td data-label="Dirección">guatemala</td>
            <td data-label="Teléfono">0165132</td>
            <td data-label="Fecha Mensualidad">05/11/2018</td>
            <td data-label="Servicio">Studio $1000.00</td>
            <td data-label="Estado"><span class="badge bg-success">Activo</span></td>
            <td data-label="Acciones">
              <div class="actions text-end">
                <button class="btn btn-sm btn-hist">Historial</button>
                <button class="btn btn-sm btn-det">Detalles</button>
                <button class="btn btn-sm btn-edit">Editar</button>
                <button class="btn btn-sm btn-del" onclick="fakeDelete('Amado Saucedo')">Eliminar</button>
              </div>
            </td>
          </tr>

          <tr>
            <td data-label="Cliente" class="cli-name">dayana Lorena</td>
            <td data-label="RFC">34533132</td>
            <td data-label="Dirección">coban</td>
            <td data-label="Teléfono">49893213313</td>
            <td data-label="Fecha Mensualidad">01/12/2018</td>
            <td data-label="Servicio">Marketing $3000.00</td>
            <td data-label="Estado"><span class="badge bg-success">Activo</span></td>
            <td data-label="Acciones">
              <div class="actions text-end">
                <button class="btn btn-sm btn-hist">Historial</button>
                <button class="btn btn-sm btn-det">Detalles</button>
                <button class="btn btn-sm btn-edit">Editar</button>
                <button class="btn btn-sm btn-del" onclick="fakeDelete('dayana saucedo')">Eliminar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación simulada -->
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Mostrando 1 a 4 de 4 registros</small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>
</div>

<script>
  // Filtro por nombre (front-end)
  (function(){
    const q   = document.getElementById('cliQuery');
    const btn = document.getElementById('cliGo');
    const rows = Array.from(document.querySelectorAll('#cliTable tbody tr'));

    function filtrar(){
      const term = (q.value || '').toLowerCase().trim();
      rows.forEach(r => {
        const name = r.querySelector('.cli-name').textContent.toLowerCase();
        r.style.display = name.includes(term) ? '' : 'none';
      });
    }

    q.addEventListener('input', filtrar);
    btn.addEventListener('click', filtrar);
  })();

  function fakeDelete(name){
    alert('Solo demo (front-end): aquí confirmarías la eliminación de "'+name+'".');
  }
</script>

</body>
</html>

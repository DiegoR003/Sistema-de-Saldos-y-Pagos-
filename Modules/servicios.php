<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Servicios</title>
</head>

<style>
/* ===== Servicios (estático) ===== */
.servicios .topbar { gap: .5rem; }
.servicios .search-card .card-body { padding: 1rem; }
.servicios .search-card .form-control { height: 44px; }
.servicios .search-card .btn-go {
  height: 44px;
  display: inline-flex;
  align-items: center;
  background: #fdd835;       /* info */
  border-color: #0dcaf0;
  color: #fff;
}
.servicios .search-card .btn-go:hover {
  filter: brightness(.95);
}

.servicios .card-header { font-weight: 600; }
.servicios .table td, 
.servicios .table th { vertical-align: middle; }

/* Acciones estilo clásico */
.servicios .btn-detalle {
  background: #17a2b8; border-color:#17a2b8; color:#fff;
}
.servicios .btn-detalle:hover { filter: brightness(.95); }

.servicios .btn-editar {
  background: #f0ad4e; border-color:#f0ad4e; color:#111;
}
.servicios .btn-editar:hover { filter: brightness(.95); }

.servicios .btn-eliminar {
  background: #dc3545; border-color:#dc3545; color:#fff;
}
.servicios .btn-eliminar:hover { filter: brightness(.95); }

/* Botones compactos */
.servicios .btn-sm { padding: .25rem .5rem; }

</style>

<body>

<!-- Servicios -->
<div class="container-fluid servicios">
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Servicios</h3>

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
        <input id="srvQuery" type="text" class="form-control" placeholder="Servicio">
        <button id="srvGo" class="btn btn-go" type="button">
          <i class="bi bi-search me-1"></i> Buscar!
        </button>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
      Historial de Servicios
    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0" id="srvTable">
        <thead class="table-light">
          <tr>
            <th style="width:60%">Servicio</th>
            <th style="width:15%">Precio</th>
            <th style="width:25%" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <!-- Datos de ejemplo (estáticos) -->
          <tr>
            <td class="srv-name">Marketing</td>
            <td>$1500.00</td>
            <td class="text-end">
              <button class="btn btn-sm btn-detalle"><i class="bi bi-card-text me-1"></i> Detalles</button>
              <button class="btn btn-sm btn-editar"><i class="bi bi-pencil-square me-1"></i> Editar</button>
              <button class="btn btn-sm btn-eliminar" onclick="confirmDelete('Marketing')"><i class="bi bi-trash me-1"></i> Eliminar</button>
            </td>
          </tr>
          <tr>
            <td class="srv-name">Web Service</td>
            <td>$3000.00</td>
            <td class="text-end">
              <button class="btn btn-sm btn-detalle"><i class="bi bi-card-text me-1"></i> Detalles</button>
              <button class="btn btn-sm btn-editar"><i class="bi bi-pencil-square me-1"></i> Editar</button>
              <button class="btn btn-sm btn-eliminar" onclick="confirmDelete('WebService')"><i class="bi bi-trash me-1"></i> Eliminar</button>
            </td>
          </tr>
          <tr>
            <td class="srv-name">Studio</td>
            <td>$1000.00</td>
            <td class="text-end">
              <button class="btn btn-sm btn-detalle"><i class="bi bi-card-text me-1"></i> Detalles</button>
              <button class="btn btn-sm btn-editar"><i class="bi bi-pencil-square me-1"></i> Editar</button>
              <button class="btn btn-sm btn-eliminar" onclick="confirmDelete('Studio')"><i class="bi bi-trash me-1"></i> Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación simulada -->
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Mostrando 1 a 3 de 3 registros</small>
      <ul class="pagination pagination-sm m-0">
        <li class="page-item disabled"><span class="page-link">Anterior</span></li>
        <li class="page-item active"><span class="page-link">1</span></li>
        <li class="page-item disabled"><span class="page-link">Siguiente</span></li>
      </ul>
    </div>
  </div>
</div>

<script>
  // Filtro en vivo (front-end)
  (function(){
    const q   = document.getElementById('srvQuery');
    const btn = document.getElementById('srvGo');
    const rows = Array.from(document.querySelectorAll('#srvTable tbody tr'));

    function filtrar(){
      const term = (q.value || '').toLowerCase().trim();
      rows.forEach(r => {
        const name = r.querySelector('.srv-name').textContent.toLowerCase();
        r.style.display = name.includes(term) ? '' : 'none';
      });
    }

    q.addEventListener('input', filtrar);
    btn.addEventListener('click', filtrar);
  })();

  function confirmDelete(name){
    alert('Solo demo (front-end): aquí confirmarías la eliminación de "'+name+'".');
  }
</script>

</body>
</html>

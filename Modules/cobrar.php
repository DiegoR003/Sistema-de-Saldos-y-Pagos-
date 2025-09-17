<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobrar</title>
</head>

<style>
    /* ===== Cobrar (estático) ===== */
.cobrar .search-card .card-body { padding: 1rem; }
.cobrar .search-card .form-control { height: 44px; }
.cobrar .search-card .btn-search {
  height: 44px;
  display: inline-flex;
  align-items: center;
  background: #e74c3c;
  border-color: #e74c3c;
}
.cobrar .search-card .btn-search:hover {
  background: #d83e2e;
  border-color: #d83e2e;
}

.cobrar .cliente-card .card-body { padding: 1.25rem; }
.cobrar .cliente-card .link-primary { font-weight: 600; }
.cobrar .cliente-card .badge { font-weight: 600; font-size: .75rem; }

</style>
<body>
     

    <!-- Cobrar — Control panel (estático) -->
<div class="container-fluid cobrar">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0 fw-semibold">Cobrar <span class="text-muted fs-6">Control panel</span></h3>

    <!-- "Mostrar" placeholder (dropdown opcional) -->
    <div class="dropdown">
      <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Mostrar
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#">Todos</a></li>
        <li><a class="dropdown-item" href="#">Solo vencidos</a></li>
        <li><a class="dropdown-item" href="#">Pagados</a></li>
      </ul>
    </div>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <div class="input-group">
        <input type="text" class="form-control" placeholder="Buscar por nombre o teléfono…">
        <button class="btn btn-danger btn-search" type="button">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </div>
    </div>
  </div>

  <!-- Resultado: cliente -->
  <div class="card border-0 shadow-sm cliente-card">
    <div class="card-body">
      <a href="#" class="h5 link-primary d-inline-block mb-2">Josue Martinez</a>

      <div class="text-muted">
        <div>Dirección: Los Pirules, Col. Las Veredas</div>
        <div>Teléfono:+52 6242131373</div>
        <div>
          Servicio contratado: 
          <a href="#" class="link-primary text-decoration-none">Web Services</a> 
          <span class="text-muted">$45.00</span>
        </div>
        <div class="mt-1">
          Estado de pago: 
          <span class="badge rounded-pill bg-danger">Vencida</span>
        </div>
      </div>
    </div>
  </div>
</div>


</body>
</html>
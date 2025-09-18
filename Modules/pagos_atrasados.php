<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pagos atrasados</title>

  <style>
  /* ===== Pagos atrasados (estático) ===== */
  .overdues .topbar{ gap:.5rem; }
  .overdues .search-card .card-body{ padding:1rem; }
  .overdues .search-card .form-control{ height:44px; }
  .overdues .search-card .btn-search{
    height:44px; display:inline-flex; align-items:center;
    background:#e74c3c; border-color:#e74c3c; color:#fff;
  }
  .overdues .search-card .btn-search:hover{ filter:brightness(.95); }

  .overdues .cliente-card{ border-left:4px solid #fde68a; }
  .overdues .cliente-card .card-body{ padding:1.1rem; }
  .overdues .cliente-card .name{ font-weight:700; font-size:1.05rem; }
  .overdues .cliente-card .meta{ color:#6b7280; }
  .overdues .badge-overdue{
    background:#dc3545; color:#fff; font-weight:700; font-size:.75rem;
    border-radius:999px; padding:.25rem .6rem;
  }
  .overdues .actions .btn{ padding:.35rem .6rem; }
  .overdues .btn-cobrar{ background:#28a745; border-color:#28a745; color:#fff; }
  .overdues .btn-det{ background:#17a2b8; border-color:#17a2b8; color:#fff; }
  </style>
</head>
<body>

<div class="container-fluid overdues">
  <!-- Título + "Mostrar" -->
  <div class="d-flex align-items-center justify-content-between flex-wrap topbar mb-3">
    <h3 class="mb-0 fw-semibold">Pagos atrasados <span class="text-muted fs-6">Control panel</span></h3>

    <div class="dropdown">
      <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" type="button">
        Mostrar
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#">Todos</a></li>
        <li><a class="dropdown-item" href="#">Últimos 7 días</a></li>
        <li><a class="dropdown-item" href="#">Últimos 30 días</a></li>
      </ul>
    </div>
  </div>

  <!-- Buscador -->
  <div class="card border-0 shadow-sm search-card mb-3">
    <div class="card-body">
      <div class="input-group">
        <input id="qOver" type="text" class="form-control" placeholder="Buscar por nombre o teléfono…">
        <button id="btnSearchOver" class="btn btn-search" type="button">
          <i class="bi bi-search me-1"></i> Buscar
        </button>
      </div>
    </div>
  </div>

  <!-- Resultado: tarjetas de clientes vencidos (ejemplo estático) -->
  <div class="card border-0 shadow-sm mb-3 cliente-card"
       data-name="dayana saucedo" data-phone="4989321313">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
        <div>
          <div class="name mb-1">Dayana Lorena</div>
          <div class="meta">
            <div>Dirección: <span class="text-dark">coban</span></div>
            <div>Teléfono: <span class="text-dark">4989321313</span></div>
            <div>Servicio contratado:
              <span class="text-decoration-none">Claro</span>
              <span class="text-muted">$30.00</span>
            </div>
            <div class="mt-1">Estado de pago: <span class="badge-overdue">Vencida</span></div>
          </div>
        </div>

        <div class="actions d-flex align-items-start gap-2">
          <button class="btn btn-sm btn-cobrar">
            <i class="bi bi-cash-coin me-1"></i> Cobrar
          </button>
          <button class="btn btn-sm btn-det">
            <i class="bi bi-person-badge me-1"></i> Detalles Cliente
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3 cliente-card"
       data-name="amado saucedo" data-phone="0165132">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
        <div>
          <div class="name mb-1">Ismael Jose</div>
          <div class="meta">
            <div>Dirección: <span class="text-dark">guatemala</span></div>
            <div>Teléfono: <span class="text-dark">0165132</span></div>
            <div>Servicio contratado:
              <span class="text-decoration-none">Service Web</span>
              <span class="text-muted">$3000.00</span>
            </div>
            <div class="mt-1">Estado de pago: <span class="badge-overdue">Vencida</span></div>
          </div>
        </div>

        <div class="actions d-flex align-items-start gap-2">
          <button class="btn btn-sm btn-cobrar">
            <i class="bi bi-cash-coin me-1"></i> Cobrar
          </button>
          <button class="btn btn-sm btn-det">
            <i class="bi bi-person-badge me-1"></i> Detalles Cliente
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Ejemplo extra -->
  <div class="card border-0 shadow-sm mb-3 cliente-card"
       data-name="josue martinez" data-phone="+526242131373">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
        <div>
          <div class="name mb-1">Josue Martinez</div>
          <div class="meta">
            <div>Dirección: <span class="text-dark">Los Pirules, Col. Las Veredas</span></div>
            <div>Teléfono: <span class="text-dark">+52 6242132323</span></div>
            <div>Servicio contratado:
              <span class="text-decoration-none">Marketing</span>
              <span class="text-muted">$1500.00</span>
            </div>
            <div class="mt-1">Estado de pago: <span class="badge-overdue">Vencida</span></div>
          </div>
        </div>

        <div class="actions d-flex align-items-start gap-2">
          <button class="btn btn-sm btn-cobrar">
            <i class="bi bi-cash-coin me-1"></i> Cobrar
          </button>
          <button class="btn btn-sm btn-det">
            <i class="bi bi-person-badge me-1"></i> Detalles Cliente
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* Filtro simple por nombre o teléfono (front-end) */
(function(){
  const q = document.getElementById('qOver');
  const btn = document.getElementById('btnSearchOver');
  const cards = Array.from(document.querySelectorAll('.overdues .cliente-card'));

  function filtrar(){
    const term = (q.value || '').toLowerCase().trim();
    cards.forEach(c => {
      const name  = (c.getAttribute('data-name')  || '').toLowerCase();
      const phone = (c.getAttribute('data-phone') || '').toLowerCase();
      c.style.display = (!term || name.includes(term) || phone.includes(term)) ? '' : 'none';
    });
  }
  q.addEventListener('input', filtrar);
  btn.addEventListener('click', filtrar);
})();
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    
</head>
<style>

 /* Evita overflow lateral cuando hay sidebar */
    .main, .dashboard, .container-fluid { min-width: 0; }

    /* ===== Dashboard Inicio ===== */
    .dashboard .card-stat { color:#fff; border-radius:.5rem; }
    .dashboard .card-stat .card-body { padding:1.1rem 1.15rem; }
    .dashboard .stat-value { font-size:3rem; line-height:1; font-weight:700; }
    .dashboard .stat-label { margin-top:.25rem; font-weight:500; }
    .dashboard .stat-link { display:inline-flex; gap:.4rem; margin-top:.35rem; color:rgba(255,255,255,.9); text-decoration:none; }
    .dashboard .stat-link:hover { color:#fff; }
    .dashboard .stat-blue   { background:#0ea5e9; }
    .dashboard .stat-green  { background:#16a34a; }
    .dashboard .stat-orange { background:#f59e0b; }
    .dashboard .stat-red    { background:#ef4444; }

    /* Grilla responsiva simple (1-2-4 columnas) */
    .dashboard .stats-grid { /* usa row-cols para que adapte solo */
    }

    /* Gráfica con alto fluido por viewport */
    .chart-wrap { position:relative; height:360px; }
    @media (max-width:1199.98px){ .chart-wrap{ height:320px; } }
    @media (max-width:991.98px) { .chart-wrap{ height:300px; } }
    @media (max-width:767.98px) { .chart-wrap{ height:260px; } }
    @media (max-width:575.98px) { 
      .chart-wrap{ height:220px; }
      .dashboard .stat-value{ font-size:2.3rem; }
      .dashboard .card-stat .card-body{ padding:.9rem 1rem; }
    }

</style>
<body>

<div class="container-fluid dashboard  py-3">
  <h3 class="mb-3 fw-semibold topbar">Dashboard <span class="text-muted fs-6">Control panel</span></h3>

  <!-- Tarjetas: 1 col en móvil, 2 en sm, 4 en xl -->
  <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3 stats-grid">
    <div class="col">
      <div class="card card-stat stat-blue shadow-sm border-0">
        <div class="card-body">
          <div class="stat-value">3</div>
          <div class="stat-label">Usuarios</div>
          <a href="#" class="stat-link">Detalles <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card card-stat stat-green shadow-sm border-0">
        <div class="card-body">
          <div class="stat-value">4</div>
          <div class="stat-label">Clientes</div>
          <a href="#" class="stat-link">Detalles <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card card-stat stat-orange shadow-sm border-0">
        <div class="card-body">
          <div class="stat-value">3</div>
          <div class="stat-label">Servicios</div>
          <a href="#" class="stat-link">Detalles <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card card-stat stat-red shadow-sm border-0">
        <div class="card-body">
          <div class="stat-value">65.00</div>
          <div class="stat-label">Total Ingresos</div>
          <a href="#" class="stat-link">Detalles <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
    </div>
  </div>

  <!-- Gráfica -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <span class="fw-semibold">Ingresos por Mes</span>
    </div>
    <div class="card-body">
      <div class="chart-wrap">
        <canvas id="chartIngresos"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const ctx = document.getElementById('chartIngresos');
  const labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  const data = [12,8,15,9,13,18,22,17,25,19,21,24];

  const chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Ingresos',
        data,
        backgroundColor: 'rgba(14,165,233,.35)',
        borderColor: 'rgba(14,165,233,.9)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false, // clave para que el alto siga a .chart-wrap
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      },
      plugins: { legend: { display: false } }
    }
  });

  // ayuda en cambios bruscos de viewport
  addEventListener('orientationchange', () => setTimeout(() => chart.resize(), 200));
})();
</script>
</body>
</html>
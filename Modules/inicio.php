<?php
// Modules/inicio.php
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';

$pdo = db();
$u = current_user();
if (!$u) exit('No autorizado');

// --- 1. OBTENER ROL REAL DESDE BD (Corrección Clave) ---
// No confiamos solo en la sesión, preguntamos a la BD qué rol tiene este ID
$stRol = $pdo->prepare("
    SELECT r.nombre 
    FROM usuarios u
    JOIN usuario_rol ur ON ur.usuario_id = u.id
    JOIN roles r ON r.id = ur.rol_id
    WHERE u.id = ?
    LIMIT 1
");
$stRol->execute([$u['id']]);
$rolNombre = $stRol->fetchColumn();
$rol = strtolower($rolNombre ?: ''); // 'admin', 'operador', etc.


// --- 2. CONFIGURACIÓN DEL FILTRO DE AÑO ---
// Busca automáticamente todos los años que tengan cargos registrados
$sqlYears = "SELECT DISTINCT YEAR(periodo_inicio) as y FROM cargos WHERE periodo_inicio IS NOT NULL ORDER BY y DESC";
$years = $pdo->query($sqlYears)->fetchAll(PDO::FETCH_COLUMN);

// Si no hay datos, mostramos el año actual por defecto
if (empty($years)) $years = [date('Y')];

// Año seleccionado por el usuario (o el actual)
$year = (int)($_GET['y'] ?? date('Y'));
if (!in_array($year, $years)) $year = $years[0];


// --- 3. CONSULTAS KPIs (TARJETAS) ---

// A. Usuarios (Solo visible para Admin)
$cntUsuarios = 0;
if ($rol === 'admin') {
    $cntUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
}

// B. Clientes Activos (Todos)
$cntClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();

// C. Total Ingresos (Todos - Histórico de Pagados)
$totalIngresos = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM cargos WHERE estatus = 'pagado'")->fetchColumn();


// --- 4. DATOS PARA LA GRÁFICA (Filtrados por $year) ---
$sqlChart = "
    SELECT MONTH(periodo_inicio) as mes, SUM(total) as total 
    FROM cargos 
    WHERE estatus = 'pagado' 
      AND YEAR(periodo_inicio) = ? 
    GROUP BY mes
";
$stChart = $pdo->prepare($sqlChart);
$stChart->execute([$year]);
$chartDataRaw = $stChart->fetchAll(PDO::FETCH_KEY_PAIR); // [mes => total]

// Rellenar array de 12 meses (Ene-Dic)
$dataMeses = [];
for ($m = 1; $m <= 12; $m++) {
    $dataMeses[] = (float)($chartDataRaw[$m] ?? 0);
}
$jsonChartData = json_encode($dataMeses);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<style>
    .dashboard .card-stat { color:#fff; border-radius:.75rem; transition: transform 0.2s; border:none; position: relative; overflow: hidden; }
    .dashboard .card-stat:hover { transform: translateY(-3px); }
    .dashboard .card-stat .card-body { padding:1.5rem; position: relative; z-index: 2; }
    
    /* Icono de fondo decorativo */
    .dashboard .bg-icon {
        position: absolute; right: -10px; bottom: -10px; font-size: 5rem;
        opacity: 0.15; transform: rotate(-15deg); pointer-events: none; z-index: 1;
    }

    .stat-value { font-size: 2.5rem; font-weight: 800; line-height: 1; margin-bottom: 5px; }
    .stat-label { font-size: 1rem; font-weight: 500; opacity: 0.9; }
    
    .stat-link { 
        display: inline-flex; align-items: center; gap: 5px; margin-top: 15px; 
        color: #fff; text-decoration: none; font-size: 0.85rem; font-weight: 600;
        background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px;
        transition: background 0.2s;
    }
    .stat-link:hover { background: rgba(255,255,255,0.3); color: #fff; }

    /* Colores */
    .stat-blue   { background: linear-gradient(135deg, #0ea5e9, #2563eb); }
    .stat-green  { background: linear-gradient(135deg, #16a34a, #15803d); }
    .stat-red    { background: linear-gradient(135deg, #ef4444, #b91c1c); }

    .chart-wrap { position:relative; height:350px; width: 100%; }
    
    /* Filtro de año */
    .year-select {
        border: 1px solid #e2e8f0; background-color: #fff; 
        font-weight: 600; color: #64748b; padding: 5px 10px; border-radius: 8px;
        cursor: pointer; outline: none;
    }
    .year-select:hover { border-color: #cbd5e1; }
</style>

<div class="container-fluid dashboard py-4">
  
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
          <h3 class="fw-bold m-0">Dashboard</h3>
          <span class="text-muted small">Vista General</span>
      </div>
      
      <form method="get" id="formYear" class="d-flex align-items-center">
          <input type="hidden" name="m" value="inicio">
          <div class="d-flex align-items-center gap-2 bg-white p-1 rounded shadow-sm border">
              <span class="text-muted small fw-bold ps-2">Año:</span>
              <select name="y" class="year-select form-select-sm border-0" onchange="document.getElementById('formYear').submit()">
                  <?php foreach($years as $y): ?>
                      <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
      </form>
  </div>

  <div class="row g-4 mb-4">
    
    <?php if ($rol === 'admin'): ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card card-stat stat-blue h-100 shadow-sm">
        <i class="bi bi-people-fill bg-icon"></i>
        <div class="card-body">
          <div class="stat-value"><?= $cntUsuarios ?></div>
          <div class="stat-label">Usuarios Sistema</div>
          <a href="?m=usuarios" class="stat-link">Administrar <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-12 col-md-6 col-xl-4">
      <div class="card card-stat stat-green h-100 shadow-sm">
        <i class="bi bi-building-fill bg-icon"></i>
        <div class="card-body">
          <div class="stat-value"><?= $cntClientes ?></div>
          <div class="stat-label">Clientes Activos</div>
          <a href="?m=clientes" class="stat-link">Ver directorio <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-4">
      <div class="card card-stat stat-red h-100 shadow-sm">
        <i class="bi bi-currency-dollar bg-icon"></i>
        <div class="card-body">
          <div class="stat-value" style="font-size: 2.2rem;">
            $<?= number_format($totalIngresos, 2) ?>
          </div>
          <div class="stat-label">Ingresos Totales (Histórico)</div>
          <a href="?m=corte_diario" class="stat-link">Ver reporte <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
    
    </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 border-bottom-0 d-flex align-items-center justify-content-between">
      <h5 class="fw-bold m-0 text-dark">
          <i class="bi bi-bar-chart-fill text-primary me-2"></i>Ingresos Mensuales (Pagados)
      </h5>
      <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">Año <?= $year ?></span>
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
  const dataValues = <?= $jsonChartData ?>; 
  const labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

  // Crear gradiente azul
  const gradient = document.createElement('canvas').getContext('2d').createLinearGradient(0, 0, 0, 400);
  gradient.addColorStop(0, 'rgba(14, 165, 233, 0.5)'); 
  gradient.addColorStop(1, 'rgba(14, 165, 233, 0.0)'); 

  new Chart(ctx, {
    type: 'line', 
    data: {
      labels,
      datasets: [{
        label: 'Ingresos ($)',
        data: dataValues,
        backgroundColor: gradient,
        borderColor: '#0ea5e9',
        borderWidth: 3,
        pointBackgroundColor: '#fff',
        pointBorderColor: '#0ea5e9',
        pointRadius: 5,
        pointHoverRadius: 7,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) label += ': ';
              if (context.parsed.y !== null) {
                label += new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(context.parsed.y);
              }
              return label;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { borderDash: [2, 4], color: '#f0f0f0' },
          ticks: {
            callback: function(value) {
                if(value >= 1000) return '$' + value / 1000 + 'k';
                return '$' + value;
            }
          }
        },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>
</html>
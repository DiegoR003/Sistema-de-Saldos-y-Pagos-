<?php
$showLogout = false;

if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}
$current = $_GET['m'] ?? 'inicio';

$menu_top = [
  ['slug'=>'inicio',   'icon'=>'bi-house',   'text'=>'Inicio'],
  ['slug'=>'cobrar',   'icon'=>'bi-cash',    'text'=>'Cobrar'],
];

$menu_cat = [
  ['slug'=>'cotizaciones','icon'=>'bi-grid',    'text'=>'Cotizaciones'],
  ['slug'=>'clientes', 'icon'=>'bi-people',  'text'=>'Clientes'],
];

$menu_rep = [
  ['slug'=>'cobros',          'icon'=>'bi-receipt',        'text'=>'Cobros'],
  ['slug'=>'corte_diario',    'icon'=>'bi-calendar-check', 'text'=>'Corte Diario'],
  ['slug'=>'pagos_atrasados', 'icon'=>'bi-bell',           'text'=>'Pagos Atrasados'],
];

$menu_conf = [
  ['slug'=>'usuarios','icon'=>'bi-person-gear','text'=>'Mi Perfil'],
];
?>

<div class="d-flex">
  <!-- Sidebar fijo (desktop) -->
  <aside class="sidebar d-none d-lg-flex flex-column">
    <div class="profile px-3 py-3 d-flex align-items-center gap-3">
      <div class="avatar"><i class="bi bi-person-fill"></i></div>
      <div class="small text-dark">
        <div class="fw-semibold">Leonel Pimentel Agundez</div>
        <div class="d-flex align-items-center gap-2">
          <span class="status-dot"></span><span class="opacity-75">En Línea</span>
        </div>
      </div>
    </div>

    <div class="sidebar-body flex-grow-1">
      <div class="section-title">Menú de Navegación</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_top as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Catálogos</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_cat as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Reportes</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_rep as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Configuración</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_conf as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>"
             href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <?php if ($showLogout): ?>
        <div class="mt-4 px-2">
          <a class="nav-link side-item text-warning" href="<?= BASE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
          </a>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <!-- Sidebar móvil (offcanvas) -->
  <div class="offcanvas offcanvas-start sidebar-off" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header topbar text-white">
     <!-- <h5 class="offcanvas-title m-0" id="mobileSidebarLabel">Banana Group<span class="brand-emp">MX</span></h5> -->
        <!-- Logo fijo en la  esquina -->
     <a class="login-logo" href="/">
  <img src="./assets/logo.png" alt="Banana Group">
    </a>
      </a>
      <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0">
      <div class="profile px-3 py-3 d-flex align-items-center gap-3">
        <div class="avatar"><i class="bi bi-person-fill"></i></div>
        <div class="small">
          <div class="fw-semibold">Leonel Pimentel Agundez</div>
          <div class="d-flex align-items-center gap-2">
            <span class="status-dot"></span><span class="opacity-75">Activo</span>
          </div>
        </div>
      </div>

      <div class="section-title px-3">Menú de Navegación</div>
      <div class="list-group list-group-flush">
        <?php
          $groups = [ $menu_top, $menu_cat, $menu_rep, $menu_conf ];
          foreach ($groups as $i => $group):
            foreach ($group as $it):
        ?>
              <a class="list-group-item list-group-item-action <?= $current === $it['slug'] ? 'active' : '' ?>"
                 href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>"
                 data-bs-dismiss="offcanvas">
                <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
              </a>
        <?php
            endforeach;
            if ($i < count($groups)-1) echo '<div class="border-top"></div>';
          endforeach;
        ?>
      </div>
    </div>
  </div>

  <main class="main flex-grow-1 p-3 p-md-4">

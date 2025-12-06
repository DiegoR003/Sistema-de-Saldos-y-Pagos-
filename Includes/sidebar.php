<?php
// Includes/sidebar.php

// 1. Asegurar sesión y dependencias
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';

// 2. Configuración base
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}
$current = $_GET['m'] ?? 'inicio';

// 3. OBTENER DATOS FRESCOS DEL USUARIO (Foto y Nombre)
$sideUser = current_user();
// Intentamos sacar el ID de donde se pueda
$sideId   = (int)($sideUser['id'] ?? $_SESSION['usuario_id'] ?? 0);

// Valores por defecto
$sideName = $sideUser['nombre'] ?? 'Usuario';
$sideFoto = ''; // Empezamos sin foto
$sideRol  = ucfirst($sideUser['rol'] ?? 'Invitado');

// Consultamos a la BD para tener la foto REAL al momento
if ($sideId > 0) {
    try {
        $pdoSide = db(); // Usamos la conexión global
        $stSide = $pdoSide->prepare("SELECT nombre, foto_url FROM usuarios WHERE id = ? LIMIT 1");
        $stSide->execute([$sideId]);
        $rowSide = $stSide->fetch(PDO::FETCH_ASSOC);
        
        if ($rowSide) {
            $sideName = $rowSide['nombre']; // Nombre fresco
            $sideFoto = $rowSide['foto_url']; // Foto fresca
        }
    } catch (Exception $e) {
        // Si falla la BD, nos quedamos con los datos de sesión, no pasa nada
    }
}

// 4. Definición de Menús
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
  <aside class="sidebar d-none d-lg-flex flex-column">
    <div class="profile px-3 py-3 d-flex align-items-center gap-3">
      
      <div class="avatar" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center;">
        <?php if (!empty($sideFoto)): ?>
          <img src="<?= htmlspecialchars($sideFoto) ?>?v=<?= time() ?>" alt="User" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <i class="bi bi-person-fill fs-4 text-secondary"></i>
        <?php endif; ?>
      </div>

      <div class="small text-dark">
        <div class="fw-semibold text-truncate" style="max-width: 140px;"><?= htmlspecialchars($sideName) ?></div>
        <div class="d-flex align-items-center gap-2">
          <span class="status-dot bg-success rounded-circle" style="width: 8px; height: 8px;"></span>
          <span class="opacity-75" style="font-size: 0.85rem;"><?= htmlspecialchars($sideRol) ?></span>
        </div>
      </div>
    </div>

    <div class="sidebar-body flex-grow-1">
      <div class="section-title">Menú de Navegación</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_top as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Catálogos</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_cat as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Reportes</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_rep as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="section-title mt-3">Configuración</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_conf as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      
      <div class="mt-auto px-3 pb-3">
         <a class="nav-link side-item text-danger" href="<?= BASE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
         </a>
      </div>
    </div>
  </aside>

  <div class="offcanvas offcanvas-start sidebar-off" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header topbar text-white">
      <a class="login-logo" href="/"><img src="./assets/logo.png" alt="Banana Group" style="height: 40px;"></a>
      <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
      <div class="profile px-3 py-3 d-flex align-items-center gap-3">
        
        <div class="avatar" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center;">
          <?php if (!empty($sideFoto)): ?>
            <img src="<?= htmlspecialchars($sideFoto) ?>?v=<?= time() ?>" alt="User" style="width: 100%; height: 100%; object-fit: cover;">
          <?php else: ?>
            <i class="bi bi-person-fill fs-4 text-secondary"></i>
          <?php endif; ?>
        </div>

        <div class="small">
          <div class="fw-semibold"><?= htmlspecialchars($sideName) ?></div>
          <div class="d-flex align-items-center gap-2">
            <span class="status-dot bg-success rounded-circle" style="width: 8px; height: 8px;"></span>
            <span class="opacity-75"><?= htmlspecialchars($sideRol) ?></span>
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
                 href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
                <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
              </a>
        <?php
            endforeach;
            if ($i < count($groups)-1) echo '<div class="border-top"></div>';
          endforeach;
        ?>
        <a class="list-group-item list-group-item-action text-danger mt-2" href="<?= BASE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
        </a>
      </div>
    </div>
  </div>

  <main class="main flex-grow-1 p-3 p-md-4">
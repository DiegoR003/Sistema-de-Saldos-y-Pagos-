<?php
// Includes/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Cargar dependencias necesarias
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';

// 2. Configuración de URL base
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}
$current = $_GET['m'] ?? 'inicio';

// 3. OBTENER USUARIO Y ROL REAL (Consulta a BD)
$currentUser = current_user();
$sideId      = (int)($currentUser['id'] ?? $_SESSION['usuario_id'] ?? 0);

// Valores por defecto
$sideName = $currentUser['nombre'] ?? 'Usuario';
$sideFoto = '';
$sideRol  = 'guest';

if ($sideId > 0) {
    try {
        $pdoSide = db();
        // Buscamos nombre, foto Y EL NOMBRE DEL ROL
        $stSide = $pdoSide->prepare("
            SELECT u.nombre, u.foto_url, r.nombre as rol_nombre
            FROM usuarios u
            LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
            LEFT JOIN roles r ON r.id = ur.rol_id
            WHERE u.id = ? 
            LIMIT 1
        ");
        $stSide->execute([$sideId]);
        $rowSide = $stSide->fetch(PDO::FETCH_ASSOC);
        
        if ($rowSide) {
            $sideName = $rowSide['nombre'];
            $sideFoto = $rowSide['foto_url'];
            $sideRol  = strtolower(trim($rowSide['rol_nombre'] ?? 'guest'));
        }
    } catch (Exception $e) { }
}

// 4. DEFINIR MENÚS SEGÚN EL ROL DETECTADO
if ($sideRol === 'cliente') {
    // === MENÚ CLIENTE ===
    $menu_top = [
        ['slug'=>'cliente_home',  'icon'=>'bi-speedometer2', 'text'=>'Mi Resumen'],
        // ['slug'=>'cliente_pagos', 'icon'=>'bi-credit-card',  'text'=>'Mis Pagos'], // Descomenta cuando crees el archivo
    ];
    $menu_cat = []; // Vacío
    $menu_rep = []; // Vacío
    $menu_conf= [
        ['slug'=>'usuarios', 'icon'=>'bi-person-circle', 'text'=>'Mi Perfil']
    ];

} else {
    // === MENÚ ADMIN / OPERADOR ===
    $menu_top = [
        ['slug'=>'inicio',   'icon'=>'bi-house',   'text'=>'Inicio'],
        ['slug'=>'cobrar',   'icon'=>'bi-cash',    'text'=>'Cobrar'],
    ];
    $menu_cat = [
        ['slug'=>'cotizaciones','icon'=>'bi-grid',    'text'=>'Cotizaciones'],
        ['slug'=>'clientes',    'icon'=>'bi-people',  'text'=>'Clientes'],
    ];
    $menu_rep = [
        ['slug'=>'cobros',          'icon'=>'bi-receipt',        'text'=>'Cobros'],
        ['slug'=>'corte_diario',    'icon'=>'bi-calendar-check', 'text'=>'Corte Diario'],
        ['slug'=>'pagos_atrasados', 'icon'=>'bi-bell',           'text'=>'Pagos Atrasados'],
    ];
    $menu_conf = [
        ['slug'=>'usuarios','icon'=>'bi-person-gear','text'=>'Mi Perfil'],
    ];
}
?>

<div class="d-flex">
  <aside class="sidebar d-none d-lg-flex flex-column">
    <div class="profile px-3 py-3 d-flex align-items-center gap-3">
      <div class="avatar" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #eee; display: flex; align-items: center; justify-content: center;">
        <?php if (!empty($sideFoto)): ?>
          <img src="<?= htmlspecialchars($sideFoto) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <i class="bi bi-person-fill fs-4 text-secondary"></i>
        <?php endif; ?>
      </div>
      <div class="small text-dark">
        <div class="fw-semibold text-truncate" style="max-width: 140px;"><?= htmlspecialchars($sideName) ?></div>
        <div class="d-flex align-items-center gap-2">
          <span class="status-dot bg-success rounded-circle" style="width: 8px; height: 8px;"></span>
          <span class="opacity-75 text-capitalize" style="font-size: 0.85rem;"><?= htmlspecialchars($sideRol) ?></span>
        </div>
      </div>
    </div>

    <div class="sidebar-body flex-grow-1">
      <div class="section-title">Navegación</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_top as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <?php if (!empty($menu_cat)): ?>
      <div class="section-title mt-3">Catálogos</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_cat as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>

      <?php if (!empty($menu_rep)): ?>
      <div class="section-title mt-3">Reportes</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_rep as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>

      <div class="section-title mt-3">Configuración</div>
      <nav class="nav flex-column">
        <?php foreach ($menu_conf as $it): ?>
          <a class="nav-link side-item <?= $current === $it['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
      
     
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
            <img src="<?= htmlspecialchars($sideFoto) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
          <?php else: ?>
            <i class="bi bi-person-fill fs-4 text-secondary"></i>
          <?php endif; ?>
        </div>
        <div class="small">
          <div class="fw-semibold"><?= htmlspecialchars($sideName) ?></div>
          <div class="d-flex align-items-center gap-2">
            <span class="status-dot bg-success rounded-circle" style="width: 8px; height: 8px;"></span>
            <span class="opacity-75 text-capitalize"><?= htmlspecialchars($sideRol) ?></span>
          </div>
        </div>
      </div>

      <div class="list-group list-group-flush">
        <?php 
          $allMenus = array_merge($menu_top, $menu_cat, $menu_rep, $menu_conf);
          foreach ($allMenus as $it): 
        ?>
          <a class="list-group-item list-group-item-action <?= $current === $it['slug'] ? 'active' : '' ?>" 
             href="<?= BASE_URL ?>/index.php?m=<?= $it['slug'] ?>">
            <i class="bi <?= $it['icon'] ?> me-2"></i><?= $it['text'] ?>
          </a>
        <?php endforeach; ?>
        
        
      </div>
    </div>
  </div>

  <main class="main flex-grow-1 p-3 p-md-4">
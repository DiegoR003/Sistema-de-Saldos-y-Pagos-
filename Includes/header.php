<?php
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}

// Obtener usuario actual
require_once __DIR__ . '/../App/auth.php';
$currentUser = current_user();
$userName = $currentUser['nombre'] ?? 'Usuario';
$userInitial = mb_substr($userName, 0, 1, 'UTF-8');

// Notificaciones (simuladas por ahora - luego conectas con BD)
$notificaciones = [
  ['texto' => 'Nuevo pago recibido de Dolcevilla', 'hace' => 'Hace 5 min', 'leida' => false],
  ['texto' => 'Cotización pendiente de aprobar', 'hace' => 'Hace 1 hora', 'leida' => false],
  ['texto' => 'Recordatorio: Factura vence mañana', 'hace' => 'Hace 3 horas', 'leida' => true],
];
$notifCount = count(array_filter($notificaciones, fn($n) => !$n['leida']));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Banana Group</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css?v=999" rel="stylesheet">
</head>

<style>
/* Logo */
.navbar-brand img { height: 50px; }

/* Botón usuario pill */
.user-pill {
  --bs-btn-color: #1a1a1a;
  --bs-btn-bg: rgba(0,0,0,.08);
  --bs-btn-border-color: transparent;
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: #fdd835;
  --bs-btn-hover-border-color: transparent;
  transition: all .2s ease;
  font-size: 0.9rem;
  padding: 0.4rem 1rem;
}
.user-pill:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.1);
}
.user-pill .avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f39c12, #e74c3c);
  color: white;
  font-weight: 600;
  font-size: 0.85rem;
}

/* Dropdown usuario */
.user-menu {
  min-width: 200px !important;
  background: #fffbea;
  border-color: rgba(0,0,0,.1);
}
.user-menu .dropdown-item:hover {
  background: #fdd835;
  color: #000;
}

/* Campanita de notificaciones */
.notif-bell {
  position: relative;
  background: rgba(0,0,0,.08);
  border: none;
  border-radius: 50%;
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .2s ease;
  color: #333;
}
.notif-bell:hover {
  background: #fdd835;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,.1);
}
.notif-bell::after {
  display: none !important; /* Quita la flechita del dropdown */
}
.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #e74c3c;
  color: white;
  border-radius: 10px;
  padding: 2px 6px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}
.notif-menu {
  min-width: 340px !important;
  max-height: 400px;
  overflow-y: auto;
}
.notif-item {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgba(0,0,0,.05);
  transition: background .2s;
  cursor: pointer;
}
.notif-item:hover {
  background: rgba(253, 216, 53, 0.1);
}
.notif-item.unread {
  background: rgba(253, 216, 53, 0.05);
}
.notif-item.unread::before {
  content: '';
  width: 8px;
  height: 8px;
  background: #e74c3c;
  border-radius: 50%;
  display: inline-block;
  margin-right: 8px;
}


</style>

<body class="layout">
  <nav class="navbar topbar navbar-dark shadow-sm">
    <div class="container-fluid">
      <button class="btn btn-link text-dark d-lg-none p-0 me-2"
              type="button"
              data-bs-toggle="offcanvas"
              data-bs-target="#mobileSidebar">
        <i class="bi bi-list fs-2"></i>
      </button>

      <a class="navbar-brand m-0" href="<?= BASE_URL ?>/index.php?m=inicio">
        <img src="./assets/logo.png" alt="Banana Group">
      </a>

      <div class="ms-auto d-flex align-items-center gap-2">
        <!-- Campanita notificaciones -->
        <div class="dropdown">
          <button class="notif-bell" 
                  type="button" 
                  id="dropdownNotificaciones"
                  data-bs-toggle="dropdown" 
                  data-bs-auto-close="outside"
                  aria-expanded="false">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($notifCount > 0): ?>
              <span class="notif-badge"><?= $notifCount ?></span>
            <?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end notif-menu shadow" aria-labelledby="dropdownNotificaciones">
            <li class="px-3 py-2 border-bottom">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Notificaciones</span>
                <?php if ($notifCount > 0): ?>
                  <span class="badge bg-danger rounded-pill"><?= $notifCount ?></span>
                <?php endif; ?>
              </div>
            </li>
            <?php if (empty($notificaciones)): ?>
              <li class="text-center py-4 text-muted small">No hay notificaciones</li>
            <?php else: ?>
              <?php foreach ($notificaciones as $n): ?>
                <li class="notif-item <?= !$n['leida'] ? 'unread' : '' ?>">
                  <div class="small fw-semibold mb-1"><?= htmlspecialchars($n['texto']) ?></div>
                  <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($n['hace']) ?></div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
            <li class="text-center py-2 border-top">
              <a href="#" class="small text-decoration-none">Ver todas</a>
            </li>
          </ul>
        </div>

        <!-- Usuario -->
        <div class="dropdown">
          <button class="btn rounded-pill user-pill dropdown-toggle d-flex align-items-center gap-2"
                  type="button" 
                  id="dropdownUsuario"
                  data-bs-toggle="dropdown"
                  aria-expanded="false">
            <div class="avatar-circle"><?= strtoupper($userInitial) ?></div>
            <span class="d-none d-md-inline"><?= htmlspecialchars($userName) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end user-menu shadow" aria-labelledby="dropdownUsuario">
            <li><a class="dropdown-item" href="?m=usuarios"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <!-- ✅ Script de Bootstrap AL FINAL del body -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}

// ✅ 1. AGREGA LA CONEXIÓN A LA BASE DE DATOS AQUÍ
require_once __DIR__ . '/../App/bd.php'; 

// Obtener usuario actual
require_once __DIR__ . '/../App/auth.php';
require_once __DIR__ . '/../App/notifications.php';

// ✅ Agrega esta línea si no la tienes, para asegurar que cargue la config
require_once __DIR__ . '/../App/pusher_config.php';

// ✅ 2. INICIALIZA LA VARIABLE $pdo
$pdo = db();

// 1. Recuperamos los datos del usuario logueado
$currentUser = current_user();

// 2. OBTENER ID (Lógica blindada)
// Si current_user tiene ID, lo usamos (esto arreglará tu problema)
if (!empty($currentUser['id'])) {
    $usuarioId = (int)$currentUser['id'];
} 
// Si no, intentamos buscar en la sesión directamente
elseif (!empty($_SESSION['usuario_id'])) {
    $usuarioId = (int)$_SESSION['usuario_id'];
} 
else {
    $usuarioId = 0;
}

// 3. Obtener Rol (intentamos del array, si no, de sesión, si no, guest)
$usuarioRol = $currentUser['rol'] ?? $_SESSION['usuario_rol'] ?? 'guest';

// 4. Datos visuales
$userName = $currentUser['nombre'] ?? 'Usuario';
$userInitial = mb_substr($userName, 0, 1, 'UTF-8');

// -------------------------------------------------------------------
//  Cargar notificaciones para el header
// -------------------------------------------------------------------
$notifCount      = 0;
$notificaciones  = [];

if ($usuarioId !== null) {
    $sql = "
        SELECT id, titulo, cuerpo, estado, leida_en, creado_en
        FROM notificaciones
        WHERE (usuario_id = :uid OR usuario_id IS NULL)
          AND estado = 'pendiente'
        ORDER BY creado_en DESC
        LIMIT 10
    ";

    $st = $pdo->prepare($sql);
    $st->execute([':uid' => $usuarioId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $ahora = new DateTimeImmutable('now');

    foreach ($rows as $r) {
        $creado = !empty($r['creado_en'])
            ? new DateTimeImmutable($r['creado_en'])
            : $ahora;

        $diff = $ahora->diff($creado);

        if     ($diff->d > 0) $hace = $diff->d . ' día(s)';
        elseif ($diff->h > 0) $hace = $diff->h . ' h';
        elseif ($diff->i > 0) $hace = $diff->i . ' min';
        else                  $hace = 'hace un momento';

        $notificaciones[] = [
            'id'    => (int)$r['id'],
            'texto' => $r['titulo'] . ' · ' .
                       mb_strimwidth($r['cuerpo'] ?? '', 0, 90, '…', 'UTF-8'),
            'hace'  => $hace,
            'leida' => !empty($r['leida_en']),
        ];

        if (empty($r['leida_en'])) {
            $notifCount++;
        }
    }
}


// Función helper para "hace 3 min", "hace 2 horas", etc.
function tiempo_hace_es(?string $fecha): string {
    if (!$fecha) return '';
    $dt = new DateTime($fecha);
    $now = new DateTime();
    $diff = $now->diff($dt);

    if ($diff->y > 0) return 'hace '.$diff->y.' año(s)';
    if ($diff->m > 0) return 'hace '.$diff->m.' mes(es)';
    if ($diff->d > 0) return 'hace '.$diff->d.' día(s)';
    if ($diff->h > 0) return 'hace '.$diff->h.' hora(s)';
    if ($diff->i > 0) return 'hace '.$diff->i.' minuto(s)';
    return 'hace unos segundos';
}

if ($usuarioId) {
    // 1) cuántas pendientes (no leídas) tiene el usuario
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM notificaciones
        WHERE canal = 'interno'
          AND usuario_id = ?
          AND leida_en IS NULL
    ");
    $st->execute([$usuarioId]);
    $notifCount = (int)$st->fetchColumn();

    // 2) últimas 10 notificaciones para el dropdown
    $st = $pdo->prepare("
        SELECT id, titulo, cuerpo, creado_en, leida_en
        FROM notificaciones
        WHERE canal = 'interno'
          AND usuario_id = ?
        ORDER BY creado_en DESC
        LIMIT 10
    ");
    $st->execute([$usuarioId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $notificaciones[] = [
            'id'    => (int)$r['id'],
            'texto' => $r['titulo'] ?: $r['cuerpo'],
            'hace'  => tiempo_hace_es($r['creado_en']),
            'leida' => !empty($r['leida_en']),
        ];
    }
}


?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Banana Group</title>
  <link rel="icon" href="assets/Banana.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css?v=999" rel="stylesheet">

  <script>
  /* Pasamos las variables de PHP a JS de forma segura */
  window.APP_USER = {
    /* Usamos 0 si no hay ID, evitando errores de sintaxis */
    id: <?php echo (int)($usuarioId ?? 0); ?>,
    rol: "<?php echo htmlspecialchars($usuarioRol ?? 'guest'); ?>"
  };

  /* Configuración de Pusher */
  window.PUSHER_CONFIG = {
    key: "<?php echo defined('PUSHER_APP_KEY') ? PUSHER_APP_KEY : ''; ?>",
    cluster: "<?php echo defined('PUSHER_APP_CLUSTER') ? PUSHER_APP_CLUSTER : ''; ?>"
  };
</script>
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
  display: none !important;
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

/* Menu de notificaciones - Desktop */
.notif-menu {
  min-width: 340px !important;
  max-height: 400px;
  overflow-y: auto;
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
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

/* Responsive para móvil */
@media (max-width: 768px) {
  .navbar-brand img {
    height: 40px;
  }
  
  /* Notificaciones en móvil - ancho ajustado */
  .notif-menu {
    min-width: 280px !important;
    max-width: calc(100vw - 32px) !important;
    max-height: 60vh;
    right: -8px !important;
    left: auto !important;
  }
  
  .notif-item {
    padding: 0.65rem 0.75rem;
  }
  
  .notif-item .small {
    font-size: 0.8rem !important;
  }
  
  .notif-item .text-muted {
    font-size: 0.7rem !important;
  }
  
  /* Usuario en móvil */
  .user-pill {
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
  }
  
  .user-pill .avatar-circle {
    width: 28px;
    height: 28px;
    font-size: 0.75rem;
  }
  
  .user-menu {
    min-width: 180px !important;
  }
  
  /* Botones de notificación y usuario */
  .notif-bell {
    width: 38px;
    height: 38px;
  }
  
  .notif-bell i {
    font-size: 1.1rem;
  }
  
  .notif-badge {
    top: -2px;
    right: -2px;
    padding: 1px 5px;
    font-size: 0.65rem;
    min-width: 18px;
  }

}

/* Para pantallas muy pequeñas */
@media (max-width: 375px) {
  .notif-menu {
    min-width: 260px !important;
    max-width: calc(100vw - 24px) !important;
  }
  
  .notif-item {
    padding: 0.5rem;
  }
}

.notif-item.unread {
  background-color: #f5f5f5;
  border-left: 3px solid #0d6efd;
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
      
    <!-- Campanita de notificaciones -->
<li class="nav-item dropdown me-3">
  <button
      class="btn btn-link position-relative p-0 border-0"
      type="button"
      id="dropdownNotificaciones"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      aria-expanded="false">
    <i class="bi bi-bell fs-5"></i>

    <?php if ($notifCount > 0): ?>
      <span
        id="notifCountBadge"
        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
        <?= $notifCount ?>
      </span>
    <?php endif; ?>
  </button>

  <ul class="dropdown-menu dropdown-menu-end shadow notif-menu"
      aria-labelledby="dropdownNotificaciones">

    <!-- Cabecera del dropdown -->
    <li class="px-3 py-2 border-bottom bg-light">
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">Notificaciones</span>
        <?php if ($notifCount > 0): ?>
          <span class="badge bg-danger rounded-pill"><?= $notifCount ?></span>
        <?php endif; ?>
      </div>
    </li>

    <!-- Lista de notificaciones -->
    <?php if (empty($notificaciones)): ?>
      <li class="text-center py-4 text-muted small">
        <i class="bi bi-bell-slash d-block mb-2"
           style="font-size: 2rem; opacity: 0.3;"></i>
        No hay notificaciones
      </li>
    <?php else: ?>
      <?php foreach ($notificaciones as $n): ?>
        <li class="px-3 py-2 border-bottom small <?= $n['leida'] ? '' : 'bg-light' ?>">
          <div class="fw-semibold mb-1" style="line-height: 1.3;">
            <?= htmlspecialchars($n['texto'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="text-muted" style="font-size: 0.75rem;">
            <i class="bi bi-clock me-1"></i>
            <?= htmlspecialchars($n['hace'], ENT_QUOTES, 'UTF-8') ?>
          </div>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer del dropdown -->
    <li class="text-center py-2 border-top bg-light">
      <a href="#"
         class="small text-decoration-none text-primary fw-semibold">
        Ver todas <i class="bi bi-arrow-right"></i>
      </a>
    </li>
  </ul>
</li>



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
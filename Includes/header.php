<?php
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Banana GroupMX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css?v=999" rel="stylesheet"><!-- relativo a /Public -->
</head>

<style>
/* Logo fijo arriba-izquierda  */
.login-logo img{ height:60px; display:block; }

/* ===== Estilo del botón "pill" del usuario ===== */
.user-pill{
  /* colores base */
  --bs-btn-color: #1a1a1a;
  --bs-btn-bg: rgba(0,0,0,.15);
  --bs-btn-border-color: transparent;

  /* hover (iluminado) */
  --bs-btn-hover-color: #000;
  --bs-btn-hover-bg: #fdd835;          /* amarillo hover */
  --bs-btn-hover-border-color: transparent;

  /* active (click presionado) */
  --bs-btn-active-color: #000;
  --bs-btn-active-bg: #fbc02d;         /* amarillo más oscuro */
  --bs-btn-active-border-color: transparent;

  transition: all .25s ease;
}
.user-pill:hover{
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0,0,0,.08);
}

/* avatar dentro del pill */
.user-pill .avatar-circle{
  background: rgba(0,0,0,.2);
  transition: background .25s ease;
}
.user-pill:hover .avatar-circle,
.user-pill:focus .avatar-circle{
  background: rgba(255,255,255,.35);
}

/* ===== Dropdown del usuario ===== */
.navbar .dropdown{ position: relative; }     /* referencia de posición */
.navbar .dropdown .user-menu{
  --bs-dropdown-min-width: 0;                /* sin mínimo de Bootstrap */
  width: 100%;                               /* mismo ancho que el botón */
  background-color: #fffbea;                 /* fondo del menú */
  border-color: rgba(0,0,0,.1);
}
.user-menu .dropdown-item{
  color:#111;
  transition: background-color .2s ease, color .2s ease;
}
.user-menu .dropdown-item:hover,
.user-menu .dropdown-item:focus{
  background-color:#fdd835;                  /* mismo amarillo del hover */
  color:#000;
}
/* cerrar sesión con estilo */
.user-menu .dropdown-item.text-danger:hover{
  color:#b00020;
}
</style>

<body class="layout">
  <nav class="navbar topbar navbar-dark shadow-sm">
    <div class="container-fluid">
      <button class="btn btn-link text-white d-lg-none p-0 me-2"
              type="button"
              data-bs-toggle="offcanvas"
              data-bs-target="#mobileSidebar"
              aria-controls="mobileSidebar"
              aria-label="Menú">
        <i class="bi bi-list fs-2"></i>
      </button>

      
      <a class="navbar-brand brand m-0 d-flex align-items-center" href="<?= BASE_URL ?>/index.php?m=inicio">
        <img src="./assets/logo.png" alt="Banana Group" style="height:60px;">
      </a>

      <!-- Usuario -->
      <div class="ms-auto dropdown">
        <button
          id="userDropdown"
          class="btn rounded-pill px-3 d-flex align-items-center gap-2 dropdown-toggle user-pill"
          type="button"
          data-bs-toggle="dropdown"
          data-bs-display="static"
          aria-expanded="false"
          aria-haspopup="true">
          <span class="avatar-circle"><i class="bi bi-person-fill"></i></span>
          <span>Leonel Pimentel Agundez</span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end user-menu" aria-labelledby="userDropdown">
          <li><a class="dropdown-item text-danger" href="login.php">Cerrar sesión</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

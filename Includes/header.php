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
  <link href="./css/app.css" rel="stylesheet"><!-- relativo a /Public -->
</head>
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

      <a class="navbar-brand brand m-0" href="<?= BASE_URL ?>/index.php?m=inicio">
        Banana Group<span class="brand-emp">MX</span>
      </a>

      <div class="ms-auto">
        <div class="btn btn-outline-light rounded-pill px-3 d-flex align-items-center gap-2">
          <span class="avatar-circle"><i class="bi bi-person-fill"></i></span>
          <span>Usuario</span>
        </div>
      </div>
    </div>
  </nav>

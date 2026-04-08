<?php
// Public/login.php
declare(strict_types=1);

// Activar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';

// si ya está logueado, mándalo al inicio
if (current_user()) {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php');
    exit;
}

$pdo   = db();
$error = '';
$debug = ''; // Para depuración

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $pass   = $_POST['password'] ?? '';

    $debug .= "Correo recibido: " . htmlspecialchars($correo) . "<br>";
    $debug .= "Password recibido: " . (empty($pass) ? 'VACÍO' : 'CON VALOR') . "<br>";

    if ($correo === '' || $pass === '') {
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        // Traemos usuario + roles
        $st = $pdo->prepare("
            SELECT
              u.id,
              u.nombre,
              u.correo,
              u.pass_hash,
              u.activo,
              GROUP_CONCAT(ur.rol_id) AS roles_csv
            FROM usuarios u
            LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
            WHERE u.correo = ?
            GROUP BY u.id
            LIMIT 1
        ");
        $st->execute([$correo]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        $debug .= "Usuario encontrado: " . ($u ? 'SÍ' : 'NO') . "<br>";
        
        if ($u) {
            $debug .= "Activo: " . ($u['activo'] ? 'SÍ' : 'NO') . "<br>";
            $debug .= "Hash en BD: " . substr($u['pass_hash'], 0, 20) . "...<br>";
            $debug .= "Verificación password: " . (password_verify($pass, $u['pass_hash']) ? 'CORRECTA' : 'INCORRECTA') . "<br>";
        }

        if (!$u || !(int)$u['activo']) {
            $error = 'Usuario no encontrado o inactivo.';
        } elseif (!password_verify($pass, $u['pass_hash'])) {
            $error = 'Correo o contraseña incorrectos.';
        } else {
            // Login OK → guardamos en sesión
            $_SESSION['user_id']      = (int)$u['id'];
            $_SESSION['user_nombre']  = $u['nombre'];
            $_SESSION['user_correo']  = $u['correo'];
            $_SESSION['user_roles']   = $u['roles_csv']
                ? array_map('intval', explode(',', $u['roles_csv']))
                : [];

            $debug .= "SESIÓN CREADA - Redirigiendo...<br>";

            // Guardar IDs de roles
            $rolesIds = $u['roles_csv'] ? array_map('intval', explode(',', $u['roles_csv'])) : [];
            $_SESSION['user_roles'] = $rolesIds;

            //  NUEVO: Guardar el nombre del rol principal (para evitar consultas extra)
            // Buscamos el nombre del primer rol que tenga
            if (!empty($rolesIds)) {
                $stRol = $pdo->prepare("SELECT nombre FROM roles WHERE id = ? LIMIT 1");
                $stRol->execute([$rolesIds[0]]);
                $nombreRol = $stRol->fetchColumn();
                $_SESSION['user_rol'] = strtolower($nombreRol); // Guardamos "admin", "operador", etc.
                $_SESSION['usuario_rol'] = strtolower($nombreRol); // Compatibilidad con código viejo
            } else {
                $_SESSION['user_rol'] = 'guest';
                $_SESSION['usuario_rol'] = 'guest';
            }
            
            header('Location: /Sistema-de-Saldos-y-Pagos-/Public/index.php');
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Banana Group </title>
    <link rel="icon" href="assets/Banana.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">

    

     <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link rel="stylesheet" href="./css/login.css">
</head>

<body>

      <!-- Contenido del login -->
    
    <main class="page">
    <h1 class="brand">Portal Banana Group</h1>

      

    <section class="card" role="dialog" aria-labelledby="card-title">
      <div class="card-topbar"></div>
      <h2 id="card-title" class="card-title">Iniciar Sesión</h2>

       <?php if ($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:4px;margin-bottom:12px;font-size:14px;text-align:center;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

     

      <form class="form"   method="post" novalidate>
        <label class="field with-icon-right">
          <span class="sr-only">Usuario o correo</span>
          <input type="text" name="correo" autocomplete="username" placeholder="Correo Electrónico" required />
          <span class="icon" aria-hidden="true">
            <!-- envelope icon -->
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z" />
            </svg>
          </span>
        </label>

        <label class="field with-icon-right">
  <span class="sr-only">Contraseña</span>
  <input type="password" name="password" id="passwordInput" autocomplete="current-password" placeholder="Contraseña" />
  
  <span class="icon clickable-icon" id="togglePassword" aria-hidden="true" title="Mostrar/Ocultar contraseña">
    <svg id="eyeIcon" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
      <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
    </svg>
  </span>
</label>

       <div class="text-center mt-3">
    <a href="#" class="text-decoration-none small text-muted link-hover" data-bs-toggle="modal" data-bs-target="#modalRecuperar">
        <i class="bi bi-key"></i> ¿Olvidaste tu contraseña?
    </a>
    <style>
        .link-hover { transition: 0.3s; }
        .link-hover:hover { color: #f9af24 !important; text-decoration: underline !important; cursor: pointer; }
    </style>
</div>
        
        <button  class="btn" type="submit">Iniciar Sesion</button>
      </form>
    </section>
  </main>

  
  <div class="banana">
    <img src="./assets/Banana.png" alt="">
  </div>

  <div class="banana2">
    <img src="./assets/Banana.png" alt="">
  </div>

  <div class="banana3">
    <img src="./assets/Banana.png" alt="">
  </div>

  <div class="banana4">
    <img src="./assets/Banana.png" alt="">
  </div>


  <!-- Fondo decorativo (no afecta el layout) -->
<div class="bg-decor" aria-hidden="true">
  <span class="blob blob-1"></span>
  <span class="blob blob-2"></span>


  <!-- Lupa flotando -->
  <img class="decor decor-lupa" src="./assets/lupa.png" alt="Lupa">

  <!-- Avioncito que cruza -->
  <img class="decor decor-plane" src="./assets/paper.png" alt="Paper" >
  
</div>

<!-- Logo fijo en la  esquina -->
<a class="login-logo" href="/">
  <img src="./assets/logo.png" alt="Banana Group">
</a>

<!-- Tu burbuja -->
<img src="./assets/burbuja.png" alt="Burbuja" class="bubble">


<div class="modal fade" id="modalRecuperar" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
      
      <div class="modal-header border-0" style="background-color: #fdd835;">
        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-shield-lock-fill me-2"></i>Recuperar Acceso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">
        <div id="step1">
            <p class="text-muted text-center mb-4">Ingresa tu correo electrónico registrado. Te enviaremos un código de seguridad.</p>
            <form id="formSendCode">
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="recupEmail"  required placeholder="name@example.com">
                    <label for="recupEmail">Correo Electrónico</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-lg" id="btnSendCode">
                        Enviar Código <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>

        <div id="step2" class="d-none">
            <div class="alert alert-success small py-2 d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i> 
                <div>Código enviado. Revisa tu bandeja.</div>
            </div>
            
            <form id="formResetFinal">
                <input type="hidden" id="finalEmail" name="email">
                
                <div class="mb-3 text-center">
                    <label class="form-label fw-bold small text-uppercase text-muted">Código de 6 dígitos</label>
                    <input type="text" class="form-control text-center fw-bold fs-4" id="recupCode" name="code" required 
                           placeholder="000000" maxlength="6" style="letter-spacing: 8px;">
                </div>
                
                <div class="row g-2 mb-3">
    <div class="col-6">
        <label class="form-label small fw-bold">Nueva Contraseña</label>
        <div class="field with-icon-right">
            <input type="password" class="form-control" id="newPass" name="password" required minlength="6" placeholder="******">
            <span class="icon clickable-icon toggle-password" data-target="newPass">
                <svg class="eye-icon" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
            </span>
        </div>
    </div>

    <div class="col-6">
        <label class="form-label small fw-bold">Confirmar</label>
        <div class="field with-icon-right">
            <input type="password" class="form-control" id="confPass" required minlength="6" placeholder="******">
            <span class="icon clickable-icon toggle-password" data-target="confPass">
                <svg class="eye-icon" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
            </span>
        </div>
    </div>
</div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-warning fw-bold">Actualizar Contraseña</button>
                    <button type="button" class="btn btn-light btn-link btn-sm text-muted text-decoration-none" onclick="volverPaso1()">
                        <i class="bi bi-arrow-left"></i> Correo equivocado
                    </button>
                </div>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="./js/login.js" defer></script>

</body>
</html>
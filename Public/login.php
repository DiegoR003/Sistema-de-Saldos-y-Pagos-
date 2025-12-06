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

            // ✅ NUEVO: Guardar el nombre del rol principal (para evitar consultas extra)
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
    <title>Banana Group - Login </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<style>
    
body{
    background-color: #87CEFA;
}

h1{
    color: #fff;
    font-family: "arial", serif;
 }

    /* Reset & base */
* { box-sizing: border-box; }
html, body { height: 100%; }
body {
  margin: 0;
  font-family: 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, sans-serif;
  color: #2a2a2a;
  background: #fff;
}

/* Layout */
.page {
  min-height: 100%;
  display: grid;
  place-items: center;
  padding: 200px 16px; /* espacio entre titulo y form */
  
}

.brand {
  color: #212529;
  font-weight: 700;
  font-size: clamp(28px, 4vw, 48px);
  font-family: "Overpass", sans-serif;
  margin: 0;
  letter-spacing: 0.5px;
}

/* Card */
.card {
  position: relative;
  width: min(520px, 92vw);
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 10px 24px rgba(0,0,0,.15);
  padding: 22px 28px 26px;
}

.card-topbar {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 6px;
  background: #22a6f2;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.card-title {
  text-align: center;
  font-size: 16px;
  color: #6b6f72;
  font-weight: 600;
  margin: 8px 0 14px;
}

/* Form fields */
.form { display: grid; gap: 12px; }

.field {
  position: relative;
  display: block;
}

.field input {
  width: 100%;
  padding: 12px 44px 12px 12px; /* espacio para el icono */
  border: 1px solid #d7dbe0;
  border-radius: 4px;
  outline: none;
  font-size: 14px;
  transition: box-shadow .2s ease, border-color .2s ease;
}

.field input::placeholder { color: #b2b6bb; }

.field input:focus {
  border-color: #22a6f2;
  box-shadow: 0 0 0 3px rgba(34,166,242,.15);
}

/* Icono a la derecha */
.with-icon-right .icon {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 22px; height: 22px;
  display: inline-grid;
  place-items: center;
  pointer-events: none;
  color: #7d8a97;
}

.with-icon-right .icon svg {
  width: 20px; height: 20px;
  fill: currentColor;
}

/* Botón */
.btn {
  display: block;
  width: 100%;
  border: none;
  border-radius: 3px;
  padding: 12px 14px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: #2b7fb8;
  cursor: pointer;
  margin-top: 10px;
  transition: filter .2s ease, transform .02s ease-in;
}

.btn:hover { filter: brightness(1.05); }
.btn:active { transform: translateY(1px); }

/* A11y */
.sr-only {
  position: absolute !important;
  height: 1px; width: 1px;
  overflow: hidden;
  clip: rect(1px,1px,1px,1px);
  white-space: nowrap;
}



/* Animaciones para las bananas */
.banana{
  position: absolute;
  right: 5%;
  bottom: 2%;
  z-index: -1;

   /* Animación */
  animation: rotateBanana 15s linear infinite;
}

@keyframes rotateBanana {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.banana2{
  position: absolute;
  left: 5%;
  bottom: 30%;
  z-index: -1;

   /* Animación */
  animation: rotateBanana2 15s linear infinite;
}

@keyframes rotateBanana2 {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.banana3{
  position: absolute;
  right: 5%;
  bottom: 58%;
  z-index: -1;

   /* Animación */
  animation: rotateBanana3 15s linear infinite;
}

@keyframes rotateBanana3 {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.banana4{
  position: absolute;
  left: 5%;
  bottom: 80%;
  z-index: -1;

   /* Animación */
  animation: rotateBanana4 15s linear infinite;
}

@keyframes rotateBanana4 {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}


/* Animación de la búrbuja*/

.bubble {
  position: absolute;
  bottom: 25%;
  left: 2%;
  z-index: -1; 
}

:root{
  --decor-speed: 6s;
  --decor-amp: 12px;
  --blob1: #fff2a8; /* crema */
  --blob2: #ffe36d; /* amarillo */
}

/* Colores de fondo suaves */
body { background:#fffbee; }

/* Capa de decoraciones detrás del contenido */
.bg-decor{
  position:fixed; inset:0; z-index:0;
  pointer-events:none; /* no bloquea clics */
  overflow:hidden;
}
.decor{ position:absolute; user-select:none; opacity:.9; will-change:transform; }

/* Blobs (manchas) sin imágenes */
.blob{ position:absolute; border-radius:50%; filter:blur(20px); opacity:.35; }
.blob-1{
  width:42vw; height:42vw; max-width:720px; max-height:720px;
  top:-15vw; right:-12vw;
  background: radial-gradient(circle at 30% 30%, var(--blob2), var(--blob1));
  animation: floatY calc(var(--decor-speed) * 1.5) ease-in-out infinite;
}
.blob-2{
  width:22vw; height:22vw; max-width:420px; max-height:420px;
  bottom:-8vw; left:-10vw;
  background: radial-gradient(circle at 40% 40%, var(--blob1), var(--blob2));
  animation: floatY calc(var(--decor-speed) * 1.1) ease-in-out infinite reverse;
}

/* Piezas sutiles (posiciones no interfieren con la UI) */

.decor-lupa{ top:33vh; left:30vw; width:44px;
  animation: sway var(--decor-speed) ease-in-out infinite, floatY calc(var(--decor-speed)*1.2) ease-in-out infinite;
}
.decor-plane{ top:75vh; left:420px; width:356px;
  animation: planeMove 18s linear infinite, floatY calc(var(--decor-speed)*.9) ease-in-out infinite;
}

/* Logo fijo arriba-izquierda  */
.login-logo{
  position:fixed; top:24px; left:32px; z-index:2; text-decoration:none;
}
.login-logo img{ height:60px; display:block; }

/* Animaciones */
@keyframes spin{ from{transform:rotate(0)} to{transform:rotate(360deg)} }
@keyframes floatY{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(calc(var(--decor-amp)*-1))} }
@keyframes sway{ 0%,100%{transform:rotate(-6deg)} 50%{transform:rotate(6deg)} }
@keyframes planeMove{
  0%{ transform:translateX(0) translateY(0) rotate(-6deg); }
  50%{ transform:translateX(55vw) translateY(-3vh) rotate(2deg); }
  100%{ transform:translateX(105vw) translateY(0) rotate(0deg); }
}

/* Accesibilidad */
@media (prefers-reduced-motion: reduce){
  .blob, .decor{ animation:none !important; }
}




/* Responsive */
@media (max-width: 420px) {
  .card-title { font-size: 15px; }
  .btn { padding: 12px; }
  .page{ padding: 250px 16px;   }
}

   </style>
<body>

      <!-- Contenido del login -->
    
    <main class="page">
    <h1 class="brand">Sistema de Pagos</h1>

      

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
          <input type="password" name="password" autocomplete="current-password" placeholder="Contraseña" />
          <span class="icon" aria-hidden="true">
            <!-- lock icon -->
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M12 2a5 5 0 0 0-5 5v3H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V11a1 1 0 0 0-1-1h-2V7a5 5 0 0 0-5-5zm3 8H9V7a3 3 0 1 1 6 0v3z" />
            </svg>
          </span>
        </label>
        
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

<script>
  const bubble = document.querySelector(".bubble");

  let x = 100, y = 100;   // posición inicial
  let dx = 1, dy = 1;     // velocidad (px por frame) -> cambia a 3 o 4 si  quieres que vaya más rápido
  const speed = 16;       // ~60fps (16ms por frame)

  function move() {
    const w = window.innerWidth - bubble.offsetWidth;
    const h = window.innerHeight - bubble.offsetHeight;

    x += dx;
    y += dy;

    // Rebote en los bordes
    if (x <= 0 || x >= w) dx *= -1;
    if (y <= 0 || y >= h) dy *= -1;

    bubble.style.left = x + "px";
    bubble.style.top = y + "px";

    setTimeout(move, speed);
  }

  move();
</script>

</body>
</html>
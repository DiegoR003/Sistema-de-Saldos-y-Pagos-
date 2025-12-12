<?php
// 1. INICIAR LÓGICA PHP
if (!defined('BASE_URL')) {
  $cfg = __DIR__ . '/../app/config.php';
  if (file_exists($cfg)) require_once $cfg;
  if (!defined('BASE_URL')) {
    $guess = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    define('BASE_URL', $guess ?: '/');
  }
}

require_once __DIR__ . '/../App/bd.php'; 
require_once __DIR__ . '/../App/auth.php';
require_once __DIR__ . '/../App/notifications.php';
require_once __DIR__ . '/../App/pusher_config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$currentUser = current_user();

// ==============================================================================
// 🔒 SEGURIDAD CRÍTICA: DETECTAR USUARIO ELIMINADO (KILL SWITCH)
// ==============================================================================
if (isset($_SESSION['user_id']) && !$currentUser && !isset($_SESSION['cliente_id'])) {
    session_unset();
    session_destroy();
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/login.php?err=Acceso_revocado');
    exit; 
}
// ==============================================================================

// --- DEFINIR VARIABLES POR DEFECTO ---
$usuarioId    = (int)($currentUser['id'] ?? 0);
$usuarioEmail = $currentUser['correo'] ?? '';
$userName     = $currentUser['nombre'] ?? 'Invitado';
$usuarioRol   = $currentUser['rol'] ?? ''; 
$userInitial  = 'U';
$fotoUsuario  = '';

$clienteId = 0;
$esCliente = false;

// 2. DETECTAR SI ES CLIENTE O ADMIN
if (isset($_SESSION['cliente_id'])) {
    // Caso A: Ya está logueado explícitamente como cliente
    $esCliente = true;
    $clienteId = (int)$_SESSION['cliente_id'];
    $usuarioRol = 'cliente';
    $userName = $_SESSION['nombre_cliente'] ?? 'Cliente'; 
} elseif ($usuarioId > 0 && !empty($usuarioEmail)) {
    // Caso B: Logueado como Usuario, verificar si es Cliente por su correo
    $stCli = $pdo->prepare("SELECT id, empresa FROM clientes WHERE correo = ? LIMIT 1");
    $stCli->execute([$usuarioEmail]);
    $cliData = $stCli->fetch(PDO::FETCH_ASSOC);
    
    if ($cliData) {
        $esCliente = true;
        $clienteId = (int)$cliData['id'];
        
        // ✅ AGREGA ESTA LÍNEA AQUÍ:
        // Guardamos en sesión para que 'notificaciones_leer.php' sepa quién es el cliente
        $_SESSION['cliente_id'] = $clienteId; 
    }
}
$userInitial = mb_substr($userName, 0, 1, 'UTF-8');

// Foto solo para Staff (si no es cliente puro)
if ($usuarioId > 0) {
    $stUser = $pdo->prepare("SELECT nombre, foto_url FROM usuarios WHERE id = ? LIMIT 1");
    $stUser->execute([$usuarioId]);
    $datosFrescos = $stUser->fetch(PDO::FETCH_ASSOC);
    if ($datosFrescos) {
        $userName    = $datosFrescos['nombre'];
        $fotoUsuario = $datosFrescos['foto_url'];
    }
}

// -------------------------------------------------------------------
//  CARGAR NOTIFICACIONES (Ahora sí usará el $clienteId correcto)
// -------------------------------------------------------------------
$notificaciones = [];

function tiempo_hace_es(?string $fecha): string {
    if (!$fecha) return '';
    $dt = new DateTime($fecha);
    $now = new DateTime();
    $diff = $now->diff($dt);
    if ($diff->y > 0) return 'hace '.$diff->y.' año(s)';
    if ($diff->m > 0) return 'hace '.$diff->m.' mes(es)';
    if ($diff->d > 0) return 'hace '.$diff->d.' día(s)';
    if ($diff->h > 0) return 'hace '.$diff->h.' h';
    if ($diff->i > 0) return 'hace '.$diff->i.' min';
    return 'hace un momento';
}

// LÓGICA DE CONSULTA SEGÚN ROL
if ($esCliente && $clienteId > 0) {
    // A) CLIENTE: Buscar tipo 'externa' y su cliente_id
    $sql = "SELECT id, titulo, cuerpo, creado_en, leida_en, estado
            FROM notificaciones
            WHERE tipo = 'externa' AND cliente_id = ?
            ORDER BY creado_en DESC LIMIT 10";
    $stList = $pdo->prepare($sql);
    $stList->execute([$clienteId]);

} elseif ($usuarioId > 0) {
    // B) ADMIN: Buscar tipo 'interna' y su usuario_id
    $sql = "SELECT id, titulo, cuerpo, creado_en, leida_en, estado
            FROM notificaciones
            WHERE tipo = 'interna' AND (usuario_id = ? OR usuario_id IS NULL)
            ORDER BY creado_en DESC LIMIT 10";
    $stList = $pdo->prepare($sql);
    $stList->execute([$usuarioId]);
} else {
    $stList = null;
}

if ($stList) {
    $rows = $stList->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $notificaciones[] = [
            'id'    => (int)$r['id'],
            'texto' => $r['titulo'] ?: mb_strimwidth($r['cuerpo'], 0, 50, '...'),
            'cuerpo'=> mb_strimwidth($r['cuerpo'], 0, 80, '...'),
            'hace'  => tiempo_hace_es($r['creado_en']),
            'leida' => !empty($r['leida_en']),
            'pendiente' => ($r['estado'] === 'pendiente' && empty($r['leida_en'])),
            'timestamp' => strtotime($r['creado_en']),
        ];
    }
}

// CALCULAR TOTAL NO LEÍDAS PARA EL BADGE
$totalNoLeidas = 0;
foreach($notificaciones as $n) {
    if($n['pendiente']) $totalNoLeidas++;
}

// Pasar datos al JavaScript
$notificacionesJSON = json_encode($notificaciones);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Portal Banana Group</title>
  <link rel="icon" href="assets/Banana.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css?v=999" rel="stylesheet">

  <!-- Script INLINE para ejecutar ANTES que todo -->
  <script>
  window.APP_USER = {
    id: <?php echo (int)($usuarioId ?? 0); ?>,
    rol: "<?php echo htmlspecialchars($usuarioRol ?? 'guest'); ?>",
    clienteId: <?php echo (int)($clienteId ?? 0); ?>,
    esCliente: <?php echo $esCliente ? 'true' : 'false'; ?>
  };
  window.PUSHER_CONFIG = {
    key: "<?php echo defined('PUSHER_APP_KEY') ? PUSHER_APP_KEY : ''; ?>",
    cluster: "<?php echo defined('PUSHER_APP_CLUSTER') ? PUSHER_APP_CLUSTER : ''; ?>"
  };
  
  // Notificaciones cargadas desde BD
  window.NOTIFICACIONES_INICIALES = <?php echo $notificacionesJSON; ?>;
  window.TOTAL_NO_LEIDAS = <?php echo $totalNoLeidas; ?>;
  
  // Función para obtener notificaciones ocultas
  function getNotifOcultas() {
    const userId = window.APP_USER.id || window.APP_USER.clienteId;
    const key = 'notif_ocultas_' + userId;
    const stored = localStorage.getItem(key);
    return stored ? JSON.parse(stored) : [];
  }

  // Función para limpiar notificaciones antiguas ocultas (más de 7 días)
  function limpiarNotifAntiguasOcultas() {
    const userId = window.APP_USER.id || window.APP_USER.clienteId;
    const key = 'notif_ocultas_' + userId;
    const ocultas = getNotifOcultas();
    const notifActuales = window.NOTIFICACIONES_INICIALES.map(n => n.id);
    
    // Filtrar solo las que aún existen en BD
    const ocultasFiltradas = ocultas.filter(id => notifActuales.includes(id));
    localStorage.setItem(key, JSON.stringify(ocultasFiltradas));
  }
  </script>
</head>

<style>
/* Estilos Header */
.navbar-brand img { height: 50px; }
.user-pill {
  --bs-btn-color: #1a1a1a; --bs-btn-bg: rgba(0,0,0,.08); --bs-btn-border-color: transparent;
  --bs-btn-hover-color: #000; --bs-btn-hover-bg: #fdd835; --bs-btn-hover-border-color: transparent;
  transition: all .2s ease; font-size: 0.9rem; padding: 0.4rem 1rem;
}
.user-pill:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.user-pill .avatar-circle {
  width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #f39c12, #e74c3c); color: white; font-weight: 600; font-size: 0.85rem;
}
.user-menu { min-width: 200px !important; background: #fffbea; border-color: rgba(0,0,0,.1); }
.user-menu .dropdown-item:hover { background: #fdd835; color: #000; }

/* Notificaciones */
.notif-menu { min-width: 340px !important; max-height: 400px; overflow-y: auto; border-radius: 0.5rem; box-shadow: 0 4px 15px rgba(0,0,0,.15); }
.notif-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f0f0f0; transition: all .2s; position: relative; }
.notif-item:hover { background: #fffde7; }
.notif-item.oculta { display: none !important; }
.btn-close-notif {
    position: absolute; right: 10px; top: 10px;
    font-size: 0.7rem; color: #999; cursor: pointer;
    background: none; border: none; padding: 2px;
    opacity: 0.5; transition: 0.2s; z-index: 10;
}
.btn-close-notif:hover { opacity: 1; color: #dc3545; transform: scale(1.2); }

@media (max-width: 768px) {
  .notif-menu { min-width: 280px !important; max-width: calc(100vw - 32px) !important; right: -8px !important; }
}
</style>

<body class="layout">
  <nav class="navbar topbar navbar-dark shadow-sm">
    <div class="container-fluid">
      <button class="btn btn-link text-dark d-lg-none p-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list fs-2"></i>
      </button>

      <a class="navbar-brand m-0" href="<?= BASE_URL ?>/index.php?m=inicio">
        <img src="./assets/logo.png" alt="Banana Group">
      </a>

      <div class="ms-auto d-flex align-items-center gap-2">
      
      <li class="nav-item dropdown me-3 list-unstyled">
          <button class="btn btn-link position-relative p-0 border-0 text-dark" 
                  type="button" id="dropdownNotificaciones" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell fs-5"></i>
            <!-- Badge dinámico -->
            <span id="notifCountBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $totalNoLeidas > 0 ? '' : 'd-none' ?>" 
                  style="visibility: <?= $totalNoLeidas > 0 ? 'visible' : 'hidden' ?>;">
                <?= $totalNoLeidas ?>
            </span>
          </button>

          <ul class="dropdown-menu dropdown-menu-end shadow notif-menu" aria-labelledby="dropdownNotificaciones">
            <li class="px-3 py-2 border-bottom bg-light d-flex justify-content-between align-items-center">
              <span class="fw-bold small text-uppercase text-muted">Notificaciones</span>
              <small class="text-muted" id="contadorNotif"><?= count($notificaciones) ?> total</small>
            </li>

            <div id="listaNotificaciones">
                <?php if (empty($notificaciones)): ?>
                  <li class="text-center py-5 text-muted small" id="noNotifMsg">
                    <i class="bi bi-bell-slash d-block mb-2 fs-4 opacity-50"></i>
                    Sin novedades
                  </li>
                <?php else: ?>
                  <?php foreach ($notificaciones as $n): ?>
                    <li class="notif-item px-3 py-2 border-bottom small <?= $n['leida'] ? 'bg-white' : 'bg-light' ?>" 
                        id="notif-<?= $n['id'] ?>" 
                        data-notif-id="<?= $n['id'] ?>"
                        data-pendiente="<?= $n['pendiente'] ? '1' : '0' ?>"
                        data-timestamp="<?= $n['timestamp'] ?>">
                        
                      <button class="btn-close-notif" onclick="ocultarNotif(event, <?= $n['id'] ?>)" title="Ocultar">
                          <i class="bi bi-x-lg"></i>
                      </button>
                      
                      <div class="fw-semibold mb-1 pe-3"><?= htmlspecialchars($n['texto']) ?></div>
                      <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($n['cuerpo']) ?></div>
                      <div class="text-end mt-1 text-primary" style="font-size: 0.65rem;">
                        <?= htmlspecialchars($n['hace']) ?>
                      </div>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
            </div>
          </ul>
        </li>


        <div class="dropdown">
          <button class="btn rounded-pill user-pill dropdown-toggle d-flex align-items-center gap-2" type="button" id="dropdownUsuario" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="avatar-circle" style="overflow: hidden;">
              <?php if (!empty($fotoUsuario)): ?>
                <img src="<?= htmlspecialchars($fotoUsuario) ?>?v=<?= time() ?>" alt="User" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <?= strtoupper($userInitial) ?>
              <?php endif; ?>
            </div>
            <span class="d-none d-md-inline"><?= htmlspecialchars($userName) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end user-menu shadow">
            <li><a class="dropdown-item" href="?m=usuarios"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

 <script>
// =====================================================
// SISTEMA DE NOTIFICACIONES - VERSIÓN OPTIMIZADA
// =====================================================

// Variables globales
let notificacionesRecientes = new Set();

// Guardar notificación oculta en localStorage
function saveNotifOculta(notifId) {
    const userId = window.APP_USER.id || window.APP_USER.clienteId;
    const key = `notif_ocultas_${userId}`;
    let ocultas = getNotifOcultas();
    
    if (!ocultas.includes(notifId)) {
        ocultas.push(notifId);
        localStorage.setItem(key, JSON.stringify(ocultas));
        console.log('✅ Notificación', notifId, 'guardada como oculta');
    }
}

// Actualizar badge
function actualizarBadge(count) {
    const badge = document.getElementById('notifCountBadge');
    if (!badge) return;
    
    console.log('🔢 Actualizando badge a:', count);
    
    if (count > 0) {
        badge.innerText = count;
        badge.classList.remove('d-none');
        badge.style.visibility = 'visible';
    } else {
        badge.classList.add('d-none');
        badge.style.visibility = 'hidden';
    }
}

// Verificar si hay notificaciones visibles
function verificarNotificacionesVisibles() {
    const lista = document.getElementById('listaNotificaciones');
    const notifVisibles = document.querySelectorAll('.notif-item:not(.oculta)').length;
    const contador = document.getElementById('contadorNotif');
    
    console.log('📊 Notificaciones visibles:', notifVisibles);
    
    if (contador) {
        contador.textContent = notifVisibles + ' total';
    }
    
    if (notifVisibles === 0) {
        lista.innerHTML = `
          <li class="text-center py-5 text-muted small" id="noNotifMsg">
            <i class="bi bi-bell-slash d-block mb-2 fs-4 opacity-50"></i>
            Sin novedades
          </li>
        `;
    } else {
        const noMsg = document.getElementById('noNotifMsg');
        if (noMsg) noMsg.remove();
    }
}

// Filtrar notificaciones ocultas y actualizar contador
function filtrarNotificacionesOcultas() {
    console.log('🔍 Filtrando notificaciones ocultas...');
    
    const ocultas = getNotifOcultas();
    const ahora = Math.floor(Date.now() / 1000);
    let contadorVisible = 0;
    
    console.log('📋 IDs ocultas en localStorage:', ocultas);
    
    document.querySelectorAll('.notif-item').forEach(item => {
        const notifId = parseInt(item.dataset.notifId);
        const esPendiente = item.dataset.pendiente === '1';
        const timestamp = parseInt(item.dataset.timestamp || 0);
        const esReciente = (ahora - timestamp) < 10; // Menos de 10 segundos
        
        // NO OCULTAR si es una notificación que acaba de llegar
        if (ocultas.includes(notifId) && !esReciente) {
            item.classList.add('oculta');
            console.log('👁️ Ocultando notificación ID:', notifId);
        } else {
            item.classList.remove('oculta');
            if (esPendiente) {
                contadorVisible++;
            }
        }
    });
    
    console.log('✅ Contador de pendientes visibles:', contadorVisible);
    
    actualizarBadge(contadorVisible);
    verificarNotificacionesVisibles();
}

// Función para ocultar notificación
function ocultarNotif(e, id) {
    e.stopPropagation();
    e.preventDefault();

    console.log('❌ Ocultando notificación ID:', id);

    const item = document.getElementById('notif-' + id);
    if (!item) return;
    
    saveNotifOculta(id);
    
    item.style.transition = 'all 0.2s ease';
    item.style.opacity = '0';
    item.style.transform = 'translateX(20px)';
    
    setTimeout(() => {
        item.classList.add('oculta');
        verificarNotificacionesVisibles();
        
        const pendientesVisibles = document.querySelectorAll('.notif-item:not(.oculta)[data-pendiente="1"]').length;
        actualizarBadge(pendientesVisibles);
    }, 200);
}

// =====================================================
// INICIALIZACIÓN
// =====================================================

document.addEventListener("DOMContentLoaded", function() {
    console.log('🚀 Inicializando sistema de notificaciones...');
    console.log('👤 Usuario:', window.APP_USER);
    console.log('📬 Total notificaciones desde BD:', window.NOTIFICACIONES_INICIALES?.length || 0);
    console.log('🔴 Total no leídas (PHP):', window.TOTAL_NO_LEIDAS);
    
    limpiarNotifAntiguasOcultas();
    
    setTimeout(() => {
        filtrarNotificacionesOcultas();
    }, 100);
    
    // =====================================================
    // EVENTOS DE LA CAMPANITA
    // =====================================================
    const bellBtn = document.getElementById('dropdownNotificaciones');

    if (bellBtn) {
        bellBtn.addEventListener('show.bs.dropdown', function () {
            const pendientesVisibles = document.querySelectorAll('.notif-item:not(.oculta)[data-pendiente="1"]').length;
            
            console.log('🔔 Campanita abierta. Pendientes visibles:', pendientesVisibles);
            
            if (pendientesVisibles > 0) {
                const badge = document.getElementById('notifCountBadge');
                if (badge) {
                    badge.classList.add('d-none');
                    badge.style.visibility = 'hidden';
                }

                document.querySelectorAll('.notif-item.bg-light:not(.oculta)').forEach(el => {
                    el.classList.remove('bg-light');
                    el.classList.add('bg-white');
                    el.dataset.pendiente = '0';
                });

                const fd = new FormData();
                fd.append('todas', 'true');
                
                console.log('📤 Marcando notificaciones como leídas en BD...');
                
                fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/notificaciones_leer.php', { 
                    method: 'POST', 
                    body: fd 
                })
                .then(response => response.json())
                .then(data => {
                    console.log('✅ Respuesta del servidor:', data);
                })
                .catch(error => {
                    console.error('❌ Error al marcar notificaciones:', error);
                });
            }
        });
    }
});
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
  <script src="/Sistema-de-Saldos-y-Pagos-/Public/js/notificaciones.js"></script>

</body>
</html>
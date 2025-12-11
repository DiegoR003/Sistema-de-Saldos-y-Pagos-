<?php
// Modules/cliente_home.php
require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/auth.php';
require_once __DIR__ . '/../App/pusher_config.php';

$pdo = db();
$u = current_user();

// 1. Identificar al cliente
$st = $pdo->prepare("SELECT id, empresa FROM clientes WHERE correo = ? LIMIT 1");
$st->execute([$u['correo']]);
$cliente = $st->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo '<div class="container py-5"><div class="alert alert-warning text-center">
        <h4><i class="bi bi-person-slash"></i> Cuenta no vinculada</h4>
        Tu usuario no tiene una ficha de cliente asociada. Contacta a soporte.
    </div></div>';
    return;
}

// 2. Calcular Saldo Inicial
$sqlSaldo = "
    SELECT COALESCE(SUM(cg.total), 0) 
    FROM cargos cg
    JOIN ordenes o ON o.id = cg.orden_id
    WHERE o.cliente_id = ? 
      AND cg.estatus IN ('emitido', 'vencido', 'pendiente')
";
$stSaldo = $pdo->prepare($sqlSaldo);
$stSaldo->execute([$cliente['id']]);
$saldo = (float)$stSaldo->fetchColumn();
?>

<div class="container py-5">
    
    <div class="text-center mb-5">
        <h2 class="fw-bold" style="color: #333;">Hola, <?= htmlspecialchars($cliente['empresa']) ?> 👋</h2>
        <p class="text-muted">Este es tu estado de cuenta actual.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 20px;">
                <div class="card-header bg-warning border-0 py-3 text-center">
                    <span class="text-dark fw-bold text-uppercase ls-1">
                        <i class="bi bi-wallet2 me-2"></i> Saldo por Pagar
                    </span>
                </div>

                <div class="card-body p-5 text-center bg-white position-relative">
                    
                    <div style="position:absolute; top:-50px; left:-50px; width:150px; height:150px; background:#fff8e1; border-radius:50%; z-index:0;"></div>

                    <div class="position-relative z-1">
                        <div id="saldoDisplay" class="display-3 fw-bold mb-3 <?= $saldo > 0.01 ? 'text-danger' : 'text-success' ?>">
                            $<?= number_format($saldo, 2) ?>
                        </div>
                        
                        <div id="saldoStatus">
                            <?php if ($saldo > 0.01): ?>
                                <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill fw-normal shadow-sm">
                                    <i class="bi bi-exclamation-circle-fill me-1"></i> Tienes pagos pendientes
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill fw-normal shadow-sm">
                                    <i class="bi bi-check-circle-fill me-1"></i> ¡Estás al corriente!
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-5 text-muted small">
                        <i class="bi bi-arrow-clockwise"></i> Actualización en tiempo real
                    </div>
                    
                    <!-- Debug Info (Comentar en producción) -->
                    <div class="mt-3 text-start small text-muted" id="debugInfo" style="background:#f8f9fa; padding:10px; border-radius:5px; display:none;">
                        <strong>🔧 Debug Info:</strong>
                        <div>Cliente ID: <code><?= $cliente['id'] ?></code></div>
                        <div>Pusher Key: <code><?= substr(PUSHER_APP_KEY ?? '', 0, 10) ?>...</code></div>
                        <div>Canal Saldo: <code>cliente_<?= $cliente['id'] ?></code></div>
                        <div>Canal Notif: <code>notificaciones_cliente_<?= $cliente['id'] ?></code></div>
                        <div id="pusherStatus">Estado: Conectando...</div>
                        <div id="lastEvent">Último evento: -</div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="document.getElementById('debugInfo').style.display = document.getElementById('debugInfo').style.display === 'none' ? 'block' : 'none'">
                        Toggle Debug
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- DIAGNÓSTICO DE ELEMENTOS ---
    const elSaldo = document.getElementById('saldoDisplay');
    const elBadge = document.getElementById('notifCountBadge');
    
    if(!elSaldo) console.error("❌ ERROR HTML: No encuentro el elemento id='saldoDisplay'. El saldo no se actualizará.");
    if(!elBadge) console.error("❌ ERROR HTML: No encuentro el elemento id='notifCountBadge'. La campanita no funcionará.");

    // --- CONFIGURACIÓN ---
    const KEY = "<?= defined('PUSHER_APP_KEY') ? PUSHER_APP_KEY : '' ?>";
    const CLUSTER = "<?= defined('PUSHER_APP_CLUSTER') ? PUSHER_APP_CLUSTER : '' ?>";
    const ID = <?= isset($cliente['id']) ? (int)$cliente['id'] : 0 ?>;

    if(!KEY || ID === 0) return;

    // Conexión
    // Pusher.logToConsole = true; // Actívalo si quieres ver todos los detalles técnicos
    const pusher = new Pusher(KEY, { cluster: CLUSTER, forceTLS: true });

    // =========================================================
    // CANAL 1: SALDO (Actualización en tiempo real)
    // =========================================================
    const chSaldo = pusher.subscribe('cliente_' + ID);
    
    chSaldo.bind('actualizar-saldo', function(data) {
        console.log("💰 RECIBIDO SALDO:", data);
        
        const display = document.getElementById('saldoDisplay');
        const status = document.getElementById('saldoStatus');

        if(display && data.nuevo_saldo !== undefined) {
            const val = parseFloat(data.nuevo_saldo);
            const fmt = new Intl.NumberFormat('es-MX', {style:'currency', currency:'MXN'}).format(val);
            
            // Efecto visual
            display.style.opacity = '0';
            setTimeout(() => {
                display.innerText = fmt;
                display.style.opacity = '1';
                
                // Actualizar colores y texto
                if(val > 0.01) {
                    display.className = 'display-3 fw-bold mb-3 text-danger';
                    if(status) status.innerHTML = '<span class="badge bg-danger fs-6 px-3 py-2 rounded-pill"><i class="bi bi-exclamation-circle-fill"></i> Pago Pendiente</span>';
                } else {
                    display.className = 'display-3 fw-bold mb-3 text-success';
                    if(status) status.innerHTML = '<span class="badge bg-success fs-6 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill"></i> Al corriente</span>';
                }
            }, 300);
        }
    });

    // =========================================================
    // CANAL 2: CAMPANITA (Notificaciones)
    // =========================================================
    const chNotif = pusher.subscribe('notificaciones_cliente_' + ID);
    
    chNotif.bind('nueva-notificacion', function(data) {
        console.log("🔔 RECIBIDA NOTIFICACIÓN:", data);

        // A) Reproducir Sonido
        const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
        audio.play().catch(() => console.log("Audio silenciado por navegador"));

        // B) Buscar y actualizar Badge (Intento robusto)
        let badge = document.getElementById('notifCountBadge');
        
        // Si no tiene ID, intentamos buscarlo por clase dentro del nav
        if(!badge) {
            const bells = document.querySelectorAll('.bi-bell');
            bells.forEach(b => {
                if(b.nextElementSibling && b.nextElementSibling.classList.contains('badge')) {
                    badge = b.nextElementSibling;
                }
            });
        }

        if (badge) {
            let num = parseInt(badge.innerText.trim()) || 0;
            badge.innerText = num + 1;
            badge.classList.remove('d-none');
            
            // Animación
            badge.style.transition = 'transform 0.2s';
            badge.style.transform = 'scale(1.5)';
            setTimeout(() => badge.style.transform = 'scale(1)', 300);
        } else {
            console.warn("⚠️ Aún no encuentro el badge en el DOM. Revisa el HTML de tu header.");
        }

        // C) Mostrar Alerta Flotante
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                icon: 'info',
                title: data.titulo,
                text: data.cuerpo
            });
        }
    });
});
</script>
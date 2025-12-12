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

// 3. Contar notificaciones pendientes del cliente
$sqlNotif = "SELECT COUNT(*) FROM notificaciones 
             WHERE tipo = 'externa' AND cliente_id = ? AND estado = 'pendiente' AND leida_en IS NULL";
$stNotif = $pdo->prepare($sqlNotif);
$stNotif->execute([$cliente['id']]);
$notifPendientes = (int)$stNotif->fetchColumn();
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

                    <!--<div class="mt-5 text-muted small">
                        <i class="bi bi-arrow-clockwise"></i> Actualización en tiempo real
                    </div>
                    
                     Debug Info 
                    <div class="mt-3 text-start small text-muted" id="debugInfo" style="background:#f8f9fa; padding:10px; border-radius:5px; display:none;">
                        <strong>🔧 Debug Info:</strong>
                        <div>Cliente ID: <code><?= $cliente['id'] ?></code></div>
                        <div>Pusher Key: <code><?= substr(PUSHER_APP_KEY ?? '', 0, 10) ?>...</code></div>
                        <div>Canal Saldo: <code>cliente_<?= $cliente['id'] ?></code></div>
                        <div>Canal Notif: <code>notificaciones_cliente_<?= $cliente['id'] ?></code></div>
                        <div>Notificaciones Pendientes (BD): <code><?= $notifPendientes ?></code></div>
                        <div id="pusherStatus">Estado Pusher: <span class="text-warning">Conectando...</span></div>
                        <div id="lastEvent">Último evento: -</div>
                        <div id="eventLog" style="max-height: 150px; overflow-y: auto; background: white; padding: 5px; margin-top: 5px; border-radius: 3px;">
                            <small class="text-muted">Log de eventos...</small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="toggleDebug()">
                        🔍 Toggle Debug
                    </button>
                </div>-->
            </div>
        </div>
    </div>
</div>

<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<script>
// Función para toggle debug
function toggleDebug() {
    const debugDiv = document.getElementById('debugInfo');
    debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
}

// Función para agregar log
function addLog(msg, tipo = 'info') {
    const logDiv = document.getElementById('eventLog');
    if (!logDiv) return;
    
    const colors = {
        info: '#0d6efd',
        success: '#198754',
        warning: '#ffc107',
        error: '#dc3545'
    };
    
    const timestamp = new Date().toLocaleTimeString('es-MX');
    const entry = document.createElement('div');
    entry.style.color = colors[tipo] || colors.info;
    entry.style.fontSize = '0.75rem';
    entry.innerHTML = `[${timestamp}] ${msg}`;
    logDiv.appendChild(entry);
    
    // Auto-scroll
    logDiv.scrollTop = logDiv.scrollHeight;
    
    console.log(`[${tipo.toUpperCase()}] ${msg}`);
}

document.addEventListener("DOMContentLoaded", function() {
    
    addLog('🚀 Iniciando sistema de notificaciones para cliente...', 'info');
    
    // 1. CONFIGURACIÓN
    if (typeof window.PUSHER_CONFIG === 'undefined' || !window.PUSHER_CONFIG.key) {
        console.warn("⚠️ Pusher no configurado en el header.");
        addLog('❌ Pusher no configurado', 'error');
        return;
    }

    const CLIENTE_ID = <?= isset($cliente['id']) ? (int)$cliente['id'] : 0 ?>;
    if (CLIENTE_ID === 0) {
        addLog('❌ Cliente ID no válido', 'error');
        return;
    }

    addLog(`✅ Cliente ID: ${CLIENTE_ID}`, 'success');

    // 2. CONEXIÓN
    const pusher = new Pusher(window.PUSHER_CONFIG.key, {
        cluster: window.PUSHER_CONFIG.cluster,
        forceTLS: true
    });

    // Eventos de conexión
    pusher.connection.bind('connected', function() {
        const statusEl = document.getElementById('pusherStatus');
        if (statusEl) statusEl.innerHTML = 'Estado Pusher: <span class="text-success">✅ Conectado</span>';
        addLog('✅ Pusher conectado exitosamente', 'success');
    });

    pusher.connection.bind('disconnected', function() {
        const statusEl = document.getElementById('pusherStatus');
        if (statusEl) statusEl.innerHTML = 'Estado Pusher: <span class="text-danger">❌ Desconectado</span>';
        addLog('❌ Pusher desconectado', 'error');
    });

    pusher.connection.bind('error', function(err) {
        addLog('❌ Error de Pusher: ' + JSON.stringify(err), 'error');
    });

    // =========================================================
    // CANAL 1: SALDO (Actualización Visual Inmediata)
    // =========================================================
    const chSaldo = pusher.subscribe('cliente_' + CLIENTE_ID);
    
    chSaldo.bind('pusher:subscription_succeeded', function() {
        addLog('✅ Suscrito al canal de saldo', 'success');
    });

    chSaldo.bind('pusher:subscription_error', function(status) {
        addLog('❌ Error al suscribirse al canal de saldo: ' + status, 'error');
    });
    
    chSaldo.bind('actualizar-saldo', function(data) {
        addLog('💰 Evento de saldo recibido: $' + (data.nuevo_saldo || 'N/A'), 'success');
        
        const lastEventEl = document.getElementById('lastEvent');
        if (lastEventEl) lastEventEl.textContent = 'Último evento: actualizar-saldo - ' + new Date().toLocaleTimeString();
        
        if(data.nuevo_saldo !== undefined) {
            const el = document.getElementById('saldoDisplay');
            if(el) {
                // Actualiza el número visualmente
                el.innerText = new Intl.NumberFormat('es-MX', {style:'currency', currency:'MXN'}).format(data.nuevo_saldo);
                if(parseFloat(data.nuevo_saldo) > 0.01) {
                    el.className = 'display-3 fw-bold mb-3 text-danger';
                } else {
                    el.className = 'display-3 fw-bold mb-3 text-success';
                }
                addLog('✅ Saldo actualizado en la UI', 'success');
            }
        }
    });

    // =========================================================
    // CANAL 2: NOTIFICACIONES (Sonido + Recarga Automática)
    // =========================================================
    const chNotif = pusher.subscribe('notificaciones_cliente_' + CLIENTE_ID);
    
    chNotif.bind('pusher:subscription_succeeded', function() {
        addLog('✅ Suscrito al canal de notificaciones', 'success');
    });

    chNotif.bind('pusher:subscription_error', function(status) {
        addLog('❌ Error al suscribirse al canal de notificaciones: ' + status, 'error');
    });

    chNotif.bind('nueva-notificacion', function(data) {
        addLog('📬 ¡Nueva notificación recibida!', 'warning');
        addLog('Datos: ' + JSON.stringify(data), 'info');
        
        const lastEventEl = document.getElementById('lastEvent');
        if (lastEventEl) lastEventEl.textContent = 'Último evento: nueva-notificacion - ' + new Date().toLocaleTimeString();

        // A) Sonido
        try { 
            const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
            audio.play().catch((err) => {
                addLog('⚠️ No se pudo reproducir sonido: ' + err.message, 'warning');
            });
            addLog('🔊 Sonido reproducido', 'success');
        } catch(e) {
            addLog('❌ Error al reproducir sonido: ' + e.message, 'error');
        }

        // B) Actualizar Badge visualmente (Efecto inmediato)
        const badge = document.getElementById('notifCountBadge');
        if (badge) {
            let n = parseInt(badge.innerText.replace(/[^0-9]/g, '') || '0');
            badge.innerText = n + 1;
            badge.classList.remove('d-none');
            badge.style.visibility = 'visible';
            addLog(`✅ Badge actualizado: ${n} → ${n + 1}`, 'success');
        } else {
            addLog('⚠️ No se encontró el elemento del badge', 'warning');
        }

        // C) RECARGAR PÁGINA (Para que el header.php traiga la notificación real de la BD)
        addLog('⏳ Recargando página en 2 segundos...', 'info');
        
        setTimeout(() => {
            addLog('🔄 Recargando ahora...', 'warning');
            window.location.reload();
        }, 2000); 
    });

    addLog('✅ Sistema de notificaciones inicializado', 'success');
});
</script>
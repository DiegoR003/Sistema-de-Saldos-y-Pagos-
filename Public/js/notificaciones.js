// ============================================================
// 🔔 SISTEMA DE NOTIFICACIONES EN TIEMPO REAL
// ============================================================

(function () {
  'use strict';

  // ============================================================
  // 📢 CONFIGURACIÓN DE SONIDO
  // ============================================================
  const audioNotif = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
  audioNotif.volume = 0.5; // Volumen al 50%
  
  // Desbloquear audio con la primera interacción del usuario
  let audioDesbloqueado = false;
  document.addEventListener('click', function desbloquearAudio() {
    if (!audioDesbloqueado) {
      audioNotif.play().then(() => audioNotif.pause()).catch(() => {});
      audioDesbloqueado = true;
    }
  }, { once: true });

  // ============================================================
  // 🔴 PUNTO ROJO EN PESTAÑA (Favicon dinámico)
  // ============================================================
  const faviconOriginal = document.querySelector('link[rel="icon"]')?.href || '/assets/Banana.png';
  let faviconConPunto = null;

  // Crear favicon con punto rojo
  function crearFaviconConPunto() {
    const canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 32;
    const ctx = canvas.getContext('2d');
    
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
      // Dibujar el favicon original
      ctx.drawImage(img, 0, 0, 32, 32);
      
      // Dibujar punto rojo en la esquina superior derecha
      ctx.fillStyle = '#FF0000';
      ctx.beginPath();
      ctx.arc(24, 8, 6, 0, 2 * Math.PI);
      ctx.fill();
      
      // Borde blanco para que resalte
      ctx.strokeStyle = '#FFFFFF';
      ctx.lineWidth = 2;
      ctx.stroke();
      
      faviconConPunto = canvas.toDataURL('image/png');
    };
    img.src = faviconOriginal;
  }

  function actualizarFavicon(mostrarPunto) {
    const link = document.querySelector('link[rel="icon"]') || document.createElement('link');
    link.rel = 'icon';
    link.href = mostrarPunto && faviconConPunto ? faviconConPunto : faviconOriginal;
    
    if (!document.querySelector('link[rel="icon"]')) {
      document.head.appendChild(link);
    }
  }

  // ============================================================
  // 🎯 INICIALIZACIÓN
  // ============================================================
  
  // Verificar configuración
  if (!window.APP_USER || !window.PUSHER_CONFIG || !APP_USER.id) {
    console.warn('❌ Pusher: Faltan configuraciones de usuario.');
    return;
  }

  const { id } = APP_USER;
  const { key, cluster } = PUSHER_CONFIG;

  // Prevenir múltiples inicializaciones
  if (window.pusherInitialized) {
    console.log('⚠️ Pusher ya está inicializado, saltando...');
    return;
  }
  window.pusherInitialized = true;

  // Inicializar Pusher
  const pusher = new Pusher(key, {
    cluster: cluster,
    forceTLS: true
  });

  const channelName = `notificaciones_user_${id}`;
  const channel = pusher.subscribe(channelName);
  
  console.log('📡 Pusher conectado. Escuchando canal:', channelName);

  // Crear favicon con punto
  crearFaviconConPunto();

  // ============================================================
  // 📌 REFERENCIAS AL DOM
  // ============================================================
  const badge = document.getElementById('notifCountBadge');
  const btnBell = document.getElementById('dropdownNotificaciones');
  const listaNotificaciones = document.getElementById('listaNotificaciones');

  // ============================================================
  // 🔢 FUNCIÓN: Incrementar contador
  // ============================================================
  function incrementarContador() {
    if (!badge) return;
    
    // Obtener notificaciones ocultas
    const ocultas = getNotifOcultas();
    
    // Contar solo las visibles y pendientes
    const pendientesVisibles = document.querySelectorAll('.notif-item:not(.oculta)[data-pendiente="1"]').length;
    const nuevoConteo = pendientesVisibles + 1;
    
    badge.textContent = nuevoConteo;
    badge.classList.remove('d-none');
    badge.style.visibility = 'visible';
    
    // Activar punto rojo en favicon
    actualizarFavicon(true);
  }

  // ============================================================
  // ➕ FUNCIÓN: Agregar notificación a la lista
  // ============================================================
  function agregarNotificacionALista(n) {
    if (!listaNotificaciones) return;

    // Remover mensaje de "vacío" si existe
    const emptyMsg = document.getElementById('noNotifMsg');
    if (emptyMsg) emptyMsg.remove();

    const notifId = n.id || Date.now();
    const texto = n.titulo || n.texto || 'Nueva notificación';
    const cuerpo = n.cuerpo || '';

    // Verificar si la notificación ya está oculta
    const ocultas = getNotifOcultas();
    const estaOculta = ocultas.includes(notifId);

    const li = document.createElement('li');
    li.className = `notif-item px-3 py-2 border-bottom small bg-light ${estaOculta ? 'oculta' : ''}`;
    li.id = `notif-${notifId}`;
    li.dataset.notifId = notifId;
    li.dataset.pendiente = '1';
    
    li.innerHTML = `
      <button class="btn-close-notif" onclick="ocultarNotif(event, ${notifId})" title="Ocultar">
        <i class="bi bi-x-lg"></i>
      </button>
      
      <div class="fw-semibold mb-1 pe-3">${texto}</div>
      <div class="text-muted" style="font-size: 0.75rem;">${cuerpo}</div>
      <div class="text-end mt-1 text-primary" style="font-size: 0.65rem;">
        Justo ahora
      </div>
    `;

    // Insertar al inicio de la lista (después del header)
    const primerItem = listaNotificaciones.querySelector('.notif-item');
    if (primerItem) {
      primerItem.parentNode.insertBefore(li, primerItem);
    } else {
      listaNotificaciones.appendChild(li);
    }
  }

  // ============================================================
  // 🍞 FUNCIÓN: Mostrar Toast flotante
  // ============================================================
  function mostrarToast(n) {
    const titulo = n.titulo || 'Notificación';
    const cuerpo = n.cuerpo || n.texto || '';

    // Remover toasts anteriores
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());

    const toastHtml = `
      <div class="toast-notification position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
        <div class="toast show bg-white shadow-lg" role="alert">
          <div class="toast-header bg-primary text-white">
            <i class="bi bi-bell-fill me-2"></i>
            <strong class="me-auto">Banana Group</strong>
            <small>Ahora</small>
            <button type="button" class="btn-close btn-close-white" onclick="this.closest('.toast-notification').remove()"></button>
          </div>
          <div class="toast-body">
            <strong>${titulo}</strong><br>
            <span class="text-muted">${cuerpo}</span>
          </div>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
      const toast = document.querySelector('.toast-notification');
      if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 300);
      }
    }, 5000);
  }

  // ============================================================
  // 🔊 FUNCIÓN: Reproducir sonido
  // ============================================================
  function reproducirSonido() {
    // Reiniciar el audio si ya estaba sonando
    audioNotif.currentTime = 0;
    
    audioNotif.play().catch(error => {
      console.warn("🔇 El navegador bloqueó el sonido (requiere interacción previa):", error);
    });
  }

  // ============================================================
  // 📥 MANEJADOR PRINCIPAL: Nueva notificación
  // ============================================================
  function manejarNuevaNotificacion(data) {
    console.log('🔔 Notificación recibida:', data);
    
    const notificacion = (typeof data === 'string') ? JSON.parse(data) : data;
    
    // 1. Agregar a la lista
    agregarNotificacionALista(notificacion);
    
    // 2. Incrementar contador
    incrementarContador();
    
    // 3. Mostrar toast
    mostrarToast(notificacion);
    
    // 4. Reproducir sonido
    reproducirSonido();
  }

  // ============================================================
  // 🎧 ESCUCHAR EVENTOS DE PUSHER
  // ============================================================
  channel.bind('nueva-notificacion', manejarNuevaNotificacion);

  // Confirmar suscripción
  channel.bind('pusher:subscription_succeeded', function() {
    console.log('✅ Suscripción exitosa al canal:', channelName);
  });

  // Manejar errores
  channel.bind('pusher:subscription_error', function(error) {
    console.error('❌ Error en la suscripción:', error);
  });

  // ============================================================
  // 👁️ QUITAR PUNTO ROJO AL ABRIR NOTIFICACIONES
  // ============================================================
  if (btnBell) {
    btnBell.addEventListener('show.bs.dropdown', function() {
      // Quitar punto rojo del favicon
      actualizarFavicon(false);
    });
  }

  // También quitar punto rojo cuando la ventana recupera el foco
  window.addEventListener('focus', function() {
    const badgeVisible = badge && !badge.classList.contains('d-none');
    if (!badgeVisible) {
      actualizarFavicon(false);
    }
  });

  console.log('✅ Sistema de notificaciones inicializado correctamente');
})();
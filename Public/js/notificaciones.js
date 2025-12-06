// ============================================================
  // 🔔 CONFIGURACIÓN DE SONIDO
  // ============================================================
  // Opción A: Si tienes el archivo en tu carpeta (Recomendado):
  // const audioNotif = new Audio('/Sistema-de-Saldos-y-Pagos-/Public/assets/sounds/notification.mp3');
  
  // Opción B: Sonido de prueba online (Úsalo para probar ahorita):
  const audioNotif = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
  // ============================================================



(function () {
  // 1. Verificamos que existan las variables globales definidas en header.php
  if (!window.APP_USER || !window.PUSHER_CONFIG || !APP_USER.id) {
    console.warn('Pusher: Faltan configuraciones de usuario.');
    return;
  }

  const { id } = APP_USER; // Solo necesitamos el ID, ya que el PHP se encarga de los roles
  const { key, cluster } = PUSHER_CONFIG;

  // 2. Inicializar Pusher
  const pusher = new Pusher(key, {
    cluster: cluster,
    forceTLS: true
  });

  // 3. Suscribirse al canal CORRECTO (El mismo que pusimos en notifications.php)
  const channelName = 'notificaciones_user_' + id;
  const channel = pusher.subscribe(channelName);
  
  console.log('📡 Pusher conectado. Escuchando canal:', channelName);

  // Referencias al DOM
  const badge = document.getElementById('notifCountBadge');
  const btnBell = document.getElementById('dropdownNotificaciones');
  const list  = document.querySelector('.notif-menu'); // Ajustado para buscar por clase si ID falla

  // Función para actualizar el contador rojo
  function incrementCounter() {
    // Si ya existe el badge, le sumamos 1
    if (badge) {
      let current = parseInt(badge.textContent || '0', 10);
      badge.textContent = current + 1;
      badge.classList.remove('d-none');
    } else if (btnBell) {
      // Si no existe, lo creamos
      const newBadge = document.createElement('span');
      newBadge.id = 'notifCountBadge';
      newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
      newBadge.innerText = '1';
      btnBell.appendChild(newBadge);
    }
  }

  // Función para agregar la notificación a la lista visualmente
  function prependNotification(n) {
    if (!list) return;

    // Remover mensaje de "vacío" si existe
    const emptyMsg = list.querySelector('.text-center.text-muted');
    if (emptyMsg) emptyMsg.remove();

    // Crear el elemento LI
    const li = document.createElement('li');
    li.className = 'px-3 py-2 border-bottom small bg-light'; // Estilo "no leído"
    
    const texto = n.texto || n.titulo || n.cuerpo || 'Nueva notificación';
    
    li.innerHTML = `
      <div class="fw-semibold mb-1" style="line-height: 1.3;">
        ${texto}
      </div>
      <div class="text-muted" style="font-size: 0.75rem;">
        <i class="bi bi-clock me-1"></i> Justo ahora
      </div>
    `;

    // Insertar justo después del encabezado (el primer li)
    const header = list.querySelector('li:first-child');
    if (header) {
      header.insertAdjacentElement('afterend', li);
    } else {
      list.prepend(li);
    }
  }

  // Función para mostrar Toast flotante
  function showToast(n) {
    const titulo = n.titulo || 'Notificación';
    const cuerpo = n.cuerpo || n.texto || '';

    const toastHtml = `
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
      <div class="toast show bg-white" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
          <strong class="me-auto text-primary">Banana Group</strong>
          <small>Ahora</small>
          <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
          <strong>${titulo}</strong><br>${cuerpo}
        </div>
      </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    
    // Auto-eliminar a los 5 segundos
    setTimeout(() => {
        const t = document.querySelector('.toast-container:last-child');
        if(t) t.remove();
    }, 5000);
  }

  // Manejador del evento
  function handleNotification(data) {
    console.log('🔔 Notificación recibida:', data);
    const n = (typeof data === 'string') ? JSON.parse(data) : data;
    
    prependNotification(n);
    incrementCounter();
    showToast(n);
  }

  // 2. 🔊 REPRODUCIR SONIDO
    // Usamos catch porque los navegadores bloquean el sonido si el usuario 
    // no ha interactuado con la página (hecho al menos un clic en cualquier lado).
    audioNotif.play().catch(error => {
        console.warn("El navegador bloqueó el sonido automático (requiere interacción previa):", error);
    });
  

  // 4. Escuchar el evento
  channel.bind('nueva-notificacion', handleNotification);
})();
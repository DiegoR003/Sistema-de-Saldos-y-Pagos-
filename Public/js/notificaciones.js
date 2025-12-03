(function () {
  if (!window.APP_USER || !window.PUSHER_CONFIG || !APP_USER.id) {
    return;
  }

  const { id, rol } = APP_USER;
  const { key, cluster } = PUSHER_CONFIG;

  const pusher = new Pusher(key, {
    cluster: cluster,
    forceTLS: true
  });

  const userChannel = pusher.subscribe('priv-user-' + id);
  const roleChannel = pusher.subscribe('role-' + rol);

  const badge = document.getElementById('notifCountBadge');
  const list  = document.getElementById('notifList');
  const empty = document.getElementById('notifEmpty');

  let unseen = badge && !badge.classList.contains('d-none')
    ? parseInt(badge.textContent || '0', 10)
    : 0;

  function updateBadge() {
    if (!badge) return;
    if (unseen > 0) {
      badge.textContent = unseen;
      badge.classList.remove('d-none');
    } else {
      badge.textContent = '0';
      badge.classList.add('d-none');
    }
  }

  function incrementCounter() {
    unseen++;
    updateBadge();
  }

  function prependNotification(n) {
    if (!list) return;

    if (empty) {
      empty.remove();
    }

    const footer = list.querySelector('.notif-footer');

    const li = document.createElement('li');
    li.className = 'notif-item unread';
    const texto = n.texto || n.titulo || n.cuerpo || 'Notificación';
    const hace  = n.hace  || 'hace unos segundos';

    li.innerHTML = `
      <div class="small fw-semibold mb-1" style="line-height:1.3;">
        ${texto}
      </div>
      <div class="text-muted" style="font-size:0.75rem;">
        <i class="bi bi-clock me-1"></i>${hace}
      </div>
    `;

    if (footer) {
      list.insertBefore(li, footer);
    } else {
      list.appendChild(li);
    }
  }

  function handleNotification(data) {
    try {
      const n = (typeof data === 'string') ? JSON.parse(data) : data;
      prependNotification(n);
      incrementCounter();
    } catch (err) {
      console.error('Error parseando notificación', err, data);
    }
  }

  userChannel.bind('nueva-notificacion', handleNotification);
  roleChannel.bind('nueva-notificacion', handleNotification);
})();

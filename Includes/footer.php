   <!-- Bootstrap JS (bundle con Popper) -->
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script src="https://js.pusher.com/8.2/pusher.min.js"></script>

<script src="/Sistema-de-Saldos-y-Pagos-/Public/js/notificaciones.js"></script>
<script>
  // Parche mínimo: SOLO para los links dentro del offcanvas (móvil).
  // Forzamos la navegación y cerramos el offcanvas de forma controlada.
  (function () {
    const off = document.getElementById('mobileSidebar');
    if (!off) return;

    off.querySelectorAll('a[href]').forEach(a => {
      a.addEventListener('click', function (e) {
        // si el href es real, navegamos nosotros
        const url = this.getAttribute('href');
        if (!url || url === '#') return;
        e.preventDefault();

        // cierra el offcanvas (si está abierto)
        const oc = bootstrap.Offcanvas.getInstance(off) || bootstrap.Offcanvas.getOrCreateInstance(off);
        oc.hide();

        // tras la animación, navega (recarga completa)
        setTimeout(() => { window.location.href = url; }, 150);
      });
    });
  })();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const bell = document.getElementById('dropdownNotificaciones');
  if (!bell) return;

  let notifMarked = false; // para no spamear el endpoint cada vez que abras

  bell.addEventListener('show.bs.dropdown', function () {
    if (notifMarked) return;

    fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/notificaciones_leer.php', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(r => r.json())
    .then(data => {
      if (!data.ok) {
        console.error('Error marcando notificaciones:', data.error);
        return;
      }

      // Quitar burbujas / badges de contador
      const badgeMain = document.querySelector('.notif-badge');
      if (badgeMain) badgeMain.remove();

      const headerPill = document.querySelector('.notif-menu .badge.bg-danger');
      if (headerPill) headerPill.remove();

      // Quitar estilo de "no leída" a los items
      document.querySelectorAll('.notif-item.unread').forEach(function(li) {
        li.classList.remove('unread');
      });

      notifMarked = true;
    })
    .catch(err => {
      console.error('Error AJAX notificaciones_leer:', err);
    });
  });
});
</script>

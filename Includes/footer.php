<?php require_once __DIR__ . '/../Includes/chat.php'; ?>
 
 <!-- Bootstrap JS (bundle con Popper) -->
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://js.pusher.com/8.2/pusher.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



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

<script>
/* =========================================================
   A. CONFIGURACIÓN GLOBAL (Toasts y Confirmaciones)
   ========================================================= */

// Definir el estilo "Toast" (Notificación pequeña en la esquina)
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 4000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  }
});

/* =========================================================
   B. DETECTAR MENSAJES DE PHP (URL params: ok, msg, err)
   ========================================================= */
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  
  if (params.has('ok')) {
    const msg = params.get('msg') || 'Operación exitosa';
    Toast.fire({
      icon: 'success',
      title: decodeURIComponent(msg.replace(/\+/g, ' '))
    });
  }
  
  if (params.has('err')) {
    const err = params.get('err') || 'Ocurrió un error';
    Toast.fire({
      icon: 'error',
      title: decodeURIComponent(err.replace(/\+/g, ' '))
    });
  }
});

/* =========================================================
   C. FUNCIÓN PARA CONFIRMAR ACCIONES (Formularios)
   ========================================================= */
function confirmarAccion(event, titulo, texto, btnTexto, colorBtn = '#fdd835') {
  event.preventDefault(); // Detiene el envío inmediato del formulario
  const form = event.target; // El formulario que disparó el evento

  Swal.fire({
    title: titulo || '¿Estás seguro?',
    text: texto || "Esta acción no se puede deshacer",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: colorBtn,
    cancelButtonColor: '#6c757d',
    confirmButtonText: btnTexto || 'Sí, continuar',
    cancelButtonText: 'Cancelar',
    // Personalización para que combine con Banana Group
    color: '#000',
    background: '#fff'
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit(); // Si dice que sí, enviamos el formulario manualmente
    }
  });
}
</script>

 


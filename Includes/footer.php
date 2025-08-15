<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

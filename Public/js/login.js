// 1. Enviar Código
document.getElementById('formSendCode')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSendCode');
    const email = document.getElementById('recupEmail').value;
    
    const originalText = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

    let fd = new FormData();
    fd.append('email', email);

    try {
        const res = await fetch('api/auth_forgot_code.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            document.getElementById('finalEmail').value = email;
            document.getElementById('step1').classList.add('d-none');
            document.getElementById('step2').classList.remove('d-none');
        } else {
            Swal.fire('Error', data.msg || 'No se pudo enviar el correo', 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Error de conexión con el servidor', 'error');
    }
    btn.disabled = false; btn.innerHTML = originalText;
});

// 2. Confirmar Cambio
document.getElementById('formResetFinal')?.addEventListener('submit', async function(e) {
    // ... resto del código duplicado
});

function volverPaso1() {
    document.getElementById('step1').classList.remove('d-none');
    document.getElementById('step2').classList.add('d-none');
}

// Animación burbuja (está bien, no tocar)
  const bubble = document.querySelector(".bubble");
  let x = 100, y = 100;
  let dx = 1, dy = 1;
  const speed = 16;

  function move() {
    const w = window.innerWidth - bubble.offsetWidth;
    const h = window.innerHeight - bubble.offsetHeight;
    x += dx;
    y += dy;
    if (x <= 0 || x >= w) dx *= -1;
    if (y <= 0 || y >= h) dy *= -1;
    bubble.style.left = x + "px";
    bubble.style.top = y + "px";
    setTimeout(move, speed);
  }
  move();


  // 1. ENVIAR CÓDIGO (Paso 1)
const formSend = document.getElementById('formSendCode');
if (formSend) {
    formSend.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSendCode');
        const email = document.getElementById('recupEmail').value;
        
        // Bloquear botón para evitar doble envío
        if (btn.disabled) return; 
        const txtOriginal = btn.innerHTML;
        btn.disabled = true; 
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

        let fd = new FormData();
        fd.append('email', email);

        try {
            const res = await fetch('api/auth_forgot_code.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                // ÉXITO: Guardamos el correo en el campo oculto del paso 2
                document.getElementById('finalEmail').value = email; 
                
                // Cambiamos de pantalla
                document.getElementById('step1').classList.add('d-none');
                document.getElementById('step2').classList.remove('d-none');
            } else {
                Swal.fire('Error', data.msg || 'No se pudo enviar', 'error');
                btn.disabled = false;
                btn.innerHTML = txtOriginal;
            }
        } catch (err) {
            console.error(err);
            Swal.fire('Error', 'Error de conexión', 'error');
            btn.disabled = false;
            btn.innerHTML = txtOriginal;
        }
    });
}

// 2. CONFIRMAR CAMBIO (Paso 2 - Blindado contra doble clic)
const formReset = document.getElementById('formResetFinal');
if (formReset) {
    formReset.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // 1. OBTENER BOTÓN Y BLOQUEARLO
        const btnSubmit = formReset.querySelector('button[type="submit"]');
        if (btnSubmit.disabled) return; // Si ya está procesando, no hacer nada
        
        // Guardar texto original y poner "Cargando..."
        const txtOriginal = btnSubmit.innerText;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

        // 2. VALIDAR
        const p1 = document.getElementById('newPass').value;
        const p2 = document.getElementById('confPass').value;
        
        // Usamos el campo oculto o el del paso anterior como respaldo
        const email = document.getElementById('finalEmail').value || document.getElementById('recupEmail').value;

        if (p1 !== p2) {
            Swal.fire('Error', 'Las contraseñas no coinciden', 'warning');
            btnSubmit.disabled = false;
            btnSubmit.innerText = txtOriginal;
            return;
        }

        // 3. PREPARAR DATOS (Forzando el email)
        let fd = new FormData(formReset);
        fd.set('email', email); 

        try {
            const res = await fetch('api/auth_reset_final.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                // Ocultar modal primero
                const modalEl = document.getElementById('modalRecuperar');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if(modal) modal.hide();

                Swal.fire({
                    icon: 'success',
                    title: '¡Contraseña Actualizada!',
                    text: 'Inicia sesión con tu nueva clave.',
                    confirmButtonColor: '#fdd835',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.reload(); // Recarga limpia
                });
            } else {
                Swal.fire('Error', data.msg, 'error');
                // Solo reactivar botón si falló (para que intente de nuevo)
                btnSubmit.disabled = false;
                btnSubmit.innerText = txtOriginal;
            }
        } catch (err) {
            Swal.fire('Error', 'Error de red', 'error');
            btnSubmit.disabled = false;
            btnSubmit.innerText = txtOriginal;
        }
    });
}

function volverPaso1() {
    document.getElementById('step2').classList.add('d-none');
    document.getElementById('step1').classList.remove('d-none');
    // Reactivar botón paso 1
    const btn = document.getElementById('btnSendCode');
    if(btn) {
        btn.disabled = false;
        btn.innerHTML = 'Enviar Código <i class="bi bi-arrow-right ms-2"></i>';
    }

    
}

// Rutas de los SVG
  const pathOjoAbierto = "M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z";
  const pathOjoCerrado = "M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z";

  // Seleccionamos TODOS los iconos clickeables (Login y Modal)
  const iconosOjo = document.querySelectorAll('.clickable-icon');

  iconosOjo.forEach(function(icono) {
    icono.addEventListener('click', function () {
      // Navegamos por el DOM: buscamos el contenedor padre más cercano
      const contenedor = this.closest('.with-icon-right');
      
      // Si encuentra el contenedor, buscamos el input y el path del SVG dentro de él
      if (contenedor) {
        const input = contenedor.querySelector('input');
        const svgPath = this.querySelector('path');

        if (input && svgPath) {
          // Alternamos el tipo de input
          const esPassword = input.getAttribute('type') === 'password';
          input.setAttribute('type', esPassword ? 'text' : 'password');

          // Alternamos el dibujo del SVG
          svgPath.setAttribute('d', esPassword ? pathOjoCerrado : pathOjoAbierto);
        }
      }
    });
  });
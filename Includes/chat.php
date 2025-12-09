<div id="chatWidget" class="chat-widget closed">
    
    <div class="chat-header" id="chatHeaderBtn">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-chat-text-fill fs-5"></i>
            <span class="fw-bold" id="chatTitle">Mensajes</span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button id="btnVolver" type="button" class="btn-icon d-none" title="Volver">
                <i class="bi bi-arrow-left-circle-fill fs-5"></i>
            </button>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
    </div>

    <div class="chat-view-list" id="vistaLista">
        <div class="p-2 bg-light border-bottom">
            <input type="text" id="chatSearch" class="form-control form-control-sm rounded-pill" placeholder="Buscar un chat o iniciar uno nuevo">
        </div>
        <div id="listaContactos" class="list-group list-group-flush contact-list-scroll">
            <div class="text-center p-4 text-muted small">Cargando clientes...</div>
        </div>
    </div>

    <div class="chat-view-conv d-none" id="vistaChat">
        <div id="chatMsgs" class="flex-grow-1 p-3 scroll-container bg-chat"></div>
        
        <div class="p-2 bg-white border-top">
            <form id="formEnviarChat" class="d-flex gap-2 w-100 m-0" autocomplete="off">
                <input type="hidden" id="chatClienteId" name="cliente_id">
                <input type="text" id="chatInputMsg" class="form-control rounded-pill bg-light border-0" placeholder="Escribe un mensaje..." required>
                <button type="submit" class="btn btn-warning rounded-circle shadow-sm" style="width:40px; height:40px; padding:0;">
                    <i class="bi bi-send-fill text-dark small"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    /* Widget Container */
    .chat-widget {
        position: fixed; bottom: 0; right: 20px;
        width: 340px; height: 480px;
        background: #fff;
        border-radius: 12px 12px 0 0;
        box-shadow: 0 0 25px rgba(0,0,0,0.15);
        z-index: 9999;
        display: flex; flex-direction: column;
        transition: transform 0.3s ease-in-out;
        border: 1px solid #ddd;
        font-family: -apple-system, system-ui, sans-serif;
    }
    .chat-widget.closed { transform: translateY(430px); }

    /* Header */
    .chat-header {
        height: 50px; background: #fdd835; /* Amarillo */
        padding: 0 15px; display: flex; align-items: center; justify-content: space-between;
        cursor: pointer; border-radius: 12px 12px 0 0;
        user-select: none;
    }
    
    /* Listas y Scroll */
    .chat-view-list { flex: 1; display: flex; flex-direction: column; background: #fff; overflow: hidden; }
    .contact-list-scroll { flex: 1; overflow-y: auto; }
    .chat-view-conv { flex: 1; display: flex; flex-direction: column; height: 100%; }
    .scroll-container { overflow-y: auto; }
    .bg-chat { background-color: #efe7dd; }

    /* Items */
    .contact-item {
        cursor: pointer; padding: 10px 15px; border-bottom: 1px solid #f5f5f5;
        display: flex; align-items: center; transition: 0.2s;
    }
    .contact-item:hover { background-color: #fff9c4; }
    
    .avatar-circle {
        width: 36px; height: 36px; background: #333; color: #fdd835;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 0.85rem; margin-right: 10px;
    }

    /* Mensajes */
    .msg-row { display: flex; margin-bottom: 8px; }
    .msg-row.me { justify-content: flex-end; }
    .msg-bubble {
        max-width: 75%; padding: 8px 12px; border-radius: 10px;
        font-size: 0.9rem; position: relative; box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    }
    .msg-row.me .msg-bubble { background: #dcf8c6; border-top-right-radius: 0; }
    .msg-row.them .msg-bubble { background: #fff; border-top-left-radius: 0; }
    .msg-time { font-size: 0.65rem; color: #999; text-align: right; margin-top: 2px; }

    .btn-icon { background: none; border: none; padding: 0; color: #212529; }
    .toggle-icon { transition: 0.3s; }
    .chat-widget.closed .toggle-icon { transform: rotate(180deg); }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const widget = document.getElementById('chatWidget');
    const header = document.getElementById('chatHeaderBtn');
    const title = document.getElementById('chatTitle');
    const btnVolver = document.getElementById('btnVolver');
    
    const vistaLista = document.getElementById('vistaLista');
    const vistaChat = document.getElementById('vistaChat');
    const listaContactos = document.getElementById('listaContactos');
    const msgsContainer = document.getElementById('chatMsgs');
    const searchInput = document.getElementById('chatSearch');
    const formChat = document.getElementById('formEnviarChat'); // Referencia al formulario
    
    let allContacts = [];

    // 1. ABRIR / CERRAR
    header.addEventListener('click', (e) => {
        // Ignorar si clic en botón volver
        if(e.target.closest('#btnVolver')) return;
        
        widget.classList.toggle('closed');
        
        // Cargar contactos al abrir si no está cerrado
        if(!widget.classList.contains('closed')) {
            cargarContactos();
        }
    });

    // 2. CARGAR CONTACTOS
    async function cargarContactos() {
        try {
            const res = await fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/chat_list.php');
            if(!res.ok) throw new Error('Error API');
            allContacts = await res.json();
            renderContactos(allContacts);
        } catch(err) { 
            console.error(err);
            listaContactos.innerHTML = '<div class="p-3 text-danger small text-center">Error cargando lista.</div>';
        }
    }

    function renderContactos(data) {
        listaContactos.innerHTML = '';
        if(data.length === 0) {
            listaContactos.innerHTML = '<div class="p-4 text-center text-muted small">No se encontraron chats.</div>';
            return;
        }
        
        data.forEach(c => {
            const div = document.createElement('div');
            div.className = 'contact-item d-flex align-items-center p-3 border-bottom';
            div.style.cursor = 'pointer';
            
            //  AQUÍ PASAMOS EL TIPO DE CONTACTO (cliente o staff)
            div.onclick = () => abrirChat(c.id, c.nombre, c.tipo_contacto);
            
            div.innerHTML = `
                <div class="avatar-circle me-3 flex-shrink-0 overflow-hidden d-flex align-items-center justify-content-center">
                    ${c.avatar_html}
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark text-truncate" style="font-size:0.9rem">${c.nombre}</span>
                        <span class="small text-muted" style="font-size:0.7rem">${c.hora}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="${c.style} text-truncate me-2" style="max-width: 160px;">${c.ultimo_msg}</div>
                        ${c.extra_html ? c.extra_html : ''}
                    </div>
                </div>
            `;
            listaContactos.appendChild(div);
        });
    }

    // Buscador
    if(searchInput) {
        searchInput.addEventListener('keyup', (e) => {
            const val = e.target.value.toLowerCase();
            const filtrados = allContacts.filter(c => c.nombre.toLowerCase().includes(val));
            renderContactos(filtrados);
        });
    }

    // 3. ABRIR CHAT (Recibe ID, Nombre y TIPO)
    window.abrirChat = async function(clienteId, nombre, tipo) {
        vistaLista.classList.add('d-none');
        vistaChat.classList.remove('d-none');
        btnVolver.classList.remove('d-none');
        
        title.textContent = nombre;
        document.getElementById('chatClienteId').value = clienteId;

        //  GUARDAMOS EL TIPO EN EL FORMULARIO (cliente o staff)
        formChat.dataset.tipo = tipo || 'cliente'; 
        
        msgsContainer.innerHTML = '<div class="text-center p-3 small text-muted">Cargando...</div>';

        try {
            const res = await fetch(`/Sistema-de-Saldos-y-Pagos-/Public/api/chat_history.php?cliente_id=${clienteId}`);
            const msgs = await res.json();
            
            msgsContainer.innerHTML = '';
            // Detectar mi ID (Asumimos que viene del header PHP en window.APP_USER)
            const miId = (window.APP_USER && window.APP_USER.id) ? window.APP_USER.id : 0;

            if(msgs.length === 0) {
                msgsContainer.innerHTML = '<div class="text-center p-5 text-muted small">Inicia la conversación 👋</div>';
            }

            msgs.forEach(m => {
                const esMio = (m.tipo_autor === 'usuario'); 
                pintarBurbuja(m.mensaje, m.hora, esMio);
            });
            scrollFondo();

        } catch(e) { console.error(e); }
    };

    // 4. VOLVER
    btnVolver.addEventListener('click', (e) => {
        e.stopPropagation();
        vistaChat.classList.add('d-none');
        vistaLista.classList.remove('d-none');
        btnVolver.classList.add('d-none');
        title.textContent = 'Mensajes';
        cargarContactos(); // Actualizar lista por si hay nuevos
    });

    // 5. ENVIAR
    formChat.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('chatInputMsg');
        const txt = input.value.trim();
        const cid = document.getElementById('chatClienteId').value;
        
        //  RECUPERAMOS EL TIPO QUE GUARDAMOS AL ABRIR
        const tipo = formChat.dataset.tipo || 'cliente';

        if(!txt) return;

        // UI Optimista
        const now = new Date();
        const hora = now.getHours() + ':' + String(now.getMinutes()).padStart(2,'0');
        pintarBurbuja(txt, hora, true);
        scrollFondo();
        input.value = '';

        const fd = new FormData();
        fd.append('cliente_id', cid);
        fd.append('tipo_contacto', tipo); //  ENVIAMOS EL TIPO A PHP
        fd.append('mensaje', txt);
        
        await fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/chat_send.php', { method:'POST', body:fd });
    });

    function pintarBurbuja(txt, hora, esMio) {
        const div = document.createElement('div');
        div.className = `msg-row ${esMio ? 'me' : 'them'}`;
        div.innerHTML = `
            <div class="msg-bubble">
                ${txt}
                <div class="msg-time">${hora}</div>
            </div>
        `;
        msgsContainer.appendChild(div);
    }

    function scrollFondo() {
        msgsContainer.scrollTop = msgsContainer.scrollHeight;
    }
});
</script>
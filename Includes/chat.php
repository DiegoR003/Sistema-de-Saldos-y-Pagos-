<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<div id="chatWidgetContainer">

    <div id="chatBox" class="chat-box shadow-lg d-none">
        
        <div class="chat-head d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2" id="chatBackInfo" style="cursor:pointer; overflow:hidden;">
                <i class="bi bi-arrow-left text-dark d-none" id="chatBackBtn"></i>
                <div class="position-relative">
                    <div id="chatAvatar" class="chat-avatar">?</div>
                    <span id="chatStatusDot" class="chat-dot offline"></span>
                </div>
                <div class="d-flex flex-column" style="line-height:1.1;">
                    <span id="chatTitle" class="fw-bold text-dark text-truncate" style="font-size:0.95rem; max-width: 180px;">Mensajes</span>
                    <span id="chatSubtitle" class="small text-muted" style="font-size:0.7rem;">Banana Group</span>
                </div>
            </div>
            <button id="chatCloseBtn" class="btn btn-sm text-dark"><i class="bi bi-x-lg"></i></button>
        </div>

        <div id="chatViewList" class="chat-body d-flex flex-column">
            <div class="p-2 border-bottom bg-light">
                <input type="text" id="chatSearchInput" class="form-control form-control-sm rounded-pill border-0 shadow-sm" placeholder="Buscar chat...">
            </div>
            <div id="chatContactList" class="flex-grow-1 overflow-auto bg-white"></div>
        </div>

        <div id="chatViewConv" class="chat-body d-none flex-column h-100 position-relative">
            <div class="chat-bg"></div>
            
            <div id="chatMsgArea" class="flex-grow-1 p-3 overflow-auto z-1"></div>

            <div id="chatFilePreview" class="d-none p-2 bg-light border-top d-flex justify-content-between align-items-center small">
                <span id="chatFileName" class="text-truncate" style="max-width:200px"></span>
                <button type="button" class="btn-close btn-sm" onclick="chatClearFile()"></button>
            </div>

            <div class="p-2 bg-white border-top z-1">
                <form id="chatForm" class="d-flex align-items-center gap-2 m-0" onsubmit="return false;">
                    <input type="hidden" id="chatTargetId">
                    
                    <label class="btn btn-light btn-sm rounded-circle text-muted shadow-sm d-flex align-items-center justify-content-center" style="width:38px;height:38px; cursor:pointer; flex-shrink:0;">
                        <i class="bi bi-paperclip fs-5"></i>
                        <input type="file" id="chatFileIn" class="d-none">
                    </label>

                    <input type="text" id="chatTextIn" class="form-control form-control-sm rounded-pill bg-light border-0 shadow-sm" placeholder="Escribe..." style="height:38px;" autocomplete="off">
                    
                    <button type="submit" class="btn btn-warning btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:38px;height:38px; background-color:#fdd835; border:none; flex-shrink:0;">
                        <i class="bi bi-send-fill text-dark"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <button id="chatFloatBtn" class="chat-float-btn shadow-lg">
        <i class="bi bi-chat-dots-fill fs-4"></i>
        <span id="chatUnreadBadge" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle d-none"></span>
    </button>

</div>

<style>
    /* === CSS BLINDADO PARA EVITAR ESTIRAMIENTO === */
    
    #chatWidgetContainer {
        position: fixed !important;
        bottom: 25px !important;
        right: 25px !important;
        z-index: 2147483647 !important; /* Encima de todo */
        
        /* Forzamos que el contenedor NO ocupe toda la pantalla */
        width: auto !important; 
        height: auto !important;
        left: auto !important;
        top: auto !important;
        
        /* Organización interna */
        display: flex;
        flex-direction: column;
        align-items: flex-end; /* Alinea hijos a la derecha */
        gap: 15px; /* Espacio entre ventana y botón */
    }

    /* BOTÓN FLOTANTE */
    .chat-float-btn {
        width: 60px !important;
        height: 60px !important;
        border-radius: 50% !important;
        background-color: #fdd835 !important;
        color: #333 !important;
        border: none !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        transition: transform 0.2s ease;
        padding: 0 !important;
    }
    .chat-float-btn:hover { transform: scale(1.1); }

    /* VENTANA DEL CHAT */
    .chat-box {
        /* Dimensiones fijas estrictas */
        width: 350px !important;
        height: 500px !important;
        
        /* Asegurar que no se salga en móviles */
        max-width: 90vw !important;
        max-height: 80vh !important;
        
        background: #fff !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2) !important;
        border: 1px solid rgba(0,0,0,0.1) !important;
    }

    /* CABECERA */
    .chat-head {
        background: #fdd835 !important;
        height: 60px !important;
        min-height: 60px !important;
        padding: 0 15px;
        flex-shrink: 0;
    }

    .chat-avatar {
        width: 38px; height: 38px;
        background: #fff; color: #333;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 0.9rem;
        margin-right: 10px;
    }

    .chat-dot {
        position: absolute; bottom: 0; right: 8px;
        width: 10px; height: 10px;
        border-radius: 50%;
        border: 2px solid #fdd835;
    }
    .chat-dot.online { background-color: #25D366; }
    .chat-dot.offline { background-color: #ccc; }

    /* CUERPO */
    .chat-body { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
    
    .chat-bg {
        position: absolute; inset: 0; 
        background-color: #e5ddd5;
        opacity: 0.1; pointer-events: none; z-index: 0;
    }

    /* ITEMS LISTA */
    .chat-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f5f5f5;
        cursor: pointer;
        display: flex; align-items: center;
        background: #fff;
    }
    .chat-item:hover { background-color: #fffde7; }

    /* MENSAJES */
    .c-msg-row { display: flex; margin-bottom: 6px; padding: 0 10px; position: relative; z-index: 2; }
    .c-msg-row.me { justify-content: flex-end; }
    
    .c-bubble {
        max-width: 80%; padding: 8px 12px;
        border-radius: 12px; font-size: 0.9rem;
        position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        word-wrap: break-word;
    }
    .c-msg-row.me .c-bubble { background: #dcf8c6; border-top-right-radius: 0; }
    .c-msg-row.them .c-bubble { background: #fff; border-top-left-radius: 0; }

    .c-time {
        font-size: 0.65rem; color: #999;
        float: right; margin-left: 8px; margin-top: 4px;
        display: flex; align-items: center; gap: 3px;
    }

    /* MÓVIL EXCLUSIVO */
    @media (max-width: 576px) {
        #chatWidgetContainer {
            bottom: 0 !important; right: 0 !important; left:0 !important; top:0 !important;
            width: 100% !important; height: 100% !important;
            pointer-events: none;
            justify-content: flex-end;
            padding: 20px;
        }
        .chat-box {
            pointer-events: auto;
            width: 100% !important;
            max-width: 100% !important;
            height: 85% !important; /* Casi pantalla completa */
            margin-bottom: 70px;
        }
        .chat-float-btn {
            pointer-events: auto;
            position: absolute; bottom: 20px; right: 20px;
        }
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Sonido notificación
    const soundNotify = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

    // Referencias DOM
    const D = {
        floatBtn: document.getElementById('chatFloatBtn'),
        box: document.getElementById('chatBox'),
        closeBtn: document.getElementById('chatCloseBtn'),
        backInfo: document.getElementById('chatBackInfo'),
        backBtn: document.getElementById('chatBackBtn'),
        
        viewList: document.getElementById('chatViewList'),
        viewConv: document.getElementById('chatViewConv'),
        list: document.getElementById('chatContactList'),
        msgs: document.getElementById('chatMsgArea'),
        
        title: document.getElementById('chatTitle'),
        sub: document.getElementById('chatSubtitle'),
        avatar: document.getElementById('chatAvatar'),
        dot: document.getElementById('chatStatusDot'),
        
        form: document.getElementById('chatForm'),
        txt: document.getElementById('chatTextIn'),
        file: document.getElementById('chatFileIn'),
        tid: document.getElementById('chatTargetId'),
        
        preview: document.getElementById('chatFilePreview'),
        prevName: document.getElementById('chatFileName')
    };

    let activeChat = null;
    let pusherChannel = null;

    // --- 1. ABRIR / CERRAR ---
    D.floatBtn.addEventListener('click', () => {
    D.box.classList.remove('d-none');
    D.floatBtn.classList.add('d-none');
    loadContacts();
});


    D.closeBtn.addEventListener('click', () => {
        D.box.classList.add('d-none');
        D.floatBtn.classList.remove('d-none');
    });
    
    // --- 2. VOLVER A LISTA ---
    D.backInfo.addEventListener('click', () => {
        if(activeChat) {
            if(pusherChannel) pusherChannel.unbind(); // Desconectar eventos
            activeChat = null;
            D.viewConv.classList.add('d-none');
            D.viewList.classList.remove('d-none');
            D.backBtn.classList.add('d-none');
            D.title.textContent = 'Mensajes';
            D.sub.textContent = 'Banana Group';
            D.avatar.textContent = '?';
            D.dot.className = 'chat-dot offline';
            loadContacts();
        }
    });

    // --- 3. CARGAR LISTA ---
    async function loadContacts() {
        D.list.innerHTML = '<div class="text-center p-4 text-muted small">Cargando...</div>';
        try {
            const r = await fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/chat_list.php');
            const data = await r.json();
            
            D.list.innerHTML = '';
            if(!data || !data.length) {
                D.list.innerHTML = '<div class="text-center p-5 text-muted small">No hay conversaciones.</div>';
                return;
            }

            data.forEach(c => {
                const row = document.createElement('div');
                row.className = 'chat-item';
                row.onclick = () => openChat(c.id, c.nombre, c.avatar_html, c.last_seen);
                
                let online = '';
                if(c.last_seen && (new Date() - new Date(c.last_seen))/1000 < 120) {
                    online = '<span style="color:#25D366;font-size:1.2rem;line-height:0;margin-left:4px">•</span>';
                }

                row.innerHTML = `
                    <div class="chat-avatar">${c.avatar_html || c.nombre[0]}</div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark text-truncate" style="font-size:0.9rem">${c.nombre} ${online}</strong>
                            <span class="text-muted" style="font-size:0.7rem">${c.hora||''}</span>
                        </div>
                        <div class="text-muted small text-truncate">${c.ultimo_msg || '...'}</div>
                    </div>
                `;
                D.list.appendChild(row);
            });
        } catch(e) { console.error("Error lista:", e); }
    }

    // --- 4. ABRIR CONVERSACIÓN ---
    window.openChat = async function(id, name, avatar, lastSeen) {
        activeChat = id;
        D.tid.value = id;
        
        D.viewList.classList.add('d-none');
        D.viewConv.classList.remove('d-none');
        D.backBtn.classList.remove('d-none');
        
        D.title.textContent = name;
        D.avatar.innerHTML = avatar || name[0];
        updateHeaderStatus(lastSeen);

        await loadMessages(id);
        setupPusher(id);
    };

    function updateHeaderStatus(lastSeen) {
        if(!lastSeen) { D.sub.textContent = 'Desconectado'; D.dot.className = 'chat-dot offline'; return; }
        const diff = (new Date() - new Date(lastSeen))/1000;
        if(diff < 120) { D.sub.textContent = 'En línea'; D.dot.className = 'chat-dot online'; }
        else { 
            const d = new Date(lastSeen);
            D.sub.textContent = 'Últ. vez ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2,'0');
            D.dot.className = 'chat-dot offline';
        }
    }

    // --- 5. CARGAR HISTORIAL ---
    async function loadMessages(id) {
        D.msgs.innerHTML = '<div class="text-center p-3 small">Cargando...</div>';
        try {
            const r = await fetch(`/Sistema-de-Saldos-y-Pagos-/Public/api/chat_history.php?cliente_id=${id}`);
            const msgs = await r.json();
            
            D.msgs.innerHTML = '';
            msgs.forEach(m => appendMsg(m));
            scrollBottom();
        } catch(e) { console.error(e); }
    }

    function appendMsg(m) {
        // Determinar quién soy (AJUSTAR SEGÚN LÓGICA DE TU BD)
        // Si 'tipo_autor' viene del backend, úsalo. Si no, inferir.
        const isMe = (m.tipo_autor === 'usuario'); 
        
        const div = document.createElement('div');
        div.className = `c-msg-row ${isMe ? 'me' : 'them'}`;
        
        let content = m.mensaje;
        if(m.tipo_archivo === 'image') content = `<img src="${m.adjunto}" style="max-width:100%;border-radius:8px;cursor:pointer" onclick="window.open(this.src)">`;
        else if(m.adjunto) content = `<a href="${m.adjunto}" target="_blank">📎 Archivo</a>`;

        const check = isMe ? '<i class="bi bi-check2-all text-primary" style="font-size:0.8rem"></i>' : '';

        div.innerHTML = `
            <div class="c-bubble">
                ${content}
                <span class="c-time">${m.hora} ${check}</span>
            </div>
        `;
        D.msgs.appendChild(div);
    }

    function scrollBottom() { D.msgs.scrollTop = D.msgs.scrollHeight; }

    // --- 6. ENVIAR (Click y Enter) ---
    D.form.addEventListener('submit', async (e) => {
        e.preventDefault(); // Prevenir recarga
        
        const text = D.txt.value.trim();
        const file = D.file.files[0];
        
        if(!text && !file) return;

        // UI Optimista (solo texto)
        if(text && !file) {
            const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            appendMsg({ mensaje: text, hora: time, tipo_autor: 'usuario', tipo_archivo: 'text' });
            scrollBottom();
        }

        const fd = new FormData();
        fd.append('cliente_id', activeChat);
        fd.append('mensaje', text);
        if(file) fd.append('adjunto', file);

        D.txt.value = '';
        chatClearFile();

        try {
            await fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/chat_send.php', { method:'POST', body:fd });
            if(file) loadMessages(activeChat); 
        } catch(e) { console.error(e); }
    });

    // --- 7. ARCHIVOS ---
    D.file.onchange = () => { 
        if(D.file.files[0]) { 
            D.preview.classList.remove('d-none'); 
            D.prevName.textContent = D.file.files[0].name; 
        } 
    };
    window.chatClearFile = () => { D.file.value=''; D.preview.classList.add('d-none'); };

    // --- 8. PUSHER REAL ---
    function setupPusher(chatId) {
        if(!window.Pusher || !window.PUSHER_CONFIG) return;
        
        const pusher = new Pusher(window.PUSHER_CONFIG.key, {
            cluster: window.PUSHER_CONFIG.cluster,
            forceTLS: true
        });

        // IMPORTANTE: usar el mismo canal que en chat_send.php
        const channelName = 'chat_' + chatId;
        pusherChannel = pusher.subscribe(channelName);

        pusherChannel.bind('nuevo-mensaje', (data) => {
            // Si el mensaje viene marcado como 'usuario', es mío -> lo ignoro
            if (data.tipo_autor === 'usuario') {
                return;
            }

            // Sonido
            soundNotify.play().catch(()=>{});

            // Pintar mensaje usando todo el payload del backend
            appendMsg({
                mensaje: data.mensaje,
                hora:    data.hora,
                tipo_autor: data.tipo_autor || 'cliente',
                tipo_archivo: data.tipo_archivo || 'text',
                adjunto: data.adjunto || null
            });
            scrollBottom();
            
            // Estado
            D.sub.textContent = 'En línea';
            D.dot.className = 'chat-dot online';
        });
    }

    // --- 9. STATUS LOOP ---
    function updateOnlineStatus() { 
        fetch('/Sistema-de-Saldos-y-Pagos-/Public/api/chat_status.php').catch(()=>{}); 
    }
    setInterval(updateOnlineStatus, 30000);
});
</script>

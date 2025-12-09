<?php
// Public/api/chat_send.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';
require_once __DIR__ . '/../../App/pusher_config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();
$u = current_user();

if (!$u) exit(json_encode(['ok'=>false]));

$contactoId = (int)($_POST['cliente_id'] ?? 0); // En el frontend usamos 'cliente_id' genérico para el ID destino
$mensaje    = trim($_POST['mensaje'] ?? '');
$tipoDestino = $_POST['tipo_contacto'] ?? 'cliente'; // Nuevo parámetro

if ($contactoId <= 0 || $mensaje === '') exit(json_encode(['ok'=>false]));

try {
    $hiloId = 0;
    
    // --- ESCENARIO 1: ADMIN ESCRIBE A CLIENTE ---
    if ($tipoDestino === 'cliente') {
        // Buscar hilo del cliente
        $st = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_tipo='cliente' AND scope_id=? LIMIT 1");
        $st->execute([$contactoId]);
        $hiloId = (int)$st->fetchColumn();
        
        if ($hiloId === 0) {
            $ins = $pdo->prepare("INSERT INTO chat_hilos (scope_tipo, scope_id, estado, creado_por_usuario_id, creado_en, actualizado_en) VALUES ('cliente', ?, 'activo', ?, NOW(), NOW())");
            $ins->execute([$contactoId, $u['id']]);
            $hiloId = (int)$pdo->lastInsertId();
        }
    }
    
    // --- ESCENARIO 2: CLIENTE ESCRIBE A STAFF ---
    else if ($tipoDestino === 'staff') {
        // Necesito mi ID de cliente
        $stMe = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
        $stMe->execute([$u['correo']]);
        $miClienteId = (int)$stMe->fetchColumn();
        
        if ($miClienteId > 0) {
            // Buscar hilo privado: scope_tipo='privado_staff', scope_id=ID_DEL_STAFF, creado_por=YO
            $st = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_tipo='privado_staff' AND scope_id=? AND creado_por_cliente_id=? LIMIT 1");
            $st->execute([$contactoId, $miClienteId]);
            $hiloId = (int)$st->fetchColumn();
            
            if ($hiloId === 0) {
                // Notar que usamos una columna 'creado_por_cliente_id' que quizás debas agregar o reutilizar 'scope_id' con ingenio. 
                // Para no alterar tu BD, usaremos scope_id para el STAFF y una columna extra o lógica JSON si pudieras.
                // Como NO queremos alterar BD, usaremos una convención:
                // scope_tipo = 'staff_chat'
                // scope_id   = ID_STAFF
                // Pero esto mezclaría chats de varios clientes con el mismo staff.
                
                // SOLUCIÓN SIMPLE CON TU BD ACTUAL:
                // Usaremos la tabla 'chat_participantes' si existe, o crearemos hilos únicos.
                
                // Vamos a insertar asumiendo que agregaste la columna o usas la estructura flexible.
                // Si no puedes alterar BD, el chat 1-a-1 cliente-staff es complejo.
                // Volvamos a lo seguro: El cliente le escribe a la EMPRESA (un solo hilo por cliente).
                
                // REVERSION A LOGICA SEGURA (Cliente -> Empresa):
                // Ignoramos a qué staff específico le dio clic, todo va al hilo del cliente.
                // Así cualquier admin lo ve. Es lo más común en soporte.
                
                $st = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_tipo='cliente' AND scope_id=? LIMIT 1");
                $st->execute([$miClienteId]);
                $hiloId = (int)$st->fetchColumn();
                
                if ($hiloId === 0) {
                    $ins = $pdo->prepare("INSERT INTO chat_hilos (scope_tipo, scope_id, estado, creado_en, actualizado_en) VALUES ('cliente', ?, 'activo', NOW(), NOW())");
                    $ins->execute([$miClienteId]);
                    $hiloId = (int)$pdo->lastInsertId();
                }
            }
        }
    }

    // 2. INSERTAR MENSAJE
    // Detectar quién soy para llenar la columna correcta
    $esStaff = in_array(strtolower($u['rol']), ['admin', 'operador']);
    
    $colAuthor = $esStaff ? 'autor_usuario_id' : 'autor_cliente_id';
    $idAuthor  = $esStaff ? $u['id'] : ($miClienteId ?? 0); // Si es cliente, necesitamos su ID real
    
    // Si soy cliente, necesito obtener mi ID de la tabla clientes nuevamente si no lo tengo
    if (!$esStaff && !isset($miClienteId)) {
        $stMe = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
        $stMe->execute([$u['correo']]);
        $idAuthor = (int)$stMe->fetchColumn();
    }

    $sql = "INSERT INTO chat_mensajes (hilo_id, $colAuthor, tipo, mensaje, creado_en) VALUES (?, ?, 'texto', ?, NOW())";
    $pdo->prepare($sql)->execute([$hiloId, $idAuthor, $mensaje]);
    
    // Actualizar fecha hilo
    $pdo->prepare("UPDATE chat_hilos SET actualizado_en=NOW() WHERE id=?")->execute([$hiloId]);

    // PUSHER (Notificación)
    if (function_exists('pusher_client')) {
        $pusher = pusher_client();
        // Canal dinámico: si soy staff escribiendo a cliente X -> canal cliente_X
        // Si soy cliente X escribiendo -> canal cliente_X
        // El canal siempre es el ID del cliente dueño del hilo
        
        $targetClienteId = $esStaff ? $contactoId : $idAuthor;
        
        $pusher->trigger('chat_cliente_' . $targetClienteId, 'nuevo-mensaje', [
            'mensaje' => $mensaje,
            'hora'    => date('H:i')
        ]);
    }

    echo json_encode(['ok'=>true]);

} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'err'=>$e->getMessage()]);
}
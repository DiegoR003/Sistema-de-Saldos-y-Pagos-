<?php
// Public/api/chat_list.php
declare(strict_types=1);

// Evitamos que errores de PHP rompan el JSON
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$u = function_exists('current_user') ? current_user() : null;

if (!$u || empty($u['id'])) { 
    echo json_encode([]); 
    exit; 
}

$userId = (int)$u['id'];
$esStaff = false;

// 1. VERIFICACIÓN DE ROL ROBUSTA (Consultando la BD)
// No confiamos solo en la sesión, preguntamos a la base de datos quién es este usuario.
try {
    $stRol = $pdo->prepare("
        SELECT r.nombre 
        FROM roles r 
        JOIN usuario_rol ur ON ur.rol_id = r.id 
        WHERE ur.usuario_id = ? 
        LIMIT 1
    ");
    $stRol->execute([$userId]);
    $rolDb = strtolower($stRol->fetchColumn() ?: '');
    
    // Si tiene rol 'admin' u 'operador', es Staff
    if (in_array($rolDb, ['admin', 'operador'])) {
        $esStaff = true;
    }
} catch (Exception $e) {
    // Si falla, asumimos cliente por seguridad (o lista vacía)
}

$resultado = [];

try {
    // ====================================================================
    // CASO A: SOY STAFF (ADMIN/OPERADOR) -> VEO LISTA DE CLIENTES
    // ====================================================================
    if ($esStaff) {
        
        // Obtenemos TODOS los clientes.
        // LEFT JOIN para saber si ya hay chat, pero mostramos al cliente AUNQUE NO TENGA CHAT.
        $sql = "
            SELECT 
                c.id as cliente_id,
                c.empresa,
                c.correo,
                h.id as hilo_id,
                h.actualizado_en
            FROM clientes c
            LEFT JOIN chat_hilos h ON (h.scope_tipo = 'cliente' AND h.scope_id = c.id)
            ORDER BY 
                CASE WHEN h.id IS NOT NULL THEN 1 ELSE 0 END DESC, -- Prioridad chats activos
                h.actualizado_en DESC,                             -- Recientes primero
                c.empresa ASC                                      -- Alfabético
        ";
        
        $st = $pdo->query($sql);
        $clientes = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($clientes) {
            foreach ($clientes as $c) {
                // Datos base
                $hiloId    = $c['hilo_id'] ? (int)$c['hilo_id'] : 0;
                $ultimoMsg = 'Iniciar conversación';
                $hora      = '';
                $style     = 'text-muted small fst-italic';
                
                // Si hay hilo, buscamos el último mensaje
                if ($hiloId > 0) {
                    $stMsg = $pdo->prepare("SELECT mensaje, creado_en FROM chat_mensajes WHERE hilo_id = ? ORDER BY id DESC LIMIT 1");
                    $stMsg->execute([$hiloId]);
                    $m = $stMsg->fetch(PDO::FETCH_ASSOC);
                    
                    if ($m) {
                        $ultimoMsg = mb_strimwidth((string)$m['mensaje'], 0, 30, '...');
                        $ts = strtotime($m['creado_en']);
                        $hora = (date('Ymd') == date('Ymd', $ts)) ? date('H:i', $ts) : date('d/m', $ts);
                        $style = 'text-dark small';
                    }
                }

                // Intentamos buscar Foto de Perfil (si el cliente tiene usuario asociado por correo)
                $avatarHtml = '';
                if (!empty($c['correo'])) {
                    $stFoto = $pdo->prepare("SELECT foto_url FROM usuarios WHERE correo = ? LIMIT 1");
                    $stFoto->execute([$c['correo']]);
                    $fotoUrl = $stFoto->fetchColumn();
                    
                    if ($fotoUrl) {
                        $avatarHtml = '<img src="'.$fotoUrl.'" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;">';
                    }
                }

                // Si no hay foto, ponemos iniciales
                if (empty($avatarHtml)) {
                    $inicial = strtoupper(mb_substr($c['empresa'] ?: '?', 0, 1));
                    // Colores aleatorios para que se vea bonito
                    $colors = ['#ffc107', '#17a2b8', '#6c757d', '#28a745', '#007bff'];
                    $bg = $colors[$c['cliente_id'] % count($colors)];
                    $avatarHtml = '<div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width:100%; height:100%; background:'.$bg.'; font-size:1.1rem;">'.$inicial.'</div>';
                }

                $resultado[] = [
                    'id'           => $c['cliente_id'],
                    'nombre'       => $c['empresa'] ?: 'Sin Nombre',
                    'avatar_html'  => $avatarHtml,
                    'ultimo_msg'   => $ultimoMsg,
                    'hora'         => $hora,
                    'style'        => $style,
                    'tipo_contacto'=> 'cliente'
                ];
            }
        }
    } 

    // ====================================================================
    // CASO B: SOY CLIENTE -> VEO LISTA DE STAFF (ADMINS Y OPERADORES)
    // ====================================================================
    else {
        // 1. Obtener mi ID de Cliente (Soy el usuario logueado)
        $stMe = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
        $stMe->execute([$u['correo']]);
        $miClienteId = (int)$stMe->fetchColumn();

        if ($miClienteId > 0) {
            // 2. Obtener lista de Staff (Usuarios Admin/Operador)
            $sqlStaff = "
                SELECT u.id, u.nombre, u.foto_url, r.nombre as rol
                FROM usuarios u
                JOIN usuario_rol ur ON ur.usuario_id = u.id
                JOIN roles r ON r.id = ur.rol_id
                WHERE r.nombre IN ('admin', 'operador') AND u.activo = 1
                ORDER BY u.nombre ASC
            ";
            $stStaff = $pdo->query($sqlStaff);
            $staffList = $stStaff->fetchAll(PDO::FETCH_ASSOC);

            // Para el cliente, todos los mensajes van al mismo hilo principal, 
            // pero mostramos la lista de agentes para que sienta atención personalizada.
            // Buscamos el hilo general de este cliente para mostrar el último mensaje.
            
            $hiloId = 0;
            $ultimoMsgGeneral = '¡Hola! ¿En qué podemos ayudarte?';
            $horaGeneral = '';

            $stHilo = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_tipo='cliente' AND scope_id=? LIMIT 1");
            $stHilo->execute([$miClienteId]);
            $hiloId = (int)$stHilo->fetchColumn();

            if ($hiloId > 0) {
                $stMsg = $pdo->prepare("SELECT mensaje, creado_en FROM chat_mensajes WHERE hilo_id=? ORDER BY id DESC LIMIT 1");
                $stMsg->execute([$hiloId]);
                $m = $stMsg->fetch(PDO::FETCH_ASSOC);
                if ($m) {
                    $ultimoMsgGeneral = mb_strimwidth((string)$m['mensaje'], 0, 25, '...');
                    $horaGeneral = date('H:i', strtotime($m['creado_en']));
                }
            }

            foreach ($staffList as $s) {
                // Avatar del Staff
                if (!empty($s['foto_url'])) {
                    $avatarHtml = '<img src="'.$s['foto_url'].'" class="rounded-circle" style="width:100%; height:100%; object-fit:cover;">';
                } else {
                    $ini = strtoupper(mb_substr($s['nombre'], 0, 1));
                    $avatarHtml = '<div class="rounded-circle bg-dark text-warning d-flex align-items-center justify-content-center fw-bold" style="width:100%; height:100%;">'.$ini.'</div>';
                }

                $resultado[] = [
                    'id'           => $miClienteId, // IMPORTANTE: El cliente siempre abre SU propio hilo
                    'nombre'       => $s['nombre'],
                    'avatar_html'  => $avatarHtml,
                    'ultimo_msg'   => $ultimoMsgGeneral,
                    'hora'         => $horaGeneral,
                    'style'        => 'text-dark small',
                    'tipo_contacto'=> 'staff',
                    'extra_html'   => '<span class="badge bg-warning text-dark border ms-1" style="font-size:0.6em">'.ucfirst($s['rol']).'</span>'
                ];
            }
        }
    }

} catch (Exception $e) {
    // Error visible en el chat para depurar
    $resultado[] = [
        'id'=>0, 'nombre'=>'Error', 
        'ultimo_msg'=>$e->getMessage(), 'avatar_html'=>'!', 'style'=>'text-danger'
    ];
}

header('Content-Type: application/json');
echo json_encode($resultado);
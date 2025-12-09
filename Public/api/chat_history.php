<?php
// Public/api/chat_history.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();
$u = current_user();

if (!$u) { echo json_encode([]); exit; }

$rol = strtolower($u['rol'] ?? 'cliente');
$clienteIdSolicitado = (int)($_GET['cliente_id'] ?? 0);

// SEGURIDAD:
// Si es Staff, puede ver el ID que quiera.
// Si es Cliente, FORZAMOS que solo vea SU propio ID.
if (!in_array($rol, ['admin', 'operador'])) {
    $stMe = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
    $stMe->execute([$u['correo']]);
    $miId = (int)$stMe->fetchColumn();
    
    // Sobrescribimos la solicitud
    $clienteIdSolicitado = $miId;
}

if ($clienteIdSolicitado <= 0) { echo json_encode([]); exit; }

// 1. Buscar hilo
$stHilo = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_tipo = 'cliente' AND scope_id = ? LIMIT 1");
$stHilo->execute([$clienteIdSolicitado]);
$hiloId = (int)$stHilo->fetchColumn();

if ($hiloId <= 0) { echo json_encode([]); exit; }

// 2. Traer mensajes + Fotos de los autores
$sql = "
    SELECT 
        m.*,
        COALESCE(u.nombre, c.empresa, 'Sistema') as nombre_autor,
        u.foto_url as foto_usuario, -- Foto del staff
        CASE 
            WHEN m.autor_usuario_id IS NOT NULL THEN 'usuario'
            ELSE 'cliente'
        END as tipo_autor
    FROM chat_mensajes m
    LEFT JOIN usuarios u ON u.id = m.autor_usuario_id
    LEFT JOIN clientes c ON c.id = m.autor_cliente_id
    WHERE m.hilo_id = ?
    ORDER BY m.creado_en ASC
";

$st = $pdo->prepare($sql);
$st->execute([$hiloId]);
$msgs = $st->fetchAll(PDO::FETCH_ASSOC);

foreach($msgs as &$m) {
    $m['hora'] = date('H:i', strtotime($m['creado_en']));
}

header('Content-Type: application/json');
echo json_encode($msgs);
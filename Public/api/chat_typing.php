<?php
// Public/api/chat_typing.php
require_once __DIR__ . '/../../App/auth.php';
require_once __DIR__ . '/../../App/pusher_config.php';

// Validar sesión
$u = current_user();
if (!$u) exit;

$clienteId = (int)$_POST['cliente_id'];
$esStaff   = (in_array($u['rol'] ?? '', ['admin','operador']));

// El canal siempre es el ID del cliente
$canal = 'chat_' . ($esStaff ? $clienteId : $u['id']);

if (function_exists('pusher_client')) {
    $pusher = pusher_client();
    // Evento: 'escribiendo'
    $pusher->trigger($canal, 'escribiendo', [
        'quien' => $esStaff ? 'staff' : 'cliente'
    ]);
}
?>
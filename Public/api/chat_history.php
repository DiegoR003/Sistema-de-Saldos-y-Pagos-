<?php
// Public/api/chat_history.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$u   = function_exists('current_user') ? current_user() : null;

$clienteId = (int)($_GET['cliente_id'] ?? 0);
if ($clienteId <= 0) {
    echo json_encode([]);
    exit;
}

// 1) Buscar hilo de este cliente (ignoramos scope_tipo)
$st = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_id = ? LIMIT 1");
$st->execute([$clienteId]);
$hiloId = (int)$st->fetchColumn();

if (!$hiloId) {
    echo json_encode([]);
    exit;
}

// 2) Traer mensajes ordenados por fecha
$sql = "SELECT 
            id,
            mensaje,
            adjunto,
            tipo_archivo,
            autor_usuario_id,
            autor_cliente_id,
            creado_en
        FROM chat_mensajes
        WHERE hilo_id = ?
        ORDER BY creado_en ASC";

$st = $pdo->prepare($sql);
$st->execute([$hiloId]);

$res = [];
while ($m = $st->fetch(PDO::FETCH_ASSOC)) {
    $tipo = !empty($m['autor_usuario_id']) ? 'usuario' : 'cliente';

    $res[] = [
        'id'           => (int)$m['id'],
        'mensaje'      => $m['mensaje'],
        'adjunto'      => $m['adjunto'],
        'tipo_archivo' => $m['tipo_archivo'],
        'hora'         => date('H:i', strtotime($m['creado_en'])),
        'tipo_autor'   => $tipo,
    ];
}

echo json_encode($res);

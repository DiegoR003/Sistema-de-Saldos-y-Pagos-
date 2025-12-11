<?php
// Public/api/chat_list.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$u   = function_exists('current_user') ? current_user() : null;

$resultado = [];

try {
    // 1) Traer todos los clientes
    $sqlCli = "SELECT id, empresa, correo, last_seen
               FROM clientes
               ORDER BY empresa ASC";
    $stCli = $pdo->query($sqlCli);

    // Preparar statement para último mensaje por cliente
    $sqlLast = "SELECT m.mensaje, m.creado_en
                FROM chat_mensajes m
                INNER JOIN chat_hilos h ON h.id = m.hilo_id
                WHERE h.scope_id = ?
                ORDER BY m.creado_en DESC
                LIMIT 1";
    $stLast = $pdo->prepare($sqlLast);

    while ($c = $stCli->fetch(PDO::FETCH_ASSOC)) {
        $clienteId = (int)$c['id'];

        $stLast->execute([$clienteId]);
        $last = $stLast->fetch(PDO::FETCH_ASSOC);

        $ultimoMsg = $last['mensaje'] ?? 'Iniciar conversación';
        $hora      = isset($last['creado_en']) ? date('H:i', strtotime($last['creado_en'])) : '';

        $avatar = strtoupper(mb_substr($c['empresa'], 0, 1, 'UTF-8'));

        $resultado[] = [
            'id'         => $clienteId,
            'nombre'     => $c['empresa'],
            'ultimo_msg' => $ultimoMsg,
            'hora'       => $hora,
            'avatar_html'=> $avatar,
            'last_seen'  => $c['last_seen'],
        ];
    }

} catch (Throwable $e) {
    $resultado[] = [
        'id'         => 0,
        'nombre'     => 'Error',
        'ultimo_msg' => $e->getMessage(),
        'avatar_html'=> '!',
        'last_seen'  => null,
    ];
}

echo json_encode($resultado);

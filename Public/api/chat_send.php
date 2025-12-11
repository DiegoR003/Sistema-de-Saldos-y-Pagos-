<?php
// Public/api/chat_send.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/auth.php';
require_once __DIR__ . '/../../App/pusher_config.php'; // Debe definir pusher_client()

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$u   = function_exists('current_user') ? current_user() : null;

if (!$u) {
    echo json_encode(['ok' => false, 'msg' => 'Sesión requerida']);
    exit;
}

// Datos del POST
$clienteId = (int)($_POST['cliente_id'] ?? 0);
$mensaje   = trim((string)($_POST['mensaje'] ?? ''));

// Opcional: archivo adjunto (ajústalo a tu lógica si quieres usarlo)
$adjunto     = null;
$tipoArchivo = 'text'; // por defecto

if ($clienteId <= 0 || $mensaje === '') {
    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Buscar (o crear) hilo asociado a este cliente
    //    NOTA: ignoramos scope_tipo, usamos sólo scope_id = cliente_id
    $st = $pdo->prepare("SELECT id FROM chat_hilos WHERE scope_id = ? LIMIT 1");
    $st->execute([$clienteId]);
    $hiloId = (int)$st->fetchColumn();

    if (!$hiloId) {
        // Usamos 'cotizacion' como valor válido del ENUM sólo para no romper el esquema.
        $st = $pdo->prepare("
            INSERT INTO chat_hilos (scope_tipo, scope_id, estado, creado_por_usuario_id, creado_en)
            VALUES ('cotizacion', ?, 'abierto', ?, NOW())
        ");
        $st->execute([$clienteId, $u['id']]);
        $hiloId = (int)$pdo->lastInsertId();
    }

    // 2) Insertar mensaje en la BD
    $tipo = $adjunto ? 'archivo' : 'texto';

    $sqlIns = "INSERT INTO chat_mensajes
        (hilo_id, autor_usuario_id, autor_cliente_id, tipo, mensaje, adjunto, tipo_archivo, creado_en)
        VALUES (:hilo_id, :autor_usuario_id, NULL, :tipo, :mensaje, :adjunto, :tipo_archivo, NOW())";

    $stIns = $pdo->prepare($sqlIns);
    $stIns->execute([
        ':hilo_id'          => $hiloId,
        ':autor_usuario_id' => $u['id'],
        ':tipo'             => $tipo,
        ':mensaje'          => $mensaje,
        ':adjunto'          => $adjunto,
        ':tipo_archivo'     => $tipoArchivo,
    ]);

    $msgId = (int)$pdo->lastInsertId();
    $pdo->commit();

    // 3) Payload para frontend / Pusher
    $payload = [
        'id'           => $msgId,
        'mensaje'      => $mensaje,
        'adjunto'      => $adjunto,
        'tipo_archivo' => $tipoArchivo,
        'hora'         => date('H:i'),
        'tipo_autor'   => 'usuario',
    ];

    // 4) Disparar evento Pusher
    if (function_exists('pusher_client')) {
        try {
            $pusher = pusher_client();
            $canal  = 'chat_cliente_' . $clienteId; // <- debe coincidir con chat.php
            $pusher->trigger($canal, 'nuevo-mensaje', $payload);
        } catch (Throwable $e) {
            // Si Pusher falla, al menos el mensaje queda en BD
        }
    }

    echo json_encode(['ok' => true, 'msg' => $payload]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'ok'  => false,
        'msg' => 'Error al enviar mensaje',
        'err' => $e->getMessage(),
    ]);
}

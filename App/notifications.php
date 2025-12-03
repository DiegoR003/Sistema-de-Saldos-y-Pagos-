<?php
// App/notificacion.php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/pusher_config.php'; // aquí incluyes tu config de Pusher

use Pusher\Pusher;

/**
 * Crea un registro en la tabla notificaciones.
 */
function crear_notificacion(PDO $pdo, array $data): int {
    $sql = "
        INSERT INTO notificaciones
        (tipo, canal, titulo, cuerpo, usuario_id, cliente_id, correo_destino,
         ref_tipo, ref_id, estado, programada_en, creada_en)
        VALUES
        (:tipo, :canal, :titulo, :cuerpo, :usuario_id, :cliente_id, :correo_destino,
         :ref_tipo, :ref_id, :estado, :programada_en, NOW())
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':tipo'          => $data['tipo']          ?? 'sistema',   // sistema, cliente, etc.
        ':canal'         => $data['canal']         ?? 'interno',   // interno, email, ambos, etc.
        ':titulo'        => $data['titulo']        ?? '',
        ':cuerpo'        => $data['cuerpo']        ?? '',
        ':usuario_id'    => $data['usuario_id']    ?? null,
        ':cliente_id'    => $data['cliente_id']    ?? null,
        ':correo_destino'=> $data['correo_destino']?? null,
        ':ref_tipo'      => $data['ref_tipo']      ?? null,
        ':ref_id'        => $data['ref_id']        ?? null,
        ':estado'        => $data['estado']        ?? 'nueva',
        ':programada_en' => $data['programada_en'] ?? null,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Dispara la notificación en tiempo real usando Pusher.
 * $channel suele ser algo como: 'private-global' o 'private-user-5'
 */
function pusher_notificar(array $payload, string $channel = 'private-global', string $event = 'nueva-notificacion'): void {
    global $pusher; // creado en pusher_config.php
    if (!$pusher instanceof Pusher) return;

    try {
        $pusher->trigger($channel, $event, $payload);
    } catch (Throwable $e) {
        // En producción puedes loguear el error en vez de ignorarlo
    }
}

/**
 * Notifica a todos los usuarios que tengan los roles indicados.
 *  $roles = ['admin','operador']
 */
function notificar_roles(PDO $pdo, array $roles, string $titulo, string $cuerpo, string $refTipo, int $refId): void {
    if (!$roles) return;

    $placeholders = implode(',', array_fill(0, count($roles), '?'));

    $sql = "
        SELECT u.id, u.correo, r.nombre AS rol
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id = u.id
        JOIN roles r       ON r.id = ur.rol_id
        WHERE u.activo = 1
          AND r.nombre IN ($placeholders)
    ";
    $st = $pdo->prepare($sql);
    $st->execute($roles);
    $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($usuarios as $u) {
        $notifId = crear_notificacion($pdo, [
            'tipo'        => 'sistema',
            'canal'       => 'interno',
            'titulo'      => $titulo,
            'cuerpo'      => $cuerpo,
            'usuario_id'  => (int)$u['id'],
            'cliente_id'  => null,
            'correo_destino' => $u['correo'],
            'ref_tipo'    => $refTipo,
            'ref_id'      => $refId,
            'estado'      => 'nueva',
        ]);

        pusher_notificar([
            'id'       => $notifId,
            'titulo'   => $titulo,
            'cuerpo'   => $cuerpo,
            'ref_tipo' => $refTipo,
            'ref_id'   => $refId,
        ], 'private-user-'.$u['id']);
    }

    // Además, un broadcast global (para quien esté suscrito a la campana general)
    pusher_notificar([
        'titulo'   => $titulo,
        'cuerpo'   => $cuerpo,
        'ref_tipo' => $refTipo,
        'ref_id'   => $refId,
    ], 'private-global');
}

/**
 * Notifica a un cliente concreto (por ahora solo guarda la notificación;
 * luego puedes enganchar aquí también el envío de correo).
 */
function notificar_cliente(PDO $pdo, int $clienteId, string $correo, string $titulo, string $cuerpo, string $refTipo, int $refId): void {
    $notifId = crear_notificacion($pdo, [
        'tipo'          => 'cliente',
        'canal'         => 'interno',   // o 'interno+email' si quieres diferenciar
        'titulo'        => $titulo,
        'cuerpo'        => $cuerpo,
        'usuario_id'    => null,
        'cliente_id'    => $clienteId,
        'correo_destino'=> $correo,
        'ref_tipo'      => $refTipo,
        'ref_id'        => $refId,
        'estado'        => 'nueva',
    ]);

    // Si en el futuro el cliente también se conecta por websockets:
    pusher_notificar([
        'id'       => $notifId,
        'titulo'   => $titulo,
        'cuerpo'   => $cuerpo,
        'ref_tipo' => $refTipo,
        'ref_id'   => $refId,
    ], 'private-cliente-'.$clienteId);
}



/**
 * Inserta una notificación en la tabla notificaciones.
 */
function enviar_notificacion(PDO $pdo, int $usuarioId, string $tipo, string $texto, ?array $data = null): void
{
    $st = $pdo->prepare("
        INSERT INTO notificaciones (usuario_id, tipo, texto, datos_json)
        VALUES (:uid, :tipo, :texto, :json)
    ");

    $st->execute([
        ':uid'  => $usuarioId,
        ':tipo' => $tipo,
        ':texto'=> $texto,
        ':json' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
    ]);

    // 👉 si ya tienes Pusher integrado, aquí podrías disparar el evento en tiempo real
    // trigger_pusher_notificacion($usuarioId, $tipo, $texto, $data);
}

/**
 * Notificación cuando llega una NUEVA cotización (estado pendiente).
 * - Notifica a todos los usuarios ADMIN y OPERADOR.
 * - Si existe un usuario con rol 'cliente' y mismo correo, también se le notifica.
 */
function generar_notificacion_cotizacion_recibida(
    PDO $pdo,
    int $cotizacionId,
    string $empresa,
    string $correo
): void {
    // 1) Admins y operadores
    $st = $pdo->query("
        SELECT id, rol
        FROM usuarios
        WHERE activo = 1
          AND rol IN ('admin', 'operador')
    ");
    $usuarios = $st->fetchAll(PDO::FETCH_ASSOC);

    $textoAdmin = "Nueva cotización #{$cotizacionId} de {$empresa} ({$correo})";

    foreach ($usuarios as $u) {
        enviar_notificacion(
            $pdo,
            (int)$u['id'],
            'cotizacion_nueva',
            $textoAdmin,
            [
                'cotizacion_id' => $cotizacionId,
                'empresa'       => $empresa,
                'correo'        => $correo,
            ]
        );
    }

    // 2) Cliente (si existe usuario con ese correo)
    $st2 = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE correo = ?
          AND rol = 'cliente'
          AND activo = 1
        LIMIT 1
    ");
    $st2->execute([$correo]);
    $clienteId = (int)$st2->fetchColumn();

    if ($clienteId > 0) {
        $textoCliente = "Hemos recibido tu cotización #{$cotizacionId}. En breve la revisaremos.";
        enviar_notificacion(
            $pdo,
            $clienteId,
            'cotizacion_nueva',
            $textoCliente,
            [
                'cotizacion_id' => $cotizacionId,
            ]
        );
    }
}

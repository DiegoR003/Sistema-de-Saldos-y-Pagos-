<?php
// App/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/pusher_config.php';

/**
 * Función principal: Inserta una notificación en la BD y dispara Pusher en tiempo real.
 * * @param PDO $pdo Conexión a base de datos
 * @param array $data Datos de la notificación
 * @param bool $dispararPusher Si true, intenta enviar el evento a Pusher
 * @return int ID de la notificación insertada
 */
function enviar_notificacion(PDO $pdo, array $data, bool $dispararPusher = true): int
{
    // Valores por defecto
    $defaults = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => '',
        'cuerpo'         => '',
        'usuario_id'     => null, // NULL = Global (o para todos los admins si se maneja así)
        'cliente_id'     => null,
        'correo_destino' => null,
        'ref_tipo'       => null,
        'ref_id'         => null,
        'estado'         => 'pendiente',
        'programada_en'  => null,
    ];

    $data = array_merge($defaults, $data);

    // 1. Insertar en Base de Datos
    $sql = "
        INSERT INTO notificaciones
          (tipo, canal, titulo, cuerpo,
           usuario_id, cliente_id, correo_destino,
           ref_tipo, ref_id, estado,
           programada_en, creado_en)
        VALUES
          (:tipo, :canal, :titulo, :cuerpo,
           :usuario_id, :cliente_id, :correo_destino,
           :ref_tipo, :ref_id, :estado,
           :programada_en, NOW())
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':tipo'           => $data['tipo'],
        ':canal'          => $data['canal'],
        ':titulo'         => $data['titulo'],
        ':cuerpo'         => $data['cuerpo'],
        ':usuario_id'     => $data['usuario_id'],
        ':cliente_id'     => $data['cliente_id'],
        ':correo_destino' => $data['correo_destino'],
        ':ref_tipo'       => $data['ref_tipo'],
        ':ref_id'         => $data['ref_id'],
        ':estado'         => $data['estado'],
        ':programada_en'  => $data['programada_en'],
    ]);

    $idNotif = (int)$pdo->lastInsertId();

    // 2. Disparar evento a Pusher (Tiempo Real)
    // Verificamos que exista la función disparadora para evitar errores
    if ($dispararPusher && function_exists('pusher_trigger_notificacion')) {
        pusher_trigger_notificacion([
            'id'         => $idNotif,
            'titulo'     => $data['titulo'],
            'cuerpo'     => $data['cuerpo'],
            'ref_tipo'   => $data['ref_tipo'],
            'ref_id'     => $data['ref_id'],
            'usuario_id' => $data['usuario_id'], // Importante: Saber a quién enviar
        ]);
    }

    return $idNotif;
}

/**
 * Función interna: Conecta con la API de Pusher y envía el evento.
 */
function pusher_trigger_notificacion(array $notifData): void
{
    try {
        // Obtenemos la instancia de Pusher (definida en pusher_config.php)
        if (!function_exists('pusher_client')) {
            return; 
        }
        $pusher = pusher_client();

        // 1. Definir el canal
        // Si tiene usuario_id, enviamos a su canal privado. Si es NULL, al global.
        $canal = 'notificaciones_global';
        if (!empty($notifData['usuario_id'])) {
            $canal = 'notificaciones_user_' . $notifData['usuario_id'];
        }

        // 2. Definir el evento
        $evento = 'nueva-notificacion';

        // 3. Disparar
        $pusher->trigger($canal, $evento, $notifData);

    } catch (Exception $e) {
        // Si falla Pusher (internet, credenciales), no detenemos el sistema, solo registramos el error en logs
        error_log('Error Pusher: ' . $e->getMessage());
    }
}

/**
 * Helper: Notificación de cotización aprobada.
 */
function notificar_cotizacion_aprobada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => 'Cotización aprobada',
        'cuerpo'         => 'La cotización ' . ($cotizacion['folio'] ?? '---') .
                            ' del cliente ' . ($cotizacion['cliente_nombre'] ?? 'Desconocido') .
                            ' ha sido aprobada.',
        'usuario_id'     => $usuarioIdActual, // Notificar al usuario que hizo la acción (feedback) o NULL
        'cliente_id'     => $cotizacion['cliente_id'] ?? null,
        'correo_destino' => $cotizacion['correo'] ?? null,
        'ref_tipo'       => 'cotizacion',
        'ref_id'         => $cotizacion['id'],
        'estado'         => 'pendiente',
    ];

    return enviar_notificacion($pdo, $data, true);
}

/**
 * Helper: Notificación de cotización rechazada.
 */
function notificar_cotizacion_rechazada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => 'Cotización rechazada',
        'cuerpo'         => 'La cotización ' . ($cotizacion['folio'] ?? '---') .
                            ' del cliente ' . ($cotizacion['cliente_nombre'] ?? 'Desconocido') .
                            ' ha sido rechazada.',
        'usuario_id'     => $usuarioIdActual,
        'cliente_id'     => $cotizacion['cliente_id'] ?? null,
        'correo_destino' => $cotizacion['correo'] ?? null,
        'ref_tipo'       => 'cotizacion',
        'ref_id'         => $cotizacion['id'],
        'estado'         => 'pendiente',
    ];

    return enviar_notificacion($pdo, $data, true);
}

/**
 * Helper: Notificación de NUEVA cotización recibida.
 * Se envía a todos los usuarios con rol 'admin' u 'operador'.
 */
function notificar_nueva_cotizacion(PDO $pdo, array $cotizacion): int
{
    // 1. Datos del mensaje
    $cliente = $cotizacion['empresa'] ?? 'Cliente web';
    $total   = number_format((float)($cotizacion['total'] ?? 0), 2);
    
    $titulo  = 'Nueva cotización recibida';
    $cuerpo  = "Has recibido una nueva cotización de {$cliente} por \${$total}.";

    // 2. Obtener IDs de usuarios que sean 'admin' u 'operador'
    // Asegúrate que tu tabla de roles tenga los nombres 'admin' y 'operador'
    $sql = "
        SELECT DISTINCT u.id
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id = u.id
        JOIN roles r        ON r.id = ur.rol_id
        WHERE r.nombre IN ('admin', 'operador')
          AND u.activo = 1
    ";

    $st = $pdo->query($sql);
    $destinatarios = $st->fetchAll(PDO::FETCH_COLUMN);

    $enviados = 0;

    // 3. Enviar notificación individual a cada destinatario encontrado
    foreach ($destinatarios as $uid) {
        $data = [
            'tipo'           => 'sistema',
            'canal'          => 'interna',
            'titulo'         => $titulo,
            'cuerpo'         => $cuerpo,
            'usuario_id'     => $uid, // ID específico del admin/operador
            'cliente_id'     => null,
            'correo_destino' => null,
            'ref_tipo'       => 'cotizacion',
            'ref_id'         => $cotizacion['id'],
            'estado'         => 'pendiente',
        ];

        // Enviamos a BD y disparamos Pusher para este usuario
        enviar_notificacion($pdo, $data, true);
        $enviados++;
    }

    return $enviados;
}
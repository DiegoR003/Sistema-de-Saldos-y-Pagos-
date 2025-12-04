<?php
// App/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';          // si aquí usas db() en algún lado
require_once __DIR__ . '/pusher_config.php'; // si tienes la config de pusher aquí

/**
 * Inserta una notificación en la tabla `notificaciones` y opcionalmente dispara Pusher.
 *
 * Estructura esperada en $data:
 *  [
 *    'tipo'          => 'sistema' | 'email' | etc,
 *    'canal'         => 'interna' | 'externa' | etc,
 *    'titulo'        => 'Texto corto',
 *    'cuerpo'        => 'Texto largo',
 *    'usuario_id'    => int|null,
 *    'cliente_id'    => int|null,
 *    'correo_destino'=> string|null,
 *    'ref_tipo'      => string|null,   // p.ej. 'cotizacion'
 *    'ref_id'        => int|null,      // id de la cotización
 *    'estado'        => 'pendiente' | 'enviada' | 'leida' ...
 *  ]
 */
function enviar_notificacion(PDO $pdo, array $data, bool $dispararPusher = true): int
{
    // Valores por defecto
    $defaults = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => '',
        'cuerpo'         => '',
        'usuario_id'     => null,
        'cliente_id'     => null,
        'correo_destino' => null,
        'ref_tipo'       => null,
        'ref_id'         => null,
        'estado'         => 'pendiente',
        'programada_en'  => null,
    ];

    $data = array_merge($defaults, $data);

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

    // Si quieres disparar Pusher aquí, lo haces:
    if ($dispararPusher && function_exists('pusher_trigger_notificacion')) {
        pusher_trigger_notificacion([
            'id'      => $idNotif,
            'titulo'  => $data['titulo'],
            'cuerpo'  => $data['cuerpo'],
            'ref_tipo'=> $data['ref_tipo'],
            'ref_id'  => $data['ref_id'],
        ]);
    }

    return $idNotif;
}

/**
 * Helper: notificación de cotización aprobada.
 */
function notificar_cotizacion_aprobada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => 'Cotización aprobada',
        'cuerpo'         => 'La cotización ' . $cotizacion['folio'] .
                            ' del cliente ' . $cotizacion['cliente_nombre'] .
                            ' ha sido aprobada.',
        'usuario_id'     => $usuarioIdActual,          // quien aprobó
        'cliente_id'     => $cotizacion['cliente_id'] ?? null,
        'correo_destino' => $cotizacion['correo'] ?? null,
        'ref_tipo'       => 'cotizacion',
        'ref_id'         => $cotizacion['id'],
        'estado'         => 'pendiente',
    ];

    return enviar_notificacion($pdo, $data, true);
}

/**
 * Helper: notificación de cotización rechazada.
 */
function notificar_cotizacion_rechazada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'sistema',
        'canal'          => 'interna',
        'titulo'         => 'Cotización rechazada',
        'cuerpo'         => 'La cotización ' . $cotizacion['folio'] .
                            ' del cliente ' . $cotizacion['cliente_nombre'] .
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

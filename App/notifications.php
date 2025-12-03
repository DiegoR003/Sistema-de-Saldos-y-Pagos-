<?php
// Public/api/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';

/**
 * Crea una notificación en la tabla `notificaciones`.
 *
 * $data:
 *  - tipo          'interna' | 'externa'
 *  - canal         'sistema' | 'email'
 *  - titulo        (string)
 *  - cuerpo        (string)
 *  - usuario_id    (int|null)   // null => notificación global
 *  - cliente_id    (int|null)
 *  - correo_destino(string|null)
 *  - ref_tipo      (string|null) ej: 'cotizacion'
 *  - ref_id        (int|null)
 *  - estado        (string) enum('pendiente','enviada','vista','archivada')
 */
function crear_notificacion(PDO $pdo, array $data): void
{
    $sql = "
      INSERT INTO notificaciones
        (tipo, canal, titulo, cuerpo,
         usuario_id, cliente_id, correo_destino,
         ref_tipo, ref_id,
         estado, programada_en, creado_en)
      VALUES
        (:tipo, :canal, :titulo, :cuerpo,
         :usuario_id, :cliente_id, :correo_destino,
         :ref_tipo, :ref_id,
         :estado, NULL, NOW())
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        // por defecto: notificación interna dentro del sistema
        ':tipo'           => $data['tipo'] ?? 'interna',
        ':canal'          => $data['canal'] ?? 'sistema',
        ':titulo'         => $data['titulo'] ?? '',
        ':cuerpo'         => $data['cuerpo'] ?? '',
        ':usuario_id'     => $data['usuario_id'] ?? null,
        ':cliente_id'     => $data['cliente_id'] ?? null,
        ':correo_destino' => $data['correo_destino'] ?? null,
        ':ref_tipo'       => $data['ref_tipo'] ?? null,
        ':ref_id'         => $data['ref_id'] ?? null,
        // nueva notificación => pendiente
        ':estado'         => $data['estado'] ?? 'pendiente',
    ]);
}

/**
 * Notificación cuando cambia el estado de una cotización
 * (recibida / aprobada / rechazada).
 *
 * $estado puede ser: 'recibida', 'aprobada', 'rechazada'
 */
function notificar_cotizacion_estado(PDO $pdo, int $cotizacionId, string $estado): void
{
    // 1) Leer info básica de la cotización
    $st = $pdo->prepare("
      SELECT c.id, c.folio, c.cliente_nombre, c.cliente_correo, c.cliente_id
      FROM cotizaciones c
      WHERE c.id = ?
    ");
    $st->execute([$cotizacionId]);
    $cot = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cot) {
        return; // nada que notificar
    }

    // 2) Armar textos según estado
    switch ($estado) {
        case 'recibida':
            $titulo = "Nueva cotización recibida ({$cot['folio']})";
            $cuerpo = "Se ha recibido una nueva cotización del cliente {$cot['cliente_nombre']}.";
            break;

        case 'aprobada':
            $titulo = "Cotización aprobada ({$cot['folio']})";
            $cuerpo = "La cotización {$cot['folio']} del cliente {$cot['cliente_nombre']} ha sido aprobada.";
            break;

        case 'rechazada':
            $titulo = "Cotización rechazada ({$cot['folio']})";
            $cuerpo = "La cotización {$cot['folio']} del cliente {$cot['cliente_nombre']} ha sido rechazada.";
            break;

        default:
            return; // estado desconocido
    }

    // 3) Notificación interna para el panel (admin + operadores)
    crear_notificacion($pdo, [
        'tipo'      => 'interna',
        'canal'     => 'sistema',          // coincide con ENUM de la tabla
        'titulo'    => $titulo,
        'cuerpo'    => $cuerpo,
        'usuario_id'=> null,               // null => global
        'cliente_id'=> $cot['cliente_id'] ?: null,
        'correo_destino' => null,
        'ref_tipo'  => 'cotizacion',
        'ref_id'    => $cot['id'],
        'estado'    => 'pendiente',
    ]);

    // 4) Más adelante aquí puedes disparar Pusher, email, etc.
}

<?php
// App/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/pusher_config.php';

/**
 * Función principal: Inserta una notificación en la BD y dispara Pusher en tiempo real.
 */
function enviar_notificacion(PDO $pdo, array $data, bool $dispararPusher = true): int
{
    $defaults = [
        'tipo'           => 'interna',
        'canal'          => 'sistema',
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

    try {
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

        // 2. Disparar evento a Pusher
        if ($dispararPusher) {
            pusher_trigger_notificacion([
                'id'         => $idNotif,
                'titulo'     => $data['titulo'],
                'cuerpo'     => $data['cuerpo'],
                'ref_tipo'   => $data['ref_tipo'],
                'ref_id'     => $data['ref_id'],
                'usuario_id' => $data['usuario_id'],
                'cliente_id' => $data['cliente_id']
            ]);
        }

        return $idNotif;

    } catch (Exception $e) {
        error_log("Error insertando notificación: " . $e->getMessage());
        return 0;
    }
}

/**
 * Función interna: Conecta con la API de Pusher y envía el evento.
 */
function pusher_trigger_notificacion(array $notifData): void
{
    try {
        if (!function_exists('pusher_client')) {
            error_log("pusher_client() no existe");
            return; 
        }
        
        $pusher = pusher_client();
        $canales = [];

        // 1. Canal de Usuario (Admin/Operador)
        if (!empty($notifData['usuario_id'])) {
            $canales[] = 'notificaciones_user_' . $notifData['usuario_id'];
        }
        
        // 2. Canal de Cliente (CRÍTICO - ESTO ES LO QUE FALTABA)
        if (!empty($notifData['cliente_id'])) {
            $canales[] = 'notificaciones_cliente_' . $notifData['cliente_id'];
        }

        // 3. Canal Global (Si no tiene destinatario específico)
        if (empty($canales)) {
            $canales[] = 'notificaciones_global';
        }

        $evento = 'nueva-notificacion';

        // Log para debug (comentar en producción)
        error_log("Pusher - Enviando a canales: " . implode(', ', $canales));
        error_log("Pusher - Datos: " . json_encode($notifData));

        // Enviar a todos los canales correspondientes
        foreach ($canales as $canal) {
            $pusher->trigger($canal, $evento, $notifData);
        }

    } catch (Exception $e) {
        error_log('Error Pusher: ' . $e->getMessage());
    }
}

/**
 * Helper: Notificación de cotización aprobada.
 */
function notificar_cotizacion_aprobada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'interna',
        'canal'          => 'sistema',
        'titulo'         => 'Cotización aprobada',
        'cuerpo'         => 'La cotización ' . ($cotizacion['folio'] ?? '---') .
                            ' del cliente ' . ($cotizacion['cliente_nombre'] ?? 'Desconocido') .
                            ' ha sido aprobada.',
        'usuario_id'     => $usuarioIdActual,
        'cliente_id'     => $cotizacion['cliente_id'] ?? null,
        'ref_tipo'       => 'cotizacion',
        'ref_id'         => $cotizacion['id'],
    ];

    return enviar_notificacion($pdo, $data, true);
}

/**
 * Helper: Notificación de cotización rechazada.
 */
function notificar_cotizacion_rechazada(PDO $pdo, array $cotizacion, ?int $usuarioIdActual = null): int
{
    $data = [
        'tipo'           => 'interna',
        'canal'          => 'sistema',
        'titulo'         => 'Cotización rechazada',
        'cuerpo'         => 'La cotización ' . ($cotizacion['folio'] ?? '---') .
                            ' del cliente ' . ($cotizacion['cliente_nombre'] ?? 'Desconocido') .
                            ' ha sido rechazada.',
        'usuario_id'     => $usuarioIdActual,
        'cliente_id'     => $cotizacion['cliente_id'] ?? null,
        'ref_tipo'       => 'cotizacion',
        'ref_id'         => $cotizacion['id'],
    ];

    return enviar_notificacion($pdo, $data, true);
}

/**
 * Helper: Notificación de NUEVA cotización recibida.
 */
function notificar_nueva_cotizacion(PDO $pdo, array $cotizacion): int
{
    $cliente = $cotizacion['empresa'] ?? 'Cliente web';
    $total   = number_format((float)($cotizacion['total'] ?? 0), 2);
    
    $titulo  = 'Nueva cotización recibida';
    $cuerpo  = "Has recibido una nueva cotización de {$cliente} por \${$total}.";

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

    foreach ($destinatarios as $uid) {
        $data = [
            'tipo'           => 'interna',
            'canal'          => 'sistema',
            'titulo'         => $titulo,
            'cuerpo'         => $cuerpo,
            'usuario_id'     => $uid,
            'ref_tipo'       => 'cotizacion',
            'ref_id'         => $cotizacion['id'],
        ];

        enviar_notificacion($pdo, $data, true);
        $enviados++;
    }

    return $enviados;
}

/**
 * Helper: Notificación de NUEVO CARGO generado (Para Cliente y Admin).
 */
function notificar_nuevo_cargo(PDO $pdo, array $datos): void
{
    // 1. Datos necesarios
    $clienteId = (int)$datos['cliente_id'];
    $empresa   = $datos['empresa'] ?? 'Cliente';
    $monto     = $datos['monto'] ?? 0;
    $montoFmt  = number_format((float)$monto, 2);
    $periodo   = $datos['periodo'] ?? '';
    $cargoId   = (int)$datos['cargo_id'];
    $accion    = $datos['accion'] ?? 'generado';

    // Log para debug
    error_log("notificar_nuevo_cargo - Cliente ID: $clienteId, Monto: $montoFmt");

    // ---------------------------------------------------
    // A) NOTIFICACIÓN AL CLIENTE (Campanita + Pusher)
    // ---------------------------------------------------
    $tituloCli = "Nuevo Cargo: $$montoFmt";
    $cuerpoCli = "Se ha $accion tu cargo del periodo $periodo. Vence pronto.";

    $dataCliente = [
        'tipo'       => 'externa', // IMPORTANTE: tipo 'externa' para clientes
        'canal'      => 'sistema',
        'titulo'     => $tituloCli,
        'cuerpo'     => $cuerpoCli,
        'cliente_id' => $clienteId, // CRÍTICO: Esto dispara a 'notificaciones_cliente_ID'
        'usuario_id' => null, // NULL para que no vaya a usuarios admin
        'ref_tipo'   => 'cargo',
        'ref_id'     => $cargoId,
        'estado'     => 'pendiente'
    ];
    
    $notifId = enviar_notificacion($pdo, $dataCliente, true);
    error_log("Notificación cliente enviada - ID: $notifId");

    // ---------------------------------------------------
    // B) NOTIFICACIÓN AL ADMIN (Campanita Interna)
    // ---------------------------------------------------
    $sqlAdmins = "
        SELECT DISTINCT u.id 
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id = u.id
        JOIN roles r ON r.id = ur.rol_id
        WHERE r.nombre IN ('admin', 'operador') AND u.activo = 1
    ";
    $admins = $pdo->query($sqlAdmins)->fetchAll(PDO::FETCH_COLUMN);

    foreach ($admins as $uid) {
        $dataAdmin = [
            'tipo'       => 'interna',
            'canal'      => 'sistema',
            'titulo'     => "Cargo $accion ($empresa)",
            'cuerpo'     => "Monto: $$montoFmt. Periodo: $periodo",
            'usuario_id' => $uid,
            'cliente_id' => null, // NULL para que no vaya a cliente
            'ref_tipo'   => 'cargo',
            'ref_id'     => $cargoId
        ];
        enviar_notificacion($pdo, $dataAdmin, true);
    }
}
<?php
// Public/api/cotizacion_reject.php
declare(strict_types=1);

// 1. CARGAR DEPENDENCIAS (Con las rutas correctas ../../)
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/notifications.php';
require_once __DIR__ . '/../../App/pusher_config.php'; // ✅ Vital para la campanita

// Cargar el mailer si existe
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

// Iniciar sesión para saber quién rechaza (y evitar errores de sesión)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function back(string $msg, bool $ok): never {
    $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
    header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
    exit;
}

// Validar método POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) back('ID inválido', false);

try {
    $pdo = db();

    // 2. BUSCAR DATOS (CRUCIAL: Hacer esto PRIMERO)
    // Necesitamos los datos del cliente para poder enviarle el correo
    $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $cot = $st->fetch(PDO::FETCH_ASSOC);

    if (!$cot) {
        throw new RuntimeException('Cotización no encontrada');
    }

    if ($cot['estado'] !== 'pendiente') {
        throw new RuntimeException('La cotización ya no está pendiente.');
    }

    // Extraer datos en variables limpias
    $folio         = $cot['folio'] ?? ('COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT));
    $nombreCliente = $cot['empresa'] ?? 'Cliente';
    $correoCliente = $cot['correo'] ?? '';
    $clienteId     = $cot['cliente_id'] ?? null;

    // 3. ACTUALIZAR ESTADO EN BD
    $upd = $pdo->prepare("UPDATE cotizaciones SET estado='rechazada' WHERE id=?");
    $upd->execute([$id]);

    // 4. NOTIFICACIÓN AL SISTEMA (Campanita)
    $cotizacionData = [
        'id'             => $cot['id'],
        'folio'          => $folio,
        'cliente_nombre' => $nombreCliente,
        'cliente_id'     => $clienteId,
        'correo'         => $correoCliente,
    ];
    
    // Obtenemos el ID del usuario actual para registrar quién rechazó
    $usuarioIdActual = 0;
    // Buscamos el ID en las diferentes variables de sesión posibles
    if (!empty($_SESSION['usuario_id'])) $usuarioIdActual = (int)$_SESSION['usuario_id'];
    elseif (!empty($_SESSION['user']['id'])) $usuarioIdActual = (int)$_SESSION['user']['id'];

    try {
        // Esto guarda en la tabla 'notificaciones' y dispara Pusher
        notificar_cotizacion_rechazada($pdo, $cotizacionData, $usuarioIdActual);
    } catch (Throwable $e) {
        // Si falla la notificación visual, seguimos (no es crítico)
    }

    // 5. ENVIAR CORREO AL CLIENTE
    if (!empty($correoCliente) && function_exists('enviar_correo_sistema')) {
        $asunto = "Actualización de tu Cotización $folio - Banana Group";
        
        $html = "
        <div style='font-family: Arial, sans-serif; color: #333; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
            <h2 style='color: #dc3545;'>Hola, " . htmlspecialchars($nombreCliente) . "</h2>
            <p>Te informamos que tu cotización con folio <strong>{$folio}</strong> ha sido <strong style='color: #dc3545;'>RECHAZADA</strong>.</p>
            <p>Esto puede deberse a falta de disponibilidad, datos incorrectos o porque la propuesta no se ajusta a nuestros criterios actuales.</p>
            <hr>
            <p>Si deseas más información o enviar una nueva solicitud, por favor contáctanos.</p>
            <p><em>Saludos,<br>El equipo de Banana Group</em></p>
        </div>
        ";

        // Enviamos el correo
        enviar_correo_sistema($correoCliente, $nombreCliente, $asunto, $html);
    }

    back('Cotización rechazada correctamente.', true);

} catch (Throwable $e) {
    back('Error: ' . $e->getMessage(), false);
}
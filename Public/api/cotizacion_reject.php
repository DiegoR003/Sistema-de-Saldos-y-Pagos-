<?php
// Public/api/cotizacion_reject.php
declare(strict_types=1);

// 1. Cargamos las dependencias correctas (las mismas que en approve)
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/notifications.php'; // Usamos notifications.php (plural)

// Iniciar sesión para saber quién rechaza (si aplica)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function back(string $msg, bool $ok): never {
    $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
    header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) back('ID inválido', false);

try {
    $pdo = db();

    // 2. BUSCAR DATOS: Necesitamos leer la cotización ANTES de notificar
    $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $cot = $st->fetch(PDO::FETCH_ASSOC);

    if (!$cot) {
        throw new RuntimeException('Cotización no encontrada');
    }

    if ($cot['estado'] !== 'pendiente') {
        throw new RuntimeException('La cotización ya no está pendiente.');
    }

    // 3. Preparar datos para la notificación
    // Aquí aseguramos que el nombre del cliente se envíe correctamente
    $cotizacionData = [
        'id'             => $cot['id'],
        // Si no hay folio real, generamos uno visual
        'folio'          => $cot['folio'] ?? ('COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT)),
        // Importante: Mapeamos 'empresa' a 'cliente_nombre' para que salga en la alerta
        'cliente_nombre' => $cot['empresa'] ?? 'Cliente Desconocido',
        'cliente_id'     => $cot['cliente_id'] ?? null,
        'correo'         => $cot['correo'] ?? '',
    ];

    $usuarioIdActual = $_SESSION['usuario_id'] ?? null;

    // 4. Enviar Notificación (usando tu helper existente en notifications.php)
    try {
        notificar_cotizacion_rechazada($pdo, $cotizacionData, $usuarioIdActual);
    } catch (Throwable $e) {
        // Si falla la notificación, seguimos con el rechazo (no detenemos el proceso)
    }

    // 5. Actualizar estado en Base de Datos
    $upd = $pdo->prepare("UPDATE cotizaciones SET estado='rechazada' WHERE id=?");
    $upd->execute([$id]);

    back('Cotización rechazada correctamente', true);

} catch (Throwable $e) {
    back('Error: ' . $e->getMessage(), false);
}
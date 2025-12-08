<?php
// Public/api/cargos_emitir.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/date_utils.php';

// ✅ 1. Incluir dependencias de notificación y correo
require_once __DIR__ . '/../../App/notifications.php';
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();

function back(string $msg, bool $ok = true): never {
    // Intentamos volver a la página anterior, o por defecto a cobrar
    $r = $_SERVER['HTTP_REFERER'] ?? '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar';
    // Limpiamos params viejos de la url
    $r = strtok($r, '?') . '?' . parse_url($r, PHP_URL_QUERY); 
    
    // Agregamos feedback
    $sep = (strpos($r, '?') === false) ? '?' : '&';
    $r .= $sep . ($ok ? 'ok=1&msg=' : 'err=') . urlencode($msg);
    
    header("Location: $r");
    exit;
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }

    $pdo = db();

    /* =======================================================
       1. Datos enviados
       ======================================================= */
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    if ($ordenId <= 0) back('Orden inválida', false);

    $periodo_inicio = trim((string)($_POST['periodo_inicio'] ?? ''));
    $periodo_fin    = trim((string)($_POST['periodo_fin'] ?? ''));

    if ($periodo_inicio === '' || $periodo_fin === '') {
        back('Periodo inválido para emitir.', false);
    }

    $inicio = new DateTimeImmutable($periodo_inicio);
    $fin    = new DateTimeImmutable($periodo_fin);

    /* =======================================================
       2. Validar orden y OBTENER DATOS CLIENTE
       ======================================================= */
    // ✅ MODIFICACIÓN: Hacemos JOIN para traer correo y empresa del cliente
    $st = $pdo->prepare("
        SELECT o.*, c.empresa, c.correo 
        FROM ordenes o
        JOIN clientes c ON c.id = o.cliente_id
        WHERE o.id = ?
    ");
    $st->execute([$ordenId]);
    $orden = $st->fetch(PDO::FETCH_ASSOC);

    if (!$orden) back('Orden no encontrada', false);
    if ($orden['estado'] !== 'activa')
        back('La orden no está activa. No puedes emitir cargos.', false);

    /* =======================================================
       3. Verificar si YA existe cargo del periodo
       ======================================================= */
    $st = $pdo->prepare("
        SELECT *
        FROM cargos
        WHERE orden_id = ?
          AND periodo_inicio = ?
          AND periodo_fin = ?
        LIMIT 1
    ");
    $st->execute([
        $ordenId,
        $inicio->format('Y-m-d'),
        $fin->format('Y-m-d')
    ]);
    $cargo = $st->fetch(PDO::FETCH_ASSOC);

    /* =======================================================
       4. Items Activos
       ======================================================= */
    $st = $pdo->prepare("
        SELECT id, concepto, monto
        FROM orden_items
        WHERE orden_id = ?
          AND pausado = 0
          AND (billing_type = 'recurrente'
            OR (billing_type='una_vez' AND end_at IS NULL))
        ORDER BY id
    ");
    $st->execute([$ordenId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) back("La orden no tiene servicios activos.", false);

    /* =======================================================
       5. Calcular totales y Preparar lista HTML
       ======================================================= */
    $subtotal = 0.0;
    $conceptosHtml = []; // ✅ Para el correo

    foreach ($items as $r) {
        $montoItem = (float)$r['monto'];
        $subtotal += $montoItem;
        // Agregamos a la lista visual
        $conceptosHtml[] = "<li>" . htmlspecialchars($r['concepto']) . ": <strong>$" . number_format($montoItem, 2) . "</strong></li>";
    }

    $iva   = round($subtotal * 0.16, 2);
    $total = round($subtotal + $iva, 2);
    
    // Unimos la lista para el correo
    $listaServicios = "<ul>" . implode('', $conceptosHtml) . "</ul>";

    $pdo->beginTransaction();

    /* =======================================================
       6. Si el cargo YA EXISTE → ACTUALIZAR (pero respetar estatus)
       ======================================================= */
    if ($cargo) {

        $estatus = ($cargo['estatus'] === 'pagado') ? 'pagado' : 'emitido';

        $upd = $pdo->prepare("
            UPDATE cargos
            SET subtotal=?, iva=?, total=?, estatus=?
            WHERE id=?
        ");
        $upd->execute([$subtotal, $iva, $total, $estatus, $cargo['id']]);

        // Regenerar cargo_items
        $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id=?")
            ->execute([$cargo['id']]);

        $insPart = $pdo->prepare("
            INSERT INTO cargo_items
                (cargo_id, orden_item_id, concepto, monto_base, iva, total)
            VALUES (?,?,?,?,?,?)
        ");

        foreach ($items as $r) {
            $mBase = (float)$r['monto'];
            $mIva  = round($mBase * 0.16, 2);
            $insPart->execute([
                $cargo['id'],
                $r['id'],
                $r['concepto'],
                $mBase,
                $mIva,
                $mBase + $mIva
            ]);
        }

        $pdo->commit();
        
        // --- NOTIFICACIONES (Update) ---
        enviar_notificaciones_cargo($pdo, $orden, $cargo['id'], $total, $listaServicios, $inicio->format('Y-m-d'), $fin->format('Y-m-d'), 'actualizado');

        back("Cargo actualizado (".$estatus.")", true);
    }

    /* =======================================================
       7. Si NO existe cargo → CREAR UNO
       ======================================================= */
    $ins = $pdo->prepare("
        INSERT INTO cargos
            (orden_id, rfc_id, periodo_inicio, periodo_fin,
             subtotal, iva, total, estatus, creado_en)
        VALUES (?,?,?,?,?,?,?, 'emitido', NOW())
    ");

    $ins->execute([
        $ordenId,
        $orden['rfc_id'],
        $inicio->format('Y-m-d'),
        $fin->format('Y-m-d'),
        $subtotal,
        $iva,
        $total
    ]);

    $cargoId = (int)$pdo->lastInsertId();

    /* === Insertar sus items === */
    $insPart = $pdo->prepare("
        INSERT INTO cargo_items
            (cargo_id, orden_item_id, concepto, monto_base, iva, total)
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($items as $r) {
        $mBase = (float)$r['monto'];
        $mIva  = round($mBase * 0.16, 2);

        $insPart->execute([
            $cargoId,
            $r['id'],
            $r['concepto'],
            $mBase,
            $mIva,
            $mBase + $mIva
        ]);
    }

    $pdo->commit();

    // --- NOTIFICACIONES (Nuevo) ---
    enviar_notificaciones_cargo($pdo, $orden, $cargoId, $total, $listaServicios, $inicio->format('Y-m-d'), $fin->format('Y-m-d'), 'generado');

    back("Cargo emitido correctamente", true);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    back("Error al emitir cargo: " . $e->getMessage(), false);
}

/**
 * Función auxiliar para enviar las notificaciones sin repetir código
 */
function enviar_notificaciones_cargo($pdo, $orden, $cargoId, $total, $listaServicios, $fInicio, $fFin, $accionTxt) {
    
    // 1. Notificación Interna (Sistema/Pusher)
    try {
        $usuarioIdActual = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
        $notifData = [
            'tipo'       => 'sistema',
            'canal'      => 'interna',
            'titulo'     => "Cargo $accionTxt",
            'cuerpo'     => "Se ha $accionTxt el cargo de {$orden['empresa']} por $" . number_format($total, 2),
            'usuario_id' => $usuarioIdActual, 
            'ref_tipo'   => 'pago', 
            'ref_id'     => $cargoId,
            'estado'     => 'pendiente'
        ];
        enviar_notificacion($pdo, $notifData, true);
    } catch (Exception $e) {}

    // 2. Correo al Cliente
    if (!empty($orden['correo']) && function_exists('enviar_correo_sistema')) {
        $asunto = "Aviso de Cargo - {$orden['empresa']}";
        $totalFmt = number_format($total, 2);
        
        $html = "
        <div style='font-family: Arial, sans-serif; color: #333; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
            <h2 style='color: #fdd835;'>Hola, " . htmlspecialchars($orden['empresa']) . "</h2>
            <p>Te informamos que se ha <strong>$accionTxt</strong> un cargo correspondiente a tus servicios.</p>
            
            <div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                <p><strong>Periodo:</strong> $fInicio al $fFin</p>
                <p><strong>Total a Pagar:</strong> <span style='font-size: 1.2em; font-weight: bold;'>$ $totalFmt</span></p>
                <hr>
                <p><strong>Detalle:</strong></p>
                $listaServicios
            </div>

            <p>Puedes consultar el detalle completo ingresando a tu portal:</p>
            <p>
                <a href='http://localhost/Sistema-de-Saldos-y-Pagos-/Public/login.php' 
                   style='background: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                   Ir a mi Portal
                </a>
            </p>
            <p><small>Si ya realizaste tu pago, por favor haz caso omiso a este mensaje.</small></p>
        </div>
        ";

        enviar_correo_sistema($orden['correo'], $orden['empresa'], $asunto, $html);
    }
}
?>
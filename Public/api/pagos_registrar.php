<?php
// Public/api/pagos_registrar.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/notifications.php';
require_once __DIR__ . '/../../App/pusher_config.php';

if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}
if (file_exists(__DIR__ . '/../../App/date_utils.php')) {
    require_once __DIR__ . '/../../App/date_utils.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Fallbacks de funciones utilitarias
if (!function_exists('end_by_interval')) {
    function end_by_interval(DateTimeImmutable $start, string $unit, int $count): DateTimeImmutable {
        if ($unit === 'anual') return $start->modify('+1 year')->modify('-1 day');
        $count = max(1, (int)$count);
        return $start->modify("+{$count} month")->modify('-1 day');
    }
}
if (!function_exists('month_start')) {
    function month_start(int $y, int $m): DateTimeImmutable {
        $m = max(1, min(12, $m));
        return new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m));
    }
}
if (!function_exists('money_round')) {
    function money_round(float $v): float { return round($v, 2); }
}

const IVA_TASA = 0.16;

function back(string $msg, bool $ok, ?int $ordenId = null): never {
    $base = '/Sistema-de-Saldos-y-Pagos-/Modules/cobro.php'; 
    $url = $ordenId ? "$base?m=cobro&orden_id=$ordenId" : "$base?m=cobros";
    header('Location: ' . $url . '&ok=' . ($ok ? 1 : 0) . '&' . ($ok ? 'msg=' : 'err=') . urlencode($msg));
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405); exit('Método no permitido');
    }

    $ordenId    = (int)($_POST['orden_id'] ?? 0);
    $metodo     = trim((string)($_POST['metodo'] ?? 'EFECTIVO'));
    $referencia = trim((string)($_POST['referencia'] ?? ''));
    $montoForm  = (float)($_POST['monto'] ?? 0);

    if ($ordenId <= 0) back('Orden inválida', false);

    $pdo = db();

    // 1) Cargar orden, cliente y RFC EMISOR
    $st = $pdo->prepare("
        SELECT o.*, c.empresa, c.correo, c.telefono, r.razon_social as emisor_razon, r.rfc as emisor_rfc
        FROM ordenes o
        JOIN clientes c ON c.id = o.cliente_id
        LEFT JOIN company_rfcs r ON r.id = o.rfc_id
        WHERE o.id = ? FOR UPDATE
    ");
    $st->execute([$ordenId]);
    $orden = $st->fetch(PDO::FETCH_ASSOC);

    if (!$orden) back('Orden no encontrada', false);
    if ($orden['estado'] !== 'activa') back('La orden no está activa', false, $ordenId);

    // Datos del Emisor (Si no tiene RFC asignado, ponemos datos genéricos)
    $emisorNombre = $orden['emisor_razon'] ?: 'Banana Group Marketing';
    $emisorRFC    = $orden['emisor_rfc'] ? ('RFC: ' . $orden['emisor_rfc']) : 'info@bananagroup.mx';

    // 2) Periodo
    $iniStr = trim((string)($_POST['periodo_inicio'] ?? ''));
    $finStr = trim((string)($_POST['periodo_fin'] ?? ''));

    if ($iniStr && $finStr) {
        $inicio = new DateTimeImmutable($iniStr);
        $fin    = new DateTimeImmutable($finStr);
    } else {
        $y = (int)date('Y'); $m = (int)date('n');
        $inicio = month_start($y, $m);
        $fin    = end_by_interval($inicio, 'mensual', 1);
    }
    $periodo_inicio = $inicio->format('Y-m-d');
    $periodo_fin    = $fin->format('Y-m-d');

    $pdo->beginTransaction();

    // 3) Cargo existente o nuevo
    $stCargo = $pdo->prepare("SELECT * FROM cargos WHERE orden_id=? AND periodo_inicio=? AND periodo_fin=? LIMIT 1");
    $stCargo->execute([$ordenId, $periodo_inicio, $periodo_fin]);
    $cargo = $stCargo->fetch(PDO::FETCH_ASSOC);
    $cargoId = $cargo ? (int)$cargo['id'] : 0;

    // 4) Items a cobrar
    $it = $pdo->prepare("
        SELECT * FROM orden_items 
        WHERE orden_id=? AND pausado=0 
          AND (billing_type='recurrente' OR (billing_type='una_vez' AND end_at IS NULL))
          AND monto > 0 ORDER BY id ASC
    ");
    $it->execute([$ordenId]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) { $pdo->rollBack(); back('No hay partidas para cobrar', false, $ordenId); }

    // 5) Calcular totales
    $subtotal = 0.0; $iva = 0.0; $total = 0.0;
    foreach ($items as $r) {
        $m = (float)$r['monto'];
        $mIva = money_round($m * IVA_TASA);
        $subtotal += $m; $iva += $mIva; $total += $m + $mIva;
    }
    $subtotal = money_round($subtotal);
    $iva = money_round($iva);
    $total = money_round($total);

    // 6) Guardar/Actualizar Cargo
    if ($cargoId === 0) {
        $insCargo = $pdo->prepare("INSERT INTO cargos (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus) VALUES (?,?,?,?,?,?,?,'pagado')");
        $insCargo->execute([$ordenId, $orden['rfc_id']?:null, $periodo_inicio, $periodo_fin, $subtotal, $iva, $total]);
        $cargoId = (int)$pdo->lastInsertId();
    } else {
        $updCargo = $pdo->prepare("UPDATE cargos SET subtotal=?, iva=?, total=?, estatus='pagado' WHERE id=?");
        $updCargo->execute([$subtotal, $iva, $total, $cargoId]);
        $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id=?")->execute([$cargoId]);
    }

    $insPart = $pdo->prepare("INSERT INTO cargo_items (cargo_id, orden_item_id, concepto, monto_base, iva, total) VALUES (?,?,?,?,?,?)");
    foreach ($items as $r) {
        $m = (float)$r['monto'];
        $mIva = money_round($m * IVA_TASA);
        $insPart->execute([$cargoId, $r['id'], $r['concepto'], money_round($m), $mIva, money_round($m+$mIva)]);
    }

    // 7) Registrar PAGO
    $montoPago = $montoForm > 0 ? $montoForm : $total;
    $insPago = $pdo->prepare("INSERT INTO pagos (orden_id, monto, metodo, referencia, cargo_id) VALUES (?,?,?,?,?)");
    $insPago->execute([$ordenId, money_round($montoPago), $metodo, $referencia, $cargoId]);
    $pagoId = (int)$pdo->lastInsertId();

    // 8) Actualizar fechas items y saldo orden
    $nextDates = [];
    foreach ($items as $r) {
        if ($r['billing_type'] === 'recurrente') {
            $unit = $r['interval_unit']?:'mensual'; $count=(int)($r['interval_count']?:1);
            $start = new DateTimeImmutable($periodo_inicio);
            $end = end_by_interval($start, $unit, $count);
            $next = $end->modify('+1 day');
            $nextDates[] = $next->format('Y-m-d');
            $pdo->prepare("UPDATE orden_items SET next_run=?, ultimo_periodo_inicio=?, ultimo_periodo_fin=? WHERE id=?")
                ->execute([$next->format('Y-m-d'), $periodo_inicio, $periodo_fin, $r['id']]);
        } else {
            $pdo->prepare("UPDATE orden_items SET end_at=CURDATE(), ultimo_periodo_inicio=?, ultimo_periodo_fin=? WHERE id=?")
                ->execute([$periodo_inicio, $periodo_fin, $r['id']]);
        }
    }
    $proxima = null;
    if ($nextDates) { sort($nextDates); $proxima = $nextDates[0]; }
    $pdo->prepare("UPDATE ordenes SET saldo=GREATEST(0, saldo - ?), proxima_facturacion=COALESCE(?, proxima_facturacion) WHERE id=?")
        ->execute([money_round($montoPago), $proxima, $ordenId]);

    $pdo->commit();

    // =================================================================================
    // 9) NOTIFICACIONES Y GENERACIÓN DE PDF
    // =================================================================================
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    $usuarioIdActual = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;

    // A. Notificación Interna
    try {
        $notifData = [
            'tipo'=>'sistema', 'canal'=>'interna', 
            'titulo'=>'Pago Recibido', 
            'cuerpo'=>"Pago de {$orden['empresa']} por $".number_format($montoPago,2),
            'usuario_id'=>$usuarioIdActual, 'cliente_id'=>$orden['cliente_id'],
            'ref_tipo'=>'pago', 'ref_id'=>$pagoId, 'estado'=>'pendiente'
        ];
        enviar_notificacion($pdo, $notifData, true);
    } catch (Exception $e) {}

    // B. Notificación Cliente
    try {
        $stUserCli = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND activo = 1 LIMIT 1");
        $stUserCli->execute([$orden['correo']]);
        $idUserCliente = (int)$stUserCli->fetchColumn();
        if ($idUserCliente > 0) {
            $notifCliente = [
                'tipo'=>'sistema', 'canal'=>'interna', 
                'titulo'=>'Pago Aplicado', 
                'cuerpo'=>"Recibimos tu pago de $".number_format($montoPago,2),
                'usuario_id'=>$idUserCliente, 'cliente_id'=>$orden['cliente_id'],
                'ref_tipo'=>'pago', 'ref_id'=>$pagoId, 'estado'=>'pendiente'
            ];
            enviar_notificacion($pdo, $notifCliente, true);
        }
    } catch (Exception $e) {}

    // C. GENERAR PDF Y ENVIAR CORREO
    if (function_exists('enviar_correo_sistema') && !empty($orden['correo'])) {
        try {
            // Datos PDF
            $folio     = str_pad((string)$pagoId, 6, '0', STR_PAD_LEFT);
            $fechaPago = date('d/m/Y H:i');
            
            // Logo Base64
            $rutaLogo = __DIR__ . '/../../Public/assets/logo.png'; 
            $logoBase64 = '';
            if (file_exists($rutaLogo)) {
                $type = pathinfo($rutaLogo, PATHINFO_EXTENSION);
                $data = file_get_contents($rutaLogo);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }

            // --- HTML DEL RECIBO (DISEÑO SOLICITADO) ---
            $html = '
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <style>
                    @page { margin: 0px; }
                    body { font-family: sans-serif; font-size: 11px; margin: 0; padding: 0; color: #333; }
                    .header-bg { background-color: #fff2a8; padding: 20px 30px; border-bottom: 3px solid #fdd835; }
                    .header-table { width: 100%; }
                    .logo-img { max-height: 45px; }
                    .title { text-align: right; font-size: 18px; font-weight: bold; color: #444; }
                    .subtitle { text-align: right; font-size: 11px; color: #666; margin-top: 2px; }
                    .info-section { margin: 20px 30px; }
                    .info-table { width: 100%; }
                    .info-table td { vertical-align: top; width: 50%; }
                    .label { font-size: 9px; font-weight: bold; color: #888; text-transform: uppercase; margin-bottom: 2px; }
                    .value { font-size: 11px; color: #000; margin-bottom: 10px; }
                    .items-table { width: calc(100% - 60px); margin: 10px 30px; border-collapse: collapse; }
                    .items-table th { background: #f8f9fa; padding: 6px 8px; text-align: left; font-size: 9px; border-bottom: 1px solid #ccc; }
                    .items-table td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
                    .amount { text-align: right; font-weight: bold; }
                    .total-section { margin: 15px 30px; text-align: right; border-top: 2px solid #fdd835; padding-top: 10px; }
                    .total-val { font-size: 20px; font-weight: bold; }
                    .footer { position: fixed; bottom: 0; left: 0; right: 0; background: #f9f9f9; padding: 10px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; }
                </style>
            </head>
            <body>
                <div class="header-bg">
                    <table class="header-table">
                        <tr>
                            <td>' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo-img">' : '<h2>BANANA</h2>') . '</td>
                            <td class="title">Recibo de Pago<div class="subtitle">Folio: #' . $folio . '</div></td>
                        </tr>
                    </table>
                </div>
                <div class="info-section">
                    <table class="info-table">
                        <tr>
                            <td>
                                <div class="label">EMISOR</div>
                                <div class="value">
                                    <strong>' . htmlspecialchars($emisorNombre) . '</strong><br>
                                    ' . htmlspecialchars($emisorRFC) . '
                                </div>
                            </td>
                            <td>
                                <div class="label">CLIENTE</div>
                                <div class="value">
                                    <strong>' . htmlspecialchars($orden['empresa']) . '</strong><br>
                                    ' . htmlspecialchars($orden['correo']) . '
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><div class="label">FECHA</div><div class="value">' . $fechaPago . '</div></td>
                            <td><div class="label">MÉTODO</div><div class="value">' . strtoupper($metodo) . ' (' . ($referencia ?: 'N/A') . ')</div></td>
                        </tr>
                    </table>
                </div>
                <table class="items-table">
                    <thead><tr><th>CONCEPTO</th><th style="text-align: right;">IMPORTE</th></tr></thead>
                    <tbody>';
            
            foreach ($items as $it) {
                $mItem = (float)$it['monto'];
                // OJO: En tu lógica original el precio items ya podría tener IVA o no. 
                // Aquí asumimos que $it['monto'] es base y le sumamos IVA para mostrar total.
                // Si $it['monto'] YA tiene IVA, quita el "* (1 + IVA_TASA)".
                $mItemTotal = $mItem * (1 + IVA_TASA); 
                $html .= '<tr><td>' . htmlspecialchars($it['concepto']) . '</td><td class="amount">$' . number_format($mItemTotal, 2) . '</td></tr>';
            }
            
            $html .= '</tbody></table>
                <div class="total-section">
                    <span style="font-size: 12px; color: #666; margin-right: 10px;">TOTAL PAGADO:</span>
                    <span class="total-val">$' . number_format($montoPago, 2) . ' MXN</span>
                </div>
                <div class="footer">Este documento es un comprobante de pago interno y no sustituye a una factura fiscal (CFDI).<br>
                Gracias por su preferencia.</div>
            </body>
            </html>';

            // --- Generar PDF ---
            $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $pdfContent = $dompdf->output();

            $tempFile = sys_get_temp_dir() . '/Recibo_' . $folio . '.pdf';
            file_put_contents($tempFile, $pdfContent);

            // --- Enviar Correo ---
            $asunto = "Comprobante de Pago #$folio - Banana Group";
            $cuerpoCorreo = "<h3>¡Pago Recibido!</h3><p>Hola {$orden['empresa']}, hemos recibido tu pago por $".number_format($montoPago,2).". Adjunto encontrarás tu recibo.</p>";
            
            enviar_correo_sistema($orden['correo'], $orden['empresa'], $asunto, $cuerpoCorreo, [$tempFile]);

            if (file_exists($tempFile)) unlink($tempFile);

        } catch (Exception $e) {
            // Si falla el correo, no detenemos el flujo, solo avisamos (o logueamos)
        }
    }

    back('Pago registrado correctamente.', true, $ordenId);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    back('Error: ' . $e->getMessage(), false);
}
?>
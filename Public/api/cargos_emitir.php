<?php
// Public/api/cargos_emitir.php
declare(strict_types=1);

// Carga librerías y configuración
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/date_utils.php';
require_once __DIR__ . '/../../App/pusher_config.php';

// Incluimos mailer si existe
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();

function back(string $msg, bool $ok = true): never {
    $r = $_SERVER['HTTP_REFERER'] ?? '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cobrar';
    $r = strtok($r, '?') . '?' . parse_url($r, PHP_URL_QUERY); 
    $sep = (strpos($r, '?') === false) ? '?' : '&';
    $r .= $sep . ($ok ? 'ok=1&msg=' : 'err=') . urlencode($msg);
    header("Location: $r");
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        exit('Método no permitido');
    }

    $pdo = db();

    // 1. Recibir Datos
    $ordenId = (int)($_POST['orden_id'] ?? 0);
    $periodo_inicio = trim((string)($_POST['periodo_inicio'] ?? ''));
    $periodo_fin    = trim((string)($_POST['periodo_fin'] ?? ''));

    if ($ordenId <= 0 || !$periodo_inicio || !$periodo_fin) {
        back('Datos incompletos.', false);
    }

    $inicio = new DateTimeImmutable($periodo_inicio);
    $fin    = new DateTimeImmutable($periodo_fin);

    // 2. Obtener Info
    $st = $pdo->prepare("SELECT o.*, c.empresa, c.correo FROM ordenes o JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ?");
    $st->execute([$ordenId]);
    $orden = $st->fetch(PDO::FETCH_ASSOC);

    if (!$orden) back('Orden no encontrada.', false);

    // 3. Verificar Cargo Previo
    $st = $pdo->prepare("SELECT * FROM cargos WHERE orden_id = ? AND periodo_inicio = ? AND periodo_fin = ? LIMIT 1");
    $st->execute([$ordenId, $inicio->format('Y-m-d'), $fin->format('Y-m-d')]);
    $cargo = $st->fetch(PDO::FETCH_ASSOC);

    // 4. Calcular Items y Totales
    $st = $pdo->prepare("SELECT id, concepto, monto FROM orden_items WHERE orden_id = ? AND pausado = 0 AND (billing_type = 'recurrente' OR (billing_type='una_vez' AND end_at IS NULL))");
    $st->execute([$ordenId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) back("No hay servicios activos.", false);

    $subtotal = 0.0;
    $listaHtml = "";
    foreach ($items as $r) {
        $m = (float)$r['monto'];
        $subtotal += $m;
        $listaHtml .= "<li>" . htmlspecialchars($r['concepto']) . ": <strong>$" . number_format($m, 2) . "</strong></li>";
    }
    $iva = round($subtotal * 0.16, 2);
    $total = round($subtotal + $iva, 2);

    $pdo->beginTransaction();

    // 5. Guardar/Actualizar Cargo
    if ($cargo) {
        $pdo->prepare("UPDATE cargos SET subtotal=?, iva=?, total=?, estatus='emitido' WHERE id=?")->execute([$subtotal, $iva, $total, $cargo['id']]);
        $pdo->prepare("DELETE FROM cargo_items WHERE cargo_id=?")->execute([$cargo['id']]);
        $cargoId = $cargo['id'];
        $accionTxt = 'actualizado';
    } else {
        $pdo->prepare("INSERT INTO cargos (orden_id, rfc_id, periodo_inicio, periodo_fin, subtotal, iva, total, estatus, creado_en) VALUES (?,?,?,?,?,?,?, 'emitido', NOW())")
            ->execute([$ordenId, $orden['rfc_id'], $inicio->format('Y-m-d'), $fin->format('Y-m-d'), $subtotal, $iva, $total]);
        $cargoId = (int)$pdo->lastInsertId();
        $accionTxt = 'generado';
    }

    $insPart = $pdo->prepare("INSERT INTO cargo_items (cargo_id, orden_item_id, concepto, monto_base, iva, total) VALUES (?,?,?,?,?,?)");
    foreach ($items as $r) {
        $m = (float)$r['monto'];
        $iv = round($m * 0.16, 2);
        $insPart->execute([$cargoId, $r['id'], $r['concepto'], $m, $iv, $m + $iv]);
    }

    $pdo->commit();

    // =========================================================
    // 6. ENVIAR NOTIFICACIONES (MANUAL Y SEGURO)
    // =========================================================
    
    // Variables seguras
    $clienteId = (int)$orden['cliente_id'];
    $empresaName = $orden['empresa'] ?? 'Cliente';
    $correoDest = $orden['correo'] ?? '';
    $montoStr = number_format($total, 2);

    // A) Guardar en BD (Tabla notificaciones)
    $titulo = "Cargo $accionTxt";
    $cuerpo = "Se ha $accionTxt el cargo de $empresaName por $$montoStr. Periodo: " . $inicio->format('d/m') . " al " . $fin->format('d/m');
    
    try {
        $sqlN = "INSERT INTO notificaciones (cliente_id, tipo, canal, titulo, cuerpo, estado, creado_en, ref_tipo, ref_id) 
                 VALUES (?, 'externa', 'sistema', ?, ?, 'pendiente', NOW(), 'cargo', ?)";
        $pdo->prepare($sqlN)->execute([$clienteId, $titulo, $cuerpo, $cargoId]);
    } catch (Exception $e) {}

    // B) PUSHER MANUAL (Sin depender de helpers)
    if (defined('PUSHER_APP_KEY')) {
        try {
            $pusher = new Pusher\Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID, ['cluster' => PUSHER_APP_CLUSTER, 'useTLS' => true]);

            // 1. Canal NOTIFICACIONES (Campanita)
            $pusher->trigger('notificaciones_cliente_' . $clienteId, 'nueva-notificacion', [
                'titulo' => $titulo,
                'cuerpo' => $cuerpo
            ]);

            // 2. Canal SALDO (Dashboard)
            $stS = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM cargos JOIN ordenes o ON o.id=cargos.orden_id WHERE o.cliente_id=? AND estatus IN ('emitido','vencido','pendiente')");
            $stS->execute([$clienteId]);
            $saldoNuevo = (float)$stS->fetchColumn();

            $pusher->trigger('cliente_' . $clienteId, 'actualizar-saldo', [
                'nuevo_saldo' => $saldoNuevo
            ]);

        } catch (Exception $e) {
            // Ignoramos error de Pusher para no detener el script
        }
    }

    // C) CORREO (Con Try-Catch para evitar el error fatal)
    if ($correoDest && function_exists('enviar_correo_sistema')) {
        try {
            $html = "<p>Hola <strong>$empresaName</strong>,</p><p>$cuerpo</p><hr><ul>$listaHtml</ul>";
            enviar_correo_sistema($correoDest, $empresaName, "Aviso de Cargo", $html);
        } catch (Exception $e) {
            // Si falla el correo, no hacemos nada (el usuario no verá el error)
        }
    }

    back("Cargo $accionTxt con éxito", true);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    back("Error: " . $e->getMessage(), false);
}
?>
<?php
// Public/api/cotizacion_approve.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/billing_rules.php';
require_once __DIR__ . '/../../App/notifications.php';


// Asegúrate de que este archivo exista, si no, comenta la línea
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

// ✅ 1. FUNCIÓN GENERAR PASSWORD (Agregada aquí para que no falte)
function generarPassword($largo = 8) {
    return substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, $largo);
}

function back(string $msg, bool $ok): never {
  $url = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';
  header('Location: '.$url.'&ok='.($ok?1:0).'&'.($ok?'msg=':'err=').rawurlencode($msg));
  exit;
}

function norm(?string $s): string {
  return strtolower(trim((string)$s));
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) back('ID inválido', false);

  $periodicidadGlobal = $_POST['periodicidad'] ?? 'mensual';
  if (!in_array($periodicidadGlobal, ['unico','mensual','bimestral'], true)) {
    $periodicidadGlobal = 'mensual';
  }

  $overrides = json_decode($_POST['billing_json'] ?? '{}', true);
  if (!is_array($overrides)) $overrides = [];

  $rfcId = (int)($_POST['rfc_id'] ?? 0);
  if ($rfcId <= 0) back('Selecciona el RFC emisor antes de aprobar.', false);

  $pdo = db();
  $pdo->beginTransaction();

  // 1) Validar RFC
  $st = $pdo->prepare("SELECT id, rfc, razon_social FROM company_rfcs WHERE id = ? LIMIT 1");
  $st->execute([$rfcId]);
  $emisor = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emisor) {
    throw new RuntimeException('RFC emisor inválido.');
  }

  // 2) Cargar cotización
  $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? FOR UPDATE");
  $st->execute([$id]);
  $cot = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cot) {
    throw new RuntimeException('Cotización no encontrada');
  }

  if ($cot['estado'] !== 'pendiente') {
    throw new RuntimeException('La cotización ya no está pendiente.');
  }

  // 3) Cliente (Ficha de Negocio)
  $st = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
  $st->execute([$cot['correo']]);
  $clienteId = (int)$st->fetchColumn();

  if ($clienteId <= 0) {
    $st = $pdo->prepare("INSERT INTO clientes (empresa, correo) VALUES (?, ?)");
    $st->execute([$cot['empresa'], $cot['correo']]);
    $clienteId = (int)$pdo->lastInsertId();
  }

  // =================================================================================
  // 4B) CREAR USUARIO DE ACCESO (LOGIN) AUTOMÁTICO
  // =================================================================================
  
  // ✅ DEFINIR VARIABLES CORRECTAMENTE (Esto arregla el error de 'nombre cannot be null')
  $correoUser = $cot['correo']; 
  $nombreUser = $cot['empresa']; 
  
  // ✅ INICIALIZAR PASSWORD (Esto arregla el error de 'undefined variable')
  $passwordGenerada = null; 

  // Verificar si ya existe el usuario
  $stUser = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
  $stUser->execute([$correoUser]);
  $userId = (int)$stUser->fetchColumn();

  if ($userId <= 0) {
      // --- Si no existe, CREAR NUEVO USUARIO ---
      $passwordGenerada = generarPassword(8);
      $hash = password_hash($passwordGenerada, PASSWORD_DEFAULT);
      
      $insUser = $pdo->prepare("INSERT INTO usuarios (nombre, correo, pass_hash, activo, creado_en) VALUES (?, ?, ?, 1, NOW())");
      $insUser->execute([$nombreUser, $correoUser, $hash]);
      $userId = (int)$pdo->lastInsertId();

      // --- ASIGNAR ROL DE CLIENTE (ID 3) ---
      $insRol = $pdo->prepare("INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (?, 3)");
      $insRol->execute([$userId]);
  }
  // =================================================================================


  // 4) Aprobar cotización (Update)
  $st = $pdo->prepare("UPDATE cotizaciones SET estado = 'aprobada', cliente_id = ? WHERE id = ?");
  $st->execute([$clienteId, $id]);

  // 5) Crear orden
  $vence = null;
  $st = $pdo->prepare("
    INSERT INTO ordenes
      (cotizacion_id, cliente_id, rfc_id, total, saldo, estado, periodicidad, vence_en, proxima_facturacion)
    VALUES
      (:cid, :clid, :rfc, :tot, :sal, 'activa', :per, :vence, NULL)
  ");
  $st->execute([
    ':cid'   => $id,
    ':clid'  => $clienteId,
    ':rfc'   => $rfcId,
    ':tot'   => (float)$cot['total'],
    ':sal'   => (float)$cot['total'],
    ':per'   => $periodicidadGlobal,
    ':vence' => $vence
  ]);

  $ordenId = (int)$pdo->lastInsertId();

  // 6) Items (Tu lógica original de facturación)
  $it = $pdo->prepare("SELECT id, grupo, opcion, valor FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY id ASC");
  $it->execute([$id]);

  $ins = $pdo->prepare("
    INSERT INTO orden_items
      (orden_id, concepto, monto, billing_type, interval_unit, interval_count, next_run, end_at, prorate)
    VALUES
      (:oid, :concepto, :monto, :bt, :iu, :ic, :nr, NULL, 0)
  ");

  foreach ($it as $r) {
    $grupo    = norm($r['grupo'] ?? '');
    $monto    = (float)($r['valor'] ?? 0);
    $concepto = trim(($r['grupo'] ?? '').' - '.($r['opcion'] ?? ''));
    [$bt, $iu, $ic, $nr] = infer_billing($r, $periodicidadGlobal);

    // Lógica de overrides
    if (isset($overrides[$grupo]) && is_array($overrides[$grupo])) {
       $ov = $overrides[$grupo];
       $type = ($ov['type'] ?? $bt) ?: $bt;
       $unit = $ov['unit'] ?? $iu;
       $count = $ov['count'] ?? $ic;
       
       $today = new DateTimeImmutable('today');
       if ($type === 'recurrente') {
           if ($unit === 'anual') $nr = $today->modify('+1 year')->format('Y-m-d');
           elseif ($count >= 2) $nr = $today->modify('+'.((int)$count).' month')->format('Y-m-d');
           else $nr = $today->modify('+1 month')->format('Y-m-d');
       } else {
           $nr = null;
       }
       $bt = $type; $iu = $unit; $ic = $count;
    }

    $ins->execute([
      ':oid'      => $ordenId,
      ':concepto' => $concepto,
      ':monto'    => $monto,
      ':bt'       => $bt,
      ':iu'       => $iu,
      ':ic'       => $ic,
      ':nr'       => $nr,
    ]);
  }

  $pdo->commit();

  // --- Notificación Interna (Pusher) ---
  $cotizacionData = [
      'id'             => $cot['id'],
      'folio'          => $cot['folio'] ?? ('COT-' . str_pad((string)$cot['id'], 5, '0', STR_PAD_LEFT)), 
      'cliente_nombre' => $cot['empresa'] ?? 'Cliente Desconocido',
      'cliente_id'     => $clienteId, 
      'correo'         => $cot['correo'] ?? '',
  ];
  
  if (session_status() === PHP_SESSION_NONE) session_start();
  $usuarioIdActual = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;

  try {
      notificar_cotizacion_aprobada($pdo, $cotizacionData, $usuarioIdActual);
  } catch (Throwable $e) {}

  // =================================================================================
  // 7) ENVIAR CORREO CON CONTRASEÑA (Solo si se generó una nueva)
  // =================================================================================
  if ($passwordGenerada) {
      $asunto = "Bienvenido a Banana Group - Datos de Acceso";
      
      $html = "
      <div style='font-family: Arial, sans-serif; color: #333;'>
        <h2 style='color: #fdd835;'>¡Hola, " . htmlspecialchars($nombreUser) . "!</h2>
        <p>Tu cotización ha sido aprobada exitosamente y hemos creado tu cuenta en nuestro portal de clientes.</p>
        <hr>
        <h3>Tus credenciales de acceso:</h3>
        <p><strong>Usuario:</strong> {$correoUser}</p>
        <p><strong>Contraseña Temporal:</strong> {$passwordGenerada}</p>
        <hr>
        <p><a href='http://localhost/Sistema-de-Saldos-y-Pagos-/Public/login.php' style='background: #fdd835; color: #000; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Ingresar al Portal</a></p>
        <p><small>Por seguridad, te recomendamos cambiar tu contraseña al ingresar.</small></p>
      </div>
      ";

      // Intentar enviar correo si la función existe
      if (function_exists('enviar_correo_sistema')) {
          $enviado = enviar_correo_sistema($correoUser, $nombreUser, $asunto, $html);
      }
  }

  // Mensaje final para la alerta
  $msgExito = 'Cotización aprobada y orden creada.';
  if ($passwordGenerada) {
      $msgExito .= " Usuario creado (Pass temporal: $passwordGenerada).";
  }

  back($msgExito, true);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  back('Error: '.$e->getMessage(), false);
}
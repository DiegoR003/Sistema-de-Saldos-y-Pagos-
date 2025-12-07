<?php
// Public/api/cotizacion_create.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/notifications.php';

// ✅ 1. INCLUIR EL MAILER (Si existe)
if (file_exists(__DIR__ . '/../../App/mailer.php')) {
    require_once __DIR__ . '/../../App/mailer.php';
}

function pick($k,$d=''){
  return isset($_POST[$k]) ? (is_array($_POST[$k])?$_POST[$k]:trim((string)$_POST[$k])) : $d;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

/* 0) URL de regreso */
$redirect = pick('redirect',''); 

/* 1) Datos básicos */
$empresa = pick('nombre');
$correo  = pick('correo');

if ($empresa==='' || $correo==='') {
  if ($redirect !== '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_ok']  = false;
    $_SESSION['flash_msg'] = 'Empresa y correo son requeridos.';
    header('Cache-Control: no-store');
    header('Location: ' . $redirect . '?err=1', true, 303);
    exit;
  }
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'msg'=>'Empresa y correo son requeridos']);
  exit;
}

/* 2) Recalcular seguro */
$grupos = ['cuenta','publicaciones','campañas','reposteo','stories','imprenta','fotos','video','ads','web','mkt'];

$subtotal = 0.0; $items = [];
foreach ($grupos as $g) {
  if (isset($_POST[$g]) && $_POST[$g] !== '') {
    $v = (float)$_POST[$g];
    $subtotal += $v;
    $items[] = ['grupo'=>$g,'opcion'=>$_POST[$g],'valor'=>$v];
  }
}

$adicionales = isset($_POST['adicionales']) ? (float)$_POST['adicionales'] : 0.0;
$tasaIva     = isset($_POST['tasa_iva']) ? (float)$_POST['tasa_iva'] : 16.00; 
$baseIva     = $subtotal + $adicionales;
$impuestos   = round($baseIva * ($tasaIva/100.0), 2);
$total       = round($subtotal + $adicionales + $impuestos, 2);

$minimo      = isset($_POST['minimo']) ? (int)$_POST['minimo'] : 10079;
$cumple      = $total >= $minimo;

/* 3) Guardar en BD */
$pdo = db();
$pdo->beginTransaction();
try {
  $cab = $pdo->prepare("INSERT INTO cotizaciones
    (empresa, correo, subtotal, adicionales, impuestos, total, tasa_iva, minimo, cumple_minimo, estado)
    VALUES (?,?,?,?,?,?,?,?,?, 'pendiente')");
  $cab->execute([$empresa,$correo,$subtotal,$adicionales,$impuestos,$total,$tasaIva,$minimo,$cumple?1:0]);
  $cotId = (int)$pdo->lastInsertId();

  $det = $pdo->prepare("INSERT INTO cotizacion_items (cotizacion_id, grupo, opcion, valor)
                        VALUES (?,?,?,?)");
  foreach ($items as $it) {
    $det->execute([$cotId,$it['grupo'],$it['opcion'],$it['valor']]);
  }

  $pdo->commit();

  // --- A) NOTIFICAR ADMINS Y OPERADORES (Campanita) ---
  try {
      $datosNotif = [
          'id'      => $cotId,
          'empresa' => $empresa,
          'total'   => $total
      ];
      notificar_nueva_cotizacion($pdo, $datosNotif);
  } catch (Throwable $e) {
      // Silencioso
  }

  // --- B) ENVIAR CORREOS A ADMINS Y OPERADORES (NUEVO) ---
  // Reutilizamos $empresa, $total y $correo que ya calculamos arriba
  if (function_exists('enviar_correo_sistema')) {
      try {
          // 1. Buscar correos de Admins y Operadores
          $sqlAdmins = "
              SELECT DISTINCT u.nombre, u.correo 
              FROM usuarios u
              JOIN usuario_rol ur ON ur.usuario_id = u.id
              JOIN roles r ON r.id = ur.rol_id
              WHERE r.nombre IN ('admin', 'operador') AND u.activo = 1
          ";
          $stAdm = $pdo->query($sqlAdmins);
          $destinatarios = $stAdm->fetchAll(PDO::FETCH_ASSOC);

          // 2. Preparar el mensaje HTML
          $asunto = "Nueva Cotización Recibida - Folio #$cotId";
          $cuerpoHtml = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee;'>
                <h2 style='color: #fdd835;'>Nueva Oportunidad de Venta</h2>
                <p>El cliente <strong>".htmlspecialchars($empresa)."</strong> ha enviado una nueva cotización.</p>
                <p><strong>Monto Total:</strong> $".number_format($total, 2)."</p>
                <p><strong>Correo cliente:</strong> ".htmlspecialchars($correo)."</p>
                <hr>
                <p><a href='http://localhost/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones' style='background:#000; color:#fff; padding:10px 15px; text-decoration:none;'>Ver en el Sistema</a></p>
            </div>
          ";

          // 3. Enviar a cada uno
          foreach ($destinatarios as $dest) {
              enviar_correo_sistema($dest['correo'], $dest['nombre'], $asunto, $cuerpoHtml);
          }
      } catch (Throwable $e) {
          // Si falla el correo, no detenemos el proceso, solo lo registramos
          error_log("Error enviando correos de nueva cotización: " . $e->getMessage());
      }
  }

  /* 4) PRG si viene redirect */
  if ($redirect !== '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_ok']  = true;
    $_SESSION['flash_msg'] = "Cotización enviada (folio #{$cotId}).";

    header('Cache-Control: no-store');
    header('Location: ' . $redirect . '?ok=1&folio=' . urlencode((string)$cotId), true, 303);
    exit;
  }

  /* 5) Si no hay redirect, responde JSON */
  header('Content-Type: application/json');
  echo json_encode(['ok'=>true,'cotizacion_id'=>$cotId,'total'=>$total,'cumple_min'=>$cumple]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  if ($redirect !== '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_ok']  = false;
    $_SESSION['flash_msg'] = 'Error al guardar: '.$e->getMessage();
    header('Cache-Control: no-store');
    header('Location: ' . $redirect . '?err=1', true, 303);
    exit;
  }

  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
?>
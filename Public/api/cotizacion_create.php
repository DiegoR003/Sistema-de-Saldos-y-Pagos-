<?php
// Public/api/cotizacion_create.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__.'/../../App/notifications.php';

function pick($k,$d=''){
  return isset($_POST[$k]) ? (is_array($_POST[$k])?$_POST[$k]:trim((string)$_POST[$k])) : $d;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

/* 0) URL de regreso (si viene, haremos PRG) */
$redirect = pick('redirect',''); // ej: /cotizador-mini/index.php

/* 1) Datos básicos */
$empresa = pick('nombre');
$correo  = pick('correo');

if ($empresa==='' || $correo==='') {
  if ($redirect !== '') {
    // con redirect: vuelve con error "amigable"
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

/* 2) Recalcular seguro (NO usar $_POST["total"]) */
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
$tasaIva     = isset($_POST['tasa_iva']) ? (float)$_POST['tasa_iva'] : 16.00; // %
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

  

  /* 4) PRG si viene redirect */
  if ($redirect !== '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_ok']  = true;
    $_SESSION['flash_msg'] = "Cotización enviada (folio #{$cotId}).";

    // Evita re-envío con F5 y regresa al cotizador
    header('Cache-Control: no-store');
    header('Location: ' . $redirect . '?ok=1&folio=' . urlencode($cotId), true, 303);
    exit;
  }

  /* 5) Si no hay redirect, responde JSON (uso vía AJAX) */
  header('Content-Type: application/json');
  echo json_encode(['ok'=>true,'cotizacion_id'=>$cotId,'total'=>$total,'cumple_min'=>$cumple]);

} catch (Throwable $e) {
  $pdo->rollBack();

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

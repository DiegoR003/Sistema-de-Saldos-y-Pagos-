<?php
// Public/api/cotizacion_item_update.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

// ── Validación básica
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit;
}
$id    = isset($_POST['id'])    ? (int)$_POST['id'] : 0;        // id de la cotización
$grupo = isset($_POST['grupo']) ? trim((string)$_POST['grupo']) : '';
$valor = isset($_POST['valor']) ? (float)$_POST['valor'] : null;

if ($id<=0 || $grupo==='' || $valor===null) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
}

// Si tienes un catálogo central, úsalo para localizar etiqueta “opción”
$PRECIOS = require __DIR__ . '/../../App/precios.php'; // devuelve array por grupo
$opcionTxt = '';
if (isset($PRECIOS[$grupo])) {
  // intenta encontrar la “clave/etiqueta” que corresponda al precio elegido
  foreach ($PRECIOS[$grupo] as $clave => $precio) {
    if ((float)$precio === (float)$valor) { $opcionTxt = (string)$clave; break; }
  }
}

// ── DB
$pdo = db();
$pdo->beginTransaction();
try {
  // 1) Asegurar que exista el item del grupo
  $st = $pdo->prepare("SELECT id FROM cotizacion_items WHERE cotizacion_id=? AND grupo=? LIMIT 1");
  $st->execute([$id,$grupo]);
  $itemId = (int)$st->fetchColumn();

  if ($itemId) {
    $st = $pdo->prepare("UPDATE cotizacion_items SET opcion=?, valor=? WHERE id=?");
    $st->execute([$opcionTxt, $valor, $itemId]);
  } else {
    $st = $pdo->prepare("INSERT INTO cotizacion_items (cotizacion_id, grupo, opcion, valor) VALUES (?,?,?,?)");
    $st->execute([$id, $grupo, $opcionTxt, $valor]);
  }

  // 2) Recalcular totales de la cotización
  $sum = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM cotizacion_items WHERE cotizacion_id=?");
  $sum->execute([$id]);
  $subtotal = (float)$sum->fetchColumn();

  // Trae adicionales y tasa_iva actuales de la cabecera
  $cst = $pdo->prepare("SELECT adicionales, tasa_iva FROM cotizaciones WHERE id=? FOR UPDATE");
  $cst->execute([$id]);
  $cab = $cst->fetch(\PDO::FETCH_ASSOC);
  $adicionales = (float)($cab['adicionales'] ?? 0);
  $tasa        = (float)($cab['tasa_iva']    ?? 16);

  $base      = $subtotal + $adicionales;
  $impuestos = round($base * ($tasa/100), 2);
  $total     = round($subtotal + $adicionales + $impuestos, 2);

  $upd = $pdo->prepare("UPDATE cotizaciones SET subtotal=?, impuestos=?, total=? WHERE id=?");
  $upd->execute([$subtotal, $impuestos, $total, $id]);

  $pdo->commit();

  $fmt = fn($n)=>'$'.number_format((float)$n,2);
  echo json_encode([
    'ok' => true,
    'subtotal'  => $subtotal,
    'impuestos' => $impuestos,
    'total'     => $total,
    'subtotal_fmt'  => $fmt($subtotal),
    'impuestos_fmt' => $fmt($impuestos),
    'total_fmt'     => $fmt($total),
  ]);
} catch (\Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

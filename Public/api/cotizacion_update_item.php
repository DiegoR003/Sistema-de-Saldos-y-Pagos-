<?php
// Public/api/cotizacion_update_item.php
require_once __DIR__ . '/../../App/bd.php';

function pick($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Método no permitido'); }

$id     = (int)pick('id',0);
$grupo  = pick('grupo','');
$opcion = pick('opcion','');

if ($id<=0 || $grupo==='' || $opcion==='') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit;
}

$map = require __DIR__ . '/../../App/precios.php';
if (!isset($map[$grupo]) || !array_key_exists($opcion, $map[$grupo])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Opción inválida']); exit;
}

$precio = (float)$map[$grupo][$opcion];

$pdo = db();
$pdo->beginTransaction();
try {
  // Cabecera bloqueada
  $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id=? FOR UPDATE");
  $st->execute([$id]);
  $c = $st->fetch();
  if (!$c) throw new RuntimeException('Cotización no encontrada');
  if ($c['estado']!=='pendiente') throw new RuntimeException('Solo editable en estado pendiente');

  // Reemplazar SOLO el grupo
  $pdo->prepare("DELETE FROM cotizacion_items WHERE cotizacion_id=? AND grupo=?")->execute([$id,$grupo]);
  $pdo->prepare("INSERT INTO cotizacion_items (cotizacion_id,grupo,opcion,valor) VALUES (?,?,?,?)")
      ->execute([$id,$grupo,$opcion,$precio]);

  // Recalcular totales
  $s = $pdo->prepare("SELECT SUM(valor) FROM cotizacion_items WHERE cotizacion_id=?");
  $s->execute([$id]);
  $subtotal = (float)$s->fetchColumn();

  $adicionales = (float)$c['adicionales'];
  $tasaIva     = (float)$c['tasa_iva'] ?: 16.0;
  $base        = $subtotal + $adicionales;
  $impuestos   = round($base * ($tasaIva/100), 2);
  $total       = round($base + $impuestos, 2);
  $cumple      = $total >= (float)$c['minimo'];

  $up = $pdo->prepare("UPDATE cotizaciones SET subtotal=?, impuestos=?, total=?, cumple_minimo=? WHERE id=?");
  $up->execute([$subtotal,$impuestos,$total,$cumple?1:0,$id]);

  $pdo->commit();
  header('Content-Type: application/json');
  echo json_encode([
    'ok'=>true,
    'subtotal'=>$subtotal, 'impuestos'=>$impuestos, 'total'=>$total,
  ]);
} catch(Throwable $e){
  $pdo->rollBack();
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

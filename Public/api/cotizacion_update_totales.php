<?php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$adi = isset($_POST['adicionales']) ? (float)$_POST['adicionales'] : 0;

if ($id<=0) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'id inválido']); exit; }

try {
  $pdo = db();
  $pdo->beginTransaction();

  // Subtotal actual
  $sum = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM cotizacion_items WHERE cotizacion_id=?");
  $sum->execute([$id]);
  $subtotal = (float)$sum->fetchColumn();

  // Tasa IVA
  $cab = $pdo->prepare("SELECT tasa_iva FROM cotizaciones WHERE id=? FOR UPDATE");
  $cab->execute([$id]);
  $tasa = (float)$cab->fetchColumn();

  $base = $subtotal + $adi;
  $iva  = round($base * ($tasa/100), 2);
  $total= round($base + $iva, 2);

  $upd = $pdo->prepare("UPDATE cotizaciones SET adicionales=?, subtotal=?, impuestos=?, total=? WHERE id=?");
  $upd->execute([$adi,$subtotal,$iva,$total,$id]);

  $pdo->commit();
  echo json_encode([
    'ok'=>true,
    'subtotal'=>$subtotal,'adicionales'=>$adi,'impuestos'=>$iva,'total'=>$total,
    'subtotal_fmt'=>'$'.number_format($subtotal,2),
    'adicionales_fmt'=>'$'.number_format($adi,2),
    'impuestos_fmt'=>'$'.number_format($iva,2),
    'total_fmt'=>'$'.number_format($total,2),
  ]);
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}

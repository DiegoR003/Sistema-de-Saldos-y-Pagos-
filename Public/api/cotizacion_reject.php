<?php
// Public/api/cotizacion_reject.php
require_once __DIR__ . '/../../App/bd.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); exit('Método no permitido');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';

$pdo = db();
try {
  $st = $pdo->prepare("UPDATE cotizaciones SET estado='rechazada' WHERE id=? AND estado='pendiente'");
  $st->execute([$id]);

  header('Location: '.$back.'&ok=1&msg='.rawurlencode('Cotización rechazada'));
  exit;
} catch (Throwable $e) {
  header('Location: '.$back.'&ok=0&err='.rawurlencode($e->getMessage()));
  exit;
}
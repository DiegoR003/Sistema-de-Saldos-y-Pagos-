<?php
declare(strict_types=1);
require_once __DIR__.'/../../App/bd.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = db();
  $st = $pdo->query("SELECT id, rfc, razon_social, descripcion FROM company_rfcs ORDER BY id ASC");
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true, 'rows'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
}

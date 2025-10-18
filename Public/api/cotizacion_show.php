<?php
// Public/api/cotizacion_show.php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../App/bd.php';

function out($arr, int $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Validación básica
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) out(['ok'=>false,'msg'=>'ID inválido'], 400);

  $pdo = db();

  // Cabecera de cotización
  $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id=?");
  $st->execute([$id]);
  $c = $st->fetch();
  if (!$c) out(['ok'=>false,'msg'=>'Cotización no encontrada'], 404);

  // Items
  $it = $pdo->prepare("SELECT grupo, opcion, valor FROM cotizacion_items WHERE cotizacion_id=? ORDER BY id");
  $it->execute([$id]);
  $items = [];
  foreach ($it as $r){
    $items[] = [
      'grupo'  => (string)$r['grupo'],
      'opcion' => (string)$r['opcion'],
      'valor'  => (float)$r['valor'],
    ];
  }

  // Respuesta esperada por el front
  $resp = [
    'ok'          => true,
    'id'          => (int)$c['id'],
    'folio'       => 'COT-'.str_pad((string)$c['id'], 5, '0', STR_PAD_LEFT),
    'empresa'     => (string)$c['empresa'],
    'correo'      => (string)$c['correo'],
    'fecha'       => date('Y-m-d', strtotime((string)$c['creado_en'])),
    'tasa_iva'    => (float)$c['tasa_iva'],
    'subtotal'    => (float)$c['subtotal'],
    'adicionales' => (float)$c['adicionales'],
    'impuestos'   => (float)$c['impuestos'],
    'total'       => (float)$c['total'],
    'estado'      => (string)$c['estado'],
    'periodicidad'=> isset($c['periodicidad']) ? (string)$c['periodicidad'] : null,
    'items'       => $items,
  ];

  out($resp);
} catch (Throwable $e){
  // En DEV puedes loguear $e->getMessage()
  out(['ok'=>false,'msg'=>'Error interno'], 500);
}

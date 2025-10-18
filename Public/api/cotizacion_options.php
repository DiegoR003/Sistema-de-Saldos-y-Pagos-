<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/*
  Catálogo simple (mapea “grupo” => opciones [valor => etiqueta])
  Igual que el cotizador original.
*/
$opts = [
  'cuenta'        => ['1575' => '$1,575 (Obligatorio)'],
  'publicaciones' => ['1181' => '3 veces por semana', '2363' => '6 veces por semana'],
  'campañas'      => ['0' => 'No', '630' => '1', '1260' => '2'],
  'reposteo'      => ['1050' => 'Sí', '0' => 'No'],
  'stories'       => ['0' => 'No', '788' => '3', '1575' => '6'],
  'imprenta'      => ['0' => 'No', '525' => '1 a la vez', '1050' => '2 a la vez'],
  'fotos'         => ['1750' => 'Sí', '0' => 'No', '875' => 'Cada 2 meses'],
  'video'         => ['1925' => 'Sí', '0' => 'No', '963' => 'Cada 2 meses'],
  'ads'           => ['1969' => 'Sí', '0' => 'No'],
  'web'           => ['525' => 'Informativa', '1575' => 'Sistema', '0' => 'No'],
  'mkt'           => ['525' => '1 al mes', '1050' => '2 al mes', '0' => 'No'],
];

$g = $_GET['grupo'] ?? '';
if ($g !== '') {
  if (!isset($opts[$g])) { echo json_encode(['ok'=>false,'msg'=>'grupo inválido']); exit; }
  echo json_encode(['ok'=>true,'grupo'=>$g,'opciones'=>$opts[$g]]);
  exit;
}

echo json_encode(['ok'=>true,'catalogo'=>$opts]);

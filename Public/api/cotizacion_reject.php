<?php
// Public/api/cotizacion_reject.php
require_once __DIR__ . '/../../App/bd.php';
require_once __DIR__ . '/../../App/notificacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); exit('Método no permitido');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=cotizaciones';

$pdo = db();

// ==========================
  //  NOTIFICACIONES
  // ==========================
  $clienteNombre = $cot['empresa'] ?? 'Cliente';
  $clienteCorreo = $cot['correo']  ?? '';
  $folio         = $cot['folio']   ?? ('COT-'.str_pad((string)$id, 5, '0', STR_PAD_LEFT));

  $tituloAdminOp = "Cotización $folio rechazada";
  $cuerpoAdminOp = "La cotización $folio del cliente {$clienteNombre} fue rechazada.";

  // 1) Admin + Operador
  notificar_roles(
      $pdo,
      ['admin','operador'],
      $tituloAdminOp,
      $cuerpoAdminOp,
      'cotizacion',
      $id
  );

  // 2) Cliente
  if (!empty($clienteId) && !empty($clienteCorreo)) {
      $tituloCli = "Tu cotización $folio fue rechazada";
      $cuerpoCli = "Hola {$clienteNombre}, tu cotización $folio fue rechazada. Si tienes dudas, contáctanos para revisarla.";

      notificar_cliente(
          $pdo,
          (int)$clienteId,
          $clienteCorreo,
          $tituloCli,
          $cuerpoCli,
          'cotizacion',
          $id
      );
  }

  back('Cotización rechazada', true);
  
try {
  $st = $pdo->prepare("UPDATE cotizaciones SET estado='rechazada' WHERE id=? AND estado='pendiente'");
  $st->execute([$id]);

  header('Location: '.$back.'&ok=1&msg='.rawurlencode('Cotización rechazada'));
  exit;
} catch (Throwable $e) {
  header('Location: '.$back.'&ok=0&err='.rawurlencode($e->getMessage()));
  exit;
}
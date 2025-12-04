<?php
declare(strict_types=1);

require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/notifications.php';

$pdo = db();

$cotizacionFake = [
    'id'             => 123,
    'folio'          => 'COT-TEST',
    'cliente_nombre' => 'Cliente de Prueba',
    'cliente_id'     => 12,
    'correo'         => 'prueba@example.com',
];

$idNotif = notificar_cotizacion_aprobada($pdo, $cotizacionFake, null);

echo "Notificación creada con ID: " . $idNotif;

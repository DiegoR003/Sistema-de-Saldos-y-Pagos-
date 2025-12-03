<?php
// App/pusher_config.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php'; // si usas Composer

use Pusher\Pusher;

function pusher_client(): Pusher {
    static $pusher = null;
    if ($pusher !== null) return $pusher;

    // OJO: estos datos los sacas del panel de Pusher
    $options = [
        'cluster' => 'tu_cluster',   // ej: 'mt1'
        'useTLS'  => true,
    ];

    $pusher = new Pusher(
        'TU_APP_KEY',   // key
        'TU_APP_SECRET',// secret
        'TU_APP_ID',    // app_id
        $options
    );

    return $pusher;
}

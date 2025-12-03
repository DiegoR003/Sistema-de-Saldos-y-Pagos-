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
        'cluster' => 'us2',   // ej: 'mt1'
        'useTLS'  => true,
    ];

    $pusher = new Pusher(
        '186671ef6532c0880f60',   // key
        '581f3c6fa1faadf02422',// secret
        '2086047',    // app_id
        $options
    );

    return $pusher;
}

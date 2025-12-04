<?php
// App/pusher_config.php



declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Pusher\Pusher;

// Tus constantes...
const PUSHER_APP_ID = '2086047';
const PUSHER_APP_KEY = '186671ef6532c0880f60';
const PUSHER_APP_SECRET = '581f3c6fa1faadf02422';
const PUSHER_APP_CLUSTER = 'us2';

// ✅ AGREGA ESTA LÍNEA DE IF:
if (!function_exists('pusher_client')) {

    function pusher_client(): Pusher
    {
        $options = [
            'cluster' => PUSHER_APP_CLUSTER,
            'useTLS' => true,
        ];
        return new Pusher(
            PUSHER_APP_KEY,
            PUSHER_APP_SECRET,
            PUSHER_APP_ID,
            $options
        );
    }

} // ✅ CIERRA EL IF AQUÍ
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Pusher\Pusher;

if (!function_exists('pusher_client')) {
    function pusher_client(): Pusher
    {
        $appId = env('PUSHER_APP_ID');
        $key = env('PUSHER_APP_KEY');
        $secret = env('PUSHER_APP_SECRET');
        $cluster = env('PUSHER_APP_CLUSTER', 'us2');

        if (!$appId || !$key || !$secret) {
            throw new RuntimeException('Faltan variables de entorno de Pusher');
        }

        return new Pusher($key, $secret, $appId, [
            'cluster' => $cluster,
            'useTLS' => true,
        ]);
    }
}
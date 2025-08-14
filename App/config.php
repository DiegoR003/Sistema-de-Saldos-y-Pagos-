<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL',  '/sistema-pagos/public'); // se ajusta si la carpeta cambia
date_default_timezone_set('America/Mazatlan'); // o tu zona actual

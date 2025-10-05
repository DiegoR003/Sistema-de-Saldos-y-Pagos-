<?php 
if (session_status() === PHP_SESSION_NONE) session_start();

define('BASE_PATH', dirname(__DIR__));
define('BASE_URL',  '/Sistema-de-Saldos-y-Pagos-/Public'); // ajusta si la carpeta cambia
date_default_timezone_set('America/Mazatlan');

?>
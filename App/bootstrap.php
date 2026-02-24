<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->load();
}

function env(string $key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    $v = getenv($key);
    return ($v === false) ? $default : $v;
}
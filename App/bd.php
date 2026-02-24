<?php
declare(strict_types=1);

// App/bd.php
require_once __DIR__ . '/bootstrap.php'; // ✅ carga .env + env()

function db(): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  $DB_HOST = env('DB_HOST', '127.0.0.1');
  $DB_PORT = env('DB_PORT', '3306');
  $DB_NAME = env('DB_NAME', 'sistema_pagos_saldos_banana');
  $DB_USER = env('DB_USER', 'root');
  $DB_PASS = env('DB_PASS', '');

  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

  try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
  } catch (PDOException $e) {
    // ✅ En producción NO muestres el error completo
    if (env('APP_DEBUG', 'false') === 'true') {
      die('Error de conexión: ' . $e->getMessage());
    }
    die('Error de conexión a la base de datos.');
  }
}
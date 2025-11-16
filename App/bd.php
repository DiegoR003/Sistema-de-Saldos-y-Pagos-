<?php
// App/bd.php
require_once __DIR__ . '/config.php';

function db(): PDO {
  static $pdo;
  if ($pdo) return $pdo;

  // Ajustar estos valores si tu MySQL usa otro puerto o credenciales
  $DB_HOST = '127.0.0.1';  // o 'localhost'
  $DB_PORT = '3306';       // puerto de MySQL en WAMP (cambiar en donde se use el sistema)
  $DB_NAME = 'sistema_pagos_saldos_banana';
  $DB_USER = 'root';
  $DB_PASS = '';

  $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
  } catch (PDOException $e) {
    // En producción, loguea; en dev puedes ver el error:
    die('Error de conexión: '.$e->getMessage());
  }
}

?>
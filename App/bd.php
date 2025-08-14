<?php
require_once __DIR__ . '/config.php';

$DB_HOST = 'localhost:8080';
$DB_NAME = 'sistema_pagos_saldos';
$DB_USER = 'root';
$DB_PASS = ''; //  contraseña de WAMPP si aplica

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  die('Error de conexión: ' . $e->getMessage());
}

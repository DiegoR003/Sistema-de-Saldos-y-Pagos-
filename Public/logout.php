<?php
// Public/logout.php
declare(strict_types=1);

require_once __DIR__ . '/../App/auth.php';

// Destruir sesión
$_SESSION = [];
session_destroy();

// Redirigir al login
header('Location: /Sistema-de-Saldos-y-Pagos-/Public/login.php');
exit;
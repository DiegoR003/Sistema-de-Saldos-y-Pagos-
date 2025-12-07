<?php
// Public/index.php
declare(strict_types=1);

// 1. Cargar dependencias
require_once __DIR__ . '/../App/auth.php';
require_once __DIR__ . '/../App/bd.php'; // Necesario para consultar el rol

// Configuración de errores
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 2. Módulo solicitado
$m = $_GET['m'] ?? '';

// Caso especial: Login
if ($m === 'login') {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/login.php');
    exit;
}

// 3. Verificar Sesión
require_login(); // Si no está logueado, auth.php lo manda al login

// 4. DETECCIÓN DE ROL (CORREGIDA)
$currentUser = current_user();
$userId = (int)($currentUser['id'] ?? 0);
$rolUsuario = 'guest';

if ($userId > 0) {
    try {
        $pdo = db();
        // Buscamos el nombre del rol en la BD usando el ID del usuario
        $st = $pdo->prepare("
            SELECT r.nombre 
            FROM roles r
            JOIN usuario_rol ur ON ur.rol_id = r.id
            WHERE ur.usuario_id = ?
            LIMIT 1
        ");
        $st->execute([$userId]);
        $nombreRol = $st->fetchColumn();
        
        if ($nombreRol) {
            $rolUsuario = strtolower(trim($nombreRol));
        }
    } catch (Exception $e) {
        // Si falla la BD, se queda como guest
    }
}

// 5. LÓGICA DE REDIRECCIÓN Y PERMISOS
if ($rolUsuario === 'cliente') {
    // === ZONA CLIENTES ===
    $modulosPermitidos = ['cliente_home', 'cliente_pagos', 'usuarios', 'logout'];
    $moduloDefault     = 'cliente_home'; 

    // Forzar home de cliente si intenta ir a inicio o no pide nada
    if (empty($m) || $m === 'inicio' || !in_array($m, $modulosPermitidos)) {
        $m = 'cliente_home';
    }

} else {
    // === ZONA ADMIN / OPERADOR ===
    $modulosPermitidos = [
        'inicio', 'cobrar', 'cotizaciones', 'clientes', 
        'cobros', 'corte_diario', 'pagos_atrasados', 'usuarios', 'logout'
    ];
    $moduloDefault = 'inicio';
    
    if (empty($m)) {
        $m = $moduloDefault;
    }
}

// 6. ENRUTADOR
$mod = in_array($m, $modulosPermitidos) ? $m : $moduloDefault;

// 7. CARGAR VISTAS
require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/sidebar.php';

$modulePath = __DIR__ . '/../Modules/' . $mod . '.php';

if (file_exists($modulePath)) {
    include $modulePath;
} else {
    // Fallback simple
    echo '<div class="container-fluid p-4 alert alert-warning">Módulo no encontrado: ' . htmlspecialchars($mod) . '</div>';
}

require_once __DIR__ . '/../Includes/footer.php';
?>
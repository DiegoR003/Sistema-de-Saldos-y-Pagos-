<?php
declare(strict_types=1);

require_once __DIR__ . '/../App/auth.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Obtener el módulo solicitado
$m = $_GET['m'] ?? 'inicio';

// Si pide login, redirigir a login.php
if ($m === 'login') {
    header('Location: /Sistema-de-Saldos-y-Pagos-/Public/login.php');
    exit;
}

// Para cualquier otro módulo, requerir login
require_login();

// Incluir layout
require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/sidebar.php';

// Módulos permitidos
$allowed = ['inicio','cobrar','cotizaciones','clientes','cobros','corte_diario','pagos_atrasados','usuarios'];
$mod = in_array($m, $allowed, true) ? $m : 'inicio';

$modulePath = __DIR__ . '/../Modules/' . $mod . '.php';
if (file_exists($modulePath)) {
    include $modulePath;
} else {
    echo '<div class="container-fluid"><div class="alert alert-warning">Módulo no encontrado: '
       . htmlspecialchars($mod) . '</div></div>';
}

require_once __DIR__ . '/../Includes/footer.php';
?>
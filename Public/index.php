<?php
require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/sidebar.php';
require_once __DIR__ . '/../Includes/footer.php';

$allowed = ['inicio','cobrar','servicios','clientes','cobros','corte_diario','pagos_atrasados','usuarios'];
$m   = $_GET['m'] ?? 'inicio';
$mod = in_array($m, $allowed, true) ? $m : 'inicio';

$modulePath = __DIR__ . '/../Modules/' . $mod . '.php';
if (file_exists($modulePath)) {
  include $modulePath;
} else {
  echo '<div class="container-fluid"><div class="alert alert-warning">Módulo no encontrado: '
     . htmlspecialchars($mod) . '</div></div>';
}



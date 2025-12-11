<?php
// Public/test_db.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../App/bd.php';
require_once __DIR__ . '/../App/notifications.php';

echo "<h1>🛠️ Diagnóstico de Base de Datos y Notificaciones</h1>";

try {
    $pdo = db();
    
    // 1. Verificar Conexión y Base de Datos Actual
    $stmt = $pdo->query("SELECT DATABASE()");
    $dbName = $stmt->fetchColumn();
    echo "<p><strong>Base de datos conectada:</strong> <span style='color:blue'>$dbName</span></p>";

    // 2. Verificar último ID existente
    $stmt = $pdo->query("SELECT MAX(id) FROM notificaciones");
    $lastId = $stmt->fetchColumn();
    echo "<p><strong>Último ID antes de insertar:</strong> $lastId</p>";

    // 3. Intentar Insertar MANUALMENTE (Simulacro)
    echo "<p>⏳ Intentando insertar notificación de prueba...</p>";
    
    $datosPrueba = [
        'tipo'       => 'interna',
        'canal'      => 'sistema',
        'titulo'     => 'PRUEBA DE DIAGNÓSTICO ' . date('H:i:s'),
        'cuerpo'     => 'Si lees esto, la base de datos funciona correctamente.',
        'usuario_id' => 1, // Asumiendo que el ID 1 existe (si no, pon uno real)
        'estado'     => 'pendiente'
    ];

    // Llamamos a la función
    $nuevoId = enviar_notificacion($pdo, $datosPrueba, true);

    if ($nuevoId > 0) {
        echo "<h2 style='color:green'>✅ ÉXITO: Inserción reportada con ID $nuevoId</h2>";
        echo "<p>Por favor, <strong>ve a tu phpMyAdmin AHORA MISMO</strong> y busca el ID <strong>$nuevoId</strong> en la tabla <code>notificaciones</code>.</p>";
        echo "<ul>";
        echo "<li>Si lo ves ahí: El problema está en los archivos de `pagos` o `cotizaciones` (hacen rollback).</li>";
        echo "<li>Si NO lo ves ahí: Hay un problema de caché, transacción fantasma o conexión cruzada.</li>";
        echo "</ul>";
    } else {
        echo "<h2 style='color:red'>❌ ERROR: La función devolvió 0.</h2>";
        echo "<p>Revisa el archivo <code>App/error_log_notif.txt</code> si lo creaste, o revisa los logs de PHP.</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>💀 EXCEPCIÓN CRÍTICA</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
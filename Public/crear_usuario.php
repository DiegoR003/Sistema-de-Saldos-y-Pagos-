<?php
require_once __DIR__ . '/../App/bd.php';

$pdo = db();

$nombre = 'Leonel Pimentel';
$correo = 'admin@bananagap.com';
$password = 'admin123';

// Generar hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Creando usuario...</h3>";
echo "Nombre: $nombre<br>";
echo "Correo: $correo<br>";
echo "Password: $password<br>";
echo "Hash: $hash<br><br>";

try {
    // Verificar si ya existe
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $st->execute([$correo]);
    if ($st->fetch()) {
        echo "<p style='color:orange'>El usuario ya existe. Actualizando...</p>";
        
        $st = $pdo->prepare("UPDATE usuarios SET pass_hash = ?, activo = 1 WHERE correo = ?");
        $st->execute([$hash, $correo]);
        echo "<p style='color:green'>✓ Contraseña actualizada</p>";
    } else {
        echo "<p style='color:blue'>Creando nuevo usuario...</p>";
        
        $st = $pdo->prepare("INSERT INTO usuarios (nombre, correo, pass_hash, activo) VALUES (?, ?, ?, 1)");
        $st->execute([$nombre, $correo, $hash]);
        echo "<p style='color:green'>✓ Usuario creado con ID: " . $pdo->lastInsertId() . "</p>";
    }
    
    echo "<hr>";
    echo "<h4>Credenciales para login:</h4>";
    echo "<strong>Correo:</strong> $correo<br>";
    echo "<strong>Contraseña:</strong> $password<br>";
    
    echo "<hr>";
    echo "<a href='login.php'>Ir al Login</a>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
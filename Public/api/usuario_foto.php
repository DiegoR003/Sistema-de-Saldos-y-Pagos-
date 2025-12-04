<?php
// Public/api/usuario_foto.php
declare(strict_types=1);
require_once __DIR__ . '/../../App/bd.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

// Intentamos obtener el ID del usuario logueado
// (Ya que el modal de foto en tu código 'usuarios.php' no envía un input hidden con ID,
//  asumimos que estás editando TU propia foto).
$userId = 0;
if (!empty($_SESSION['usuario_id'])) $userId = (int)$_SESSION['usuario_id'];
elseif (!empty($_SESSION['user']['id'])) $userId = (int)$_SESSION['user']['id'];

if ($userId <= 0) {
    header("Location: $back&err=No+autenticado");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $file = $_FILES['foto'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: $back&err=Error+al+subir+archivo");
        exit;
    }

    // Validar tipo de imagen
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        header("Location: $back&err=Formato+no+valido+(solo+JPG,PNG,WEBP)");
        exit;
    }

    // Definir directorio de destino (crearlo si no existe)
    // __DIR__ es Public/api, así que subimos un nivel a Public
    $uploadDir = __DIR__ . '/../../Storage/avatars';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generar nombre único para evitar conflictos y caché
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Ruta relativa para guardar en BD (accesible desde el navegador)
        $webPath = './Storage/avatars/' . $fileName;

        $pdo = db();
        $upd = $pdo->prepare("UPDATE usuarios SET foto_url = ? WHERE id = ?");
        $upd->execute([$webPath, $userId]);

        // Actualizar sesión para que la foto cambie de inmediato
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['foto_url'] = $webPath;
        }

        header("Location: $back&ok=Foto+actualizada");
    } else {
        header("Location: $back&err=No+se+pudo+guardar+la+imagen+en+disco");
    }
} else {
    header("Location: $back");
}
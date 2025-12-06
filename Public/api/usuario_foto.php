<?php
// Public/api/usuario_foto.php
declare(strict_types=1);

require_once __DIR__ . '/../../App/bd.php';

// Intentamos cargar tu sistema de autenticación para ser más precisos
$authFile = __DIR__ . '/../../App/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$back = '/Sistema-de-Saldos-y-Pagos-/Public/index.php?m=usuarios';

// 1. OBTENER ID DEL USUARIO (Lógica Robusta)
$userId = 0;

// Intento A: Usar función current_user() si existe
if (function_exists('current_user')) {
    $u = current_user();
    if (!empty($u['id'])) $userId = (int)$u['id'];
}

// Intento B: Buscar en sesión si falló lo anterior
if ($userId === 0) {
    if (!empty($_SESSION['usuario_id'])) $userId = (int)$_SESSION['usuario_id'];
    elseif (!empty($_SESSION['user_id'])) $userId = (int)$_SESSION['user_id'];
    elseif (!empty($_SESSION['user']['id'])) $userId = (int)$_SESSION['user']['id'];
}

if ($userId <= 0) {
    // Si sigue siendo 0, es que de verdad no hay sesión
    header("Location: $back&err=No+autenticado+(Sesión+perdida)");
    exit;
}

// 2. PROCESAR LA IMAGEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $file = $_FILES['foto'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: $back&err=Error+al+subir+archivo");
        exit;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!in_array($file['type'], $allowed)) {
        header("Location: $back&err=Formato+inválido+(usa+JPG+o+PNG)");
        exit;
    }

    // Ruta física: C:/.../Public/Storage/avatars/
    $uploadDir = __DIR__ . '/../../Public/Storage/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Nombre único
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Ruta Web: ./Storage/avatars/foto.jpg
        $webPath = './Storage/avatars/' . $fileName;

        $pdo = db();
        $upd = $pdo->prepare("UPDATE usuarios SET foto_url = ? WHERE id = ?");
        $upd->execute([$webPath, $userId]);

        // 3. ACTUALIZAR SESIÓN (Para que se vea ya mismo)
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['foto_url'] = $webPath;
        }
        // Forzamos actualización de otras variables posibles
        $_SESSION['foto_url'] = $webPath; 

        header("Location: $back&ok=Foto+actualizada");
    } else {
        header("Location: $back&err=Error+al+mover+imagen");
    }
} else {
    header("Location: $back");
}
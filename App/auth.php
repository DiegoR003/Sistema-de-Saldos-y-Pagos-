<?php
// App/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Devuelve array con los datos del usuario logueado o null
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'      => (int)$_SESSION['user_id'],
        'nombre'  => $_SESSION['user_nombre'] ?? '',
        'correo'  => $_SESSION['user_correo'] ?? '',
        'roles'   => $_SESSION['user_roles'] ?? [],  // array de ids de rol
    ];
}

// ¿Tiene el usuario logueado un rol concreto?
function user_has_role(int $rolId): bool {
    $u = current_user();
    if (!$u) return false;
    return in_array($rolId, $u['roles'], true);
}

// Obliga a estar logueado; si no, manda al login
function require_login(): void {
    if (!current_user()) {
        header('Location: /Sistema-de-Saldos-y-Pagos-/Public/login.php');
        exit;
    }
}

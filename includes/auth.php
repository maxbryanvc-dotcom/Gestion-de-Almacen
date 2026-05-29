<?php
// ============================================================
// SISTEMA DE AUTENTICACIÓN Y CONTROL DE ACCESO
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// Redirigir a login si no hay sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Regenerar ID cada 30 min para prevenir fijación de sesión
if (!isset($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ============================================================
// CONTROL DE ROLES
// ============================================================

function verificarRol(array $rolesPermitidos): void {
    if (!in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
        http_response_code(403);
        echo '<div class="container mt-5">
                <div class="alert alert-danger text-center">
                    <i class="fa-solid fa-ban fa-2x mb-2"></i><br>
                    <strong>Acceso denegado.</strong><br>
                    No tienes permisos para realizar esta acción.
                </div>
              </div>';
        require_once __DIR__ . '/footer.php';
        exit();
    }
}

function tieneRol(array $roles): bool {
    return in_array($_SESSION['rol'] ?? '', $roles, true);
}

function esAdmin(): bool {
    return ($_SESSION['rol'] ?? '') === 'admin';
}

<?php
// ============================================================
// SISTEMA DE AUTENTICACIÓN, CONTROL DE ACCESO Y SEGURIDAD
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // true en producción con HTTPS
        'httponly' => true,    // JS no puede leer la cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Headers de seguridad HTTP ────────────────────────────────
if (!headers_sent()) {
    // Evita que el navegador muestre el sistema dentro de iframes (clickjacking)
    header('X-Frame-Options: SAMEORIGIN');
    // Evita que el navegador detecte tipos de contenido incorrectos
    header('X-Content-Type-Options: nosniff');
    // Activa la protección XSS del navegador
    header('X-XSS-Protection: 1; mode=block');
    // No enviar la URL de referencia a otros sitios
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Deshabilitar caché para páginas protegidas (evita ver datos con el botón "atrás")
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

require_once __DIR__ . '/../config/app.php';

// Redirigir a login si no hay sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// ── Timeout por inactividad (60 minutos) ─────────────────────
$timeoutInactividad = 60 * 60; // 60 minutos
if (isset($_SESSION['_ultimo_acceso'])) {
    if (time() - $_SESSION['_ultimo_acceso'] > $timeoutInactividad) {
        // Sesión expirada por inactividad
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/login.php?msg=timeout");
        exit();
    }
}
$_SESSION['_ultimo_acceso'] = time();

// ── Regenerar ID cada 30 min para prevenir fijación de sesión ─
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

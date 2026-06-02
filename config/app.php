<?php
// ============================================================
// CONFIGURACIÓN CENTRAL DEL SISTEMA
// ============================================================

// URL base del proyecto (sin barra final)
// Cambiar si el proyecto se llama diferente en XAMPP
define('BASE_URL', '/Gestion-de-almacen');


define('APP_NAME',    'ERP Almacén');
define('APP_VERSION', '2.0');
define('APP_EMPRESA', 'Empresa Eléctrica & Técnica S.A.C.');

// Umbrales de stock
define('STOCK_CRITICO', 5);
define('STOCK_BAJO',   10);

// Duración del código de permiso temporal (minutos)
define('PERMISO_MINUTOS', 10);

// Zona horaria
date_default_timezone_set('America/Lima');

// ============================================================
// HELPERS DE SEGURIDAD
// ============================================================

/** Genera token CSRF y lo guarda en sesión */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verifica token CSRF; termina si es inválido */
function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token de seguridad inválido. Recarga la página.');
    }
}

/** Registra auditoría de una acción (silencioso si la tabla no existe) */
function audit_log(mysqli $conn, string $accion, string $descripcion = ''): void {
    // Verificar que la tabla existe antes de insertar
    $check = $conn->query("SHOW TABLES LIKE 'audit_log'");
    if (!$check || $check->num_rows === 0) return;

    $usuario = $_SESSION['usuario'] ?? 'desconocido';
    $rol     = $_SESSION['rol']     ?? 'desconocido';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $conn->prepare(
        "INSERT INTO audit_log (usuario, rol, accion, descripcion, ip)
         VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('sssss', $usuario, $rol, $accion, $descripcion, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/** Etiqueta visual del rol */
function rol_badge(string $rol): string {
    return match($rol) {
        'admin'    => '<span class="badge bg-danger">Administrador</span>',
        'almacen'  => '<span class="badge bg-primary">Almacenero</span>',
        'tecnico'  => '<span class="badge bg-success">Técnico</span>',
        default    => '<span class="badge bg-secondary">' . htmlspecialchars($rol) . '</span>',
    };
}

/** Estado del stock como badge */
function stock_badge(int $stock): string {
    if ($stock <= 0)              return '<span class="badge bg-danger">Agotado</span>';
    if ($stock <= STOCK_CRITICO)  return '<span class="badge bg-warning text-dark">Crítico</span>';
    if ($stock <= STOCK_BAJO)     return '<span class="badge bg-info text-dark">Bajo</span>';
    return '<span class="badge bg-success">Disponible</span>';
}

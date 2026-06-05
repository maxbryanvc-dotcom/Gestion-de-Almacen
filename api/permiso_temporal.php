<?php
// ============================================================
// API — SISTEMA DE PERMISOS TEMPORALES
// Permite al admin generar códigos de 6 dígitos que el
// almacenero ingresa para realizar acciones restringidas.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json');

// Solo usuarios autenticados
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit();
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ============================================================
// GENERAR CÓDIGO (solo admin)
// ============================================================
if ($accion === 'generar') {

    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['ok' => false, 'msg' => 'Solo el administrador puede generar códigos']);
        exit();
    }

    $descripcion = trim($_POST['descripcion'] ?? 'Acción especial');
    $recurso_id  = intval($_POST['recurso_id'] ?? 0);
    $solicitante = trim($_POST['solicitante']  ?? '');

    // Generar código aleatorio de 6 dígitos
    $codigo  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expira  = date('Y-m-d H:i:s', strtotime('+' . PERMISO_MINUTOS . ' minutes'));

    $stmt = $conn->prepare(
        "INSERT INTO permisos_temp (codigo, solicitante, accion, recurso_id, expira_en)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssis', $codigo, $solicitante, $descripcion, $recurso_id, $expira);

    if ($stmt->execute()) {
        audit_log($conn, 'GENERAR_PERMISO', "Código para: $solicitante — $descripcion");
        echo json_encode([
            'ok'     => true,
            'codigo' => $codigo,
            'expira' => $expira,
            'minutos'=> PERMISO_MINUTOS,
        ]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Error al generar código']);
    }
    $stmt->close();
    exit();
}

// ============================================================
// VERIFICAR CÓDIGO (almacenero lo ingresa)
// ============================================================
if ($accion === 'verificar') {

    $codigo      = trim($_POST['codigo']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (strlen($codigo) !== 6 || !ctype_digit($codigo)) {
        echo json_encode(['ok' => false, 'msg' => 'Código inválido']);
        exit();
    }

    $ahora = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "SELECT * FROM permisos_temp
         WHERE codigo = ? AND usado = 0 AND expira_en > ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $codigo, $ahora);
    $stmt->execute();
    $permiso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$permiso) {
        echo json_encode(['ok' => false, 'msg' => 'Código incorrecto o expirado']);
        exit();
    }

    // Marcar como usado
    $stmt2 = $conn->prepare("UPDATE permisos_temp SET usado=1 WHERE id=?");
    $stmt2->bind_param('i', $permiso['id']);
    $stmt2->execute();
    $stmt2->close();

    // Guardar en sesión que tiene permiso temporal
    $_SESSION['permiso_temporal'] = [
        'accion'     => $permiso['accion'],
        'recurso_id' => $permiso['recurso_id'],
        'expira'     => time() + 300, // 5 min adicionales de gracia en sesión
    ];

    audit_log($conn, 'USAR_PERMISO', "Código verificado por {$_SESSION['usuario']}: {$permiso['accion']}");

    echo json_encode(['ok' => true, 'accion' => $permiso['accion'], 'recurso_id' => $permiso['recurso_id']]);
    exit();
}

echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);

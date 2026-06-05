<?php
// ============================================================
// API — Solicitudes de permiso del almacenero al admin
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autenticado']);
    exit();
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ── CREAR SOLICITUD (almacenero pide código) ──────────────────
if ($accion === 'crear') {
    $solicitante = $_SESSION['usuario'];
    $tipo        = trim($_POST['tipo']       ?? 'Accion especial');
    $recurso_id  = intval($_POST['recurso_id'] ?? 0) ?: null;

    // Cancelar solicitudes pendientes anteriores del mismo usuario
    $conn->prepare("UPDATE permisos_solicitudes SET estado='atendido'
                    WHERE solicitante=? AND estado='pendiente'")
         ->bind_param('s', $solicitante) && true;
    $s0 = $conn->prepare("UPDATE permisos_solicitudes SET estado='atendido' WHERE solicitante=? AND estado='pendiente'");
    $s0->bind_param('s', $solicitante); $s0->execute(); $s0->close();

    // Crear nueva solicitud
    $stmt = $conn->prepare(
        "INSERT INTO permisos_solicitudes (solicitante, accion, recurso_id) VALUES (?,?,?)"
    );
    $stmt->bind_param('ssi', $solicitante, $tipo, $recurso_id);

    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();
        echo json_encode(['ok'=>true, 'solicitud_id'=>$id]);
    } else {
        echo json_encode(['ok'=>false, 'msg'=>'Error al crear solicitud']);
    }
    exit();
}

// ── CONSULTAR PENDIENTES (admin verifica si hay solicitudes) ──
if ($accion === 'pendientes') {
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        echo json_encode(['pendientes'=>[]]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, solicitante, accion, recurso_id,
               created_at,
               TIMESTAMPDIFF(SECOND, created_at, NOW()) AS segundos
        FROM permisos_solicitudes
        WHERE estado = 'pendiente'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['pendientes' => $rows]);
    exit();
}

// ── MARCAR COMO ATENDIDA ──────────────────────────────────────
if ($accion === 'atender') {
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE permisos_solicitudes SET estado='atendido' WHERE id=?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    echo json_encode(['ok'=>true]);
    exit();
}

echo json_encode(['ok'=>false, 'msg'=>'Accion no valida']);

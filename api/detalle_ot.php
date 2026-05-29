<?php
// ============================================================
// API — Materiales de una OT   GET ?id=X
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401); echo json_encode([]); exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode([]); exit(); }

$stmt = $conn->prepare("
    SELECT d.cantidad, m.nombre, m.codigo, m.unidad
    FROM detalle_ot d
    JOIN materiales m ON m.id = d.material_id
    WHERE d.ot_id = ?
    ORDER BY m.nombre ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($rows);

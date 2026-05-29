<?php
// ============================================================
// API — Búsqueda de materiales por nombre (AJAX autocomplete)
// GET ?q=texto   →   JSON [{id, nombre, codigo, unidad, stock}]
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

// Solo usuarios autenticados
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 1) {
    echo json_encode([]);
    exit();
}

$like = '%' . $q . '%';

$stmt = $conn->prepare(
    "SELECT id, nombre, codigo, unidad, stock
     FROM materiales
     WHERE activo = 1
       AND (nombre LIKE ? OR codigo LIKE ?)
     ORDER BY nombre ASC
     LIMIT 10"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$materiales = [];
while ($row = $result->fetch_assoc()) {
    $materiales[] = [
        'id'     => (int)$row['id'],
        'nombre' => $row['nombre'],
        'codigo' => $row['codigo'] ?? '',
        'unidad' => $row['unidad'],
        'stock'  => (int)$row['stock'],
    ];
}

$stmt->close();
echo json_encode($materiales);

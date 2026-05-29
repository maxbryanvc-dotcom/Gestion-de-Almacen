<?php
// ============================================================
// ELIMINAR MATERIAL — soft delete (preserva historial)
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Marcamos como inactivo en lugar de borrar físicamente
    // para no romper claves foráneas con entradas/salidas/requerimientos
    $stmt = $conn->prepare("UPDATE materiales SET activo = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    audit_log($conn, 'ELIMINAR_MATERIAL', "ID desactivado: $id");
    header("Location: " . BASE_URL . "/pages/materiales.php?msg=eliminado");
} else {
    header("Location: " . BASE_URL . "/pages/materiales.php");
}
exit();

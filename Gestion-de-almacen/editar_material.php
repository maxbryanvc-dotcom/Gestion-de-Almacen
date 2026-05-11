<?php
include("conexion.php");

// 🔥 validar si existe ID
if (!isset($_GET['id'])) {
    echo "❌ ID no especificado";
    exit();
}

$id = $_GET['id'];

// 🔥 validar que sea número
if (!is_numeric($id)) {
    echo "❌ ID inválido";
    exit();
}

$sql = "SELECT * FROM materiales WHERE id=$id";
$resultado = $conn->query($sql);

if ($resultado->num_rows == 0) {
    echo "❌ Material no encontrado";
    exit();
}

$fila = $resultado->fetch_assoc();
?>
<?php
include("conexion.php");
session_start();

// validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// validar ID
if (!isset($_GET['id'])) {
    echo "❌ ID no especificado";
    exit();
}

$id = $_GET['id'];

if (!is_numeric($id)) {
    echo "❌ ID inválido";
    exit();
}

// eliminar
$conn->query("DELETE FROM materiales WHERE id=$id");

header("Location: materiales.php");
exit();
?>
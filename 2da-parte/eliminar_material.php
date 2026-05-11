<?php
include("conexion.php");

//VALIDAR ID
if (!isset($_GET['id'])) {
    header("Location: materiales.php");
    exit();
}

$id = $_GET['id'];

//VERIFICAR QUE EXISTE
$verificar = $conn->query("SELECT * FROM materiales WHERE id=$id");

if ($verificar->num_rows == 0) {
    header("Location: materiales.php");
    exit();
}

//ELIMINAR
$sql = "DELETE FROM materiales WHERE id=$id";

if ($conn->query($sql)) {
    header("Location: materiales.php?msg=eliminado");
    exit();
} else {
    echo "<div class='alert alert-danger'>❌ Error al eliminar</div>";
}
?>
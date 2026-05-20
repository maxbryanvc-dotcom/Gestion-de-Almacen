<?php
include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibir datos del formulario
    $id = intval($_POST['id']);
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $stock = $_POST['stock'];

    // Consulta de actualización
    $sql = "UPDATE materiales SET 
            codigo = '$codigo', 
            nombre = '$nombre', 
            unidad = '$unidad', 
            stock = '$stock' 
            WHERE id = $id";

    if ($conn->query($sql)) {
        // Redirigir al listado principal si todo sale bien
        header("Location: index.php?actualizado=1");
        exit();
    } else {
        echo "❌ Error al actualizar en la base de datos: " . $conn->error;
    }
} else {
    echo "⚠️ Acceso no permitido.";
}
?>
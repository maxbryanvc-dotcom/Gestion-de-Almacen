<?php
include("conexion.php");

//  Verificar si el formulario fue enviado
if ($_POST) {

    //  Capturar datos del formulario
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $stock = $_POST['stock'];

    //  VALIDACIÓN NUEVA (AGREGADO)
    // Verifica si ya existe un material con el mismo código
    $verificar = $conn->query("SELECT * FROM materiales WHERE codigo='$codigo'");

    if ($verificar->num_rows > 0) {
        //  Si ya existe, muestra mensaje y detiene ejecución
        echo "<p style='color:red;'>❌ El código ya existe</p>";
    } else {

        //  INSERTAR MATERIAL (SOLO SI NO EXISTE)
        $sql = "INSERT INTO materiales (codigo, nombre, unidad, stock)
                VALUES ('$codigo', '$nombre', '$unidad', '$stock')";

        $conn->query($sql);

        //  REDIRECCIÓN (AGREGADO exit PARA EVITAR ERRORES)
        header("Location: materiales.php");
        exit();
    }
}
?>

<h2>Agregar Material</h2>

<form method="POST">
    Código: <input type="text" name="codigo"><br>
    Nombre: <input type="text" name="nombre"><br>
    Unidad: <input type="text" name="unidad"><br>
    Stock: <input type="number" name="stock"><br>

    <button type="submit">Guardar</button>
</form>
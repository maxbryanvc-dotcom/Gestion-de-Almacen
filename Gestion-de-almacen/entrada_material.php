<?php
include("conexion.php");

// Obtener materiales
$materiales = $conn->query("SELECT * FROM materiales");

if ($_POST) {
    $material_id = $_POST['material_id'];
    $cantidad = $_POST['cantidad'];
    $fecha = date("Y-m-d");
    $fecha_hora = date("Y-m-d H:i:s");

    // Guardar entrada
    $conn->query("INSERT INTO entradas (material_id, cantidad, fecha)
                  VALUES ($material_id, $cantidad, '$fecha')");

    // ACTUALIZAR STOCK 
    $conn->query("UPDATE materiales 
                  SET stock = stock + $cantidad 
                  WHERE id = $material_id");

    // 🔥 GUARDAR EN HISTORIAL (NUEVO)
    $conn->query("INSERT INTO movimientos (tipo, material_id, cantidad, tecnico, fecha)
                  VALUES ('entrada', $material_id, $cantidad, 'Almacen', '$fecha_hora')");

    header("Location: materiales.php");
}
?>

<h2>Entrada de Material</h2>

<form method="POST">
    Material:
    <select name="material_id">
        <?php while($m = $materiales->fetch_assoc()) { ?>
            <option value="<?php echo $m['id']; ?>">
                <?php echo $m['nombre']; ?>
            </option>
        <?php } ?>
    </select><br>

    Cantidad: <input type="number" name="cantidad"><br>

    <button type="submit">Registrar Entrada</button>
</form>
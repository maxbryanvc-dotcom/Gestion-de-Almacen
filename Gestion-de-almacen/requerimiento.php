<?php
include("conexion.php");

// Obtener materiales
$materiales = $conn->query("SELECT * FROM materiales");

if ($_POST) {
    $fecha = date("Y-m-d");
    $codigo = "REQ-" . rand(100,999);

    // Crear requerimiento
    $conn->query("INSERT INTO requerimientos (codigo_req, fecha, estado)
                  VALUES ('$codigo', '$fecha', 'Pendiente')");

    $req_id = $conn->insert_id;

    // Guardar detalle
    foreach ($_POST['material_id'] as $index => $material_id) {
        $cantidad = $_POST['cantidad'][$index];

        if ($cantidad > 0) {
            $conn->query("INSERT INTO detalle_requerimiento 
                (requerimiento_id, material_id, cantidad)
                VALUES ($req_id, $material_id, $cantidad)");
        }
    }

    echo "<h3>Requerimiento generado correctamente</h3>";
    echo "<br><a href='generar_pdf.php?id=$req_id' target='_blank'>Descargar PDF</a>";
}
?>

<h2>Generar Requerimiento</h2>

<form method="POST">
<table border="1">
<tr>
    <th>Material</th>
    <th>Stock</th>
    <th>Cantidad a pedir</th>
</tr>

<?php while($m = $materiales->fetch_assoc()) { ?>
<tr>
    <td><?php echo $m['nombre']; ?></td>
    <td><?php echo $m['stock']; ?></td>

    <td>
        <input type="hidden" name="material_id[]" value="<?php echo $m['id']; ?>">
        <input type="number" name="cantidad[]" min="0">
    </td>
</tr>
<?php } ?>

</table>

<br>
<button type="submit">Generar Requerimiento</button>
</form>
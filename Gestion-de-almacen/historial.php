<?php
include("conexion.php");

$sql = "SELECT m.*, mat.nombre 
        FROM movimientos m
        JOIN materiales mat ON m.material_id = mat.id
        ORDER BY m.fecha DESC";

$resultado = $conn->query($sql);
?>

<h2>Historial de Movimientos</h2>

<table border="1">
<tr>
    <th>Tipo</th>
    <th>Material</th>
    <th>Cantidad</th>
    <th>Técnico</th>
    <th>Fecha</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()) { ?>
<tr>
    <td><?php echo $fila['tipo']; ?></td>
    <td><?php echo $fila['nombre']; ?></td>
    <td><?php echo $fila['cantidad']; ?></td>
    <td><?php echo $fila['tecnico']; ?></td>
    <td><?php echo $fila['fecha']; ?></td>
</tr>
<?php } ?>

</table>
<?php
session_start();

require_once(__DIR__ . "/conexion.php");
require_once(__DIR__ . "/layout.php");

// CONSULTA
$sql = "SELECT r.*, t.nombre as tecnico
FROM requerimientos r
LEFT JOIN tecnicos t ON r.tecnico_id = t.id
ORDER BY r.id DESC";

$resultado = $conn->query($sql);
?>

<h2 class="mb-4">Historial de Requerimientos</h2>

<table class="table table-bordered">

    <tr>
        <th>ID</th>
        <th>Técnico</th>
        <th>Fecha</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>

<?php while($row = $resultado->fetch_assoc()) { ?>

<tr>

    <td><?php echo $row['id']; ?></td>

    <td>
        <?php echo $row['tecnico']; ?>
    </td>

    <td>
        <?php echo $row['fecha']; ?>
    </td>

    <td>
        <?php echo $row['estado']; ?>
    </td>

    <td>

        <a href="generar_word.php?id=<?php echo $row['id']; ?>"
           class="btn btn-primary btn-sm">
           Descargar Word
        </a>

    </td>

</tr>

<?php } ?>

</table>

<?php include("footer.php"); ?>
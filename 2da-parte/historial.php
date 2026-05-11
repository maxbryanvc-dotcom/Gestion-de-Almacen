<?php
session_start();
include("conexion.php");
include("layout.php");

// 🔴 CONSULTA COMPLETA
$sql = "SELECT r.*, t.nombre as tecnico 
        FROM requerimientos r
        LEFT JOIN tecnicos t ON r.tecnico_id = t.id
        ORDER BY r.id DESC";

$resultado = $conn->query($sql);
?>

<h2 class="mb-4">📊 Historial de Requerimientos</h2>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Técnico</th>
            <th>Estado</th>
            <th>Aprobado por</th>
            <th>Acciones</th>
        </tr>
    </thead>

    <tbody>
        <?php while($fila = $resultado->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $fila['id']; ?></td>
            <td><?php echo $fila['fecha']; ?></td>
            <td><?php echo $fila['tecnico']; ?></td>
            <td><?php echo $fila['estado']; ?></td>
            <td><?php echo $fila['aprobado_por']; ?></td>

            <td>
                <!-- 🔥 BOTÓN PDF -->
                <a href="generar_pdf.php?id=<?php echo $fila['id']; ?>" 
                   class="btn btn-danger btn-sm">
                   📄 PDF
                </a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php include("footer.php"); ?>
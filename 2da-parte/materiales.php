<?php
include("conexion.php");
include("layout.php");

//CONSULTA
$sql = "SELECT * FROM materiales";
$resultado = $conn->query($sql);
?>

<h2 class="mb-4">📦 Gestión de Materiales</h2>

<!--MENSAJE DE ELIMINACIÓN -->
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'eliminado') { ?>
    <div class="alert alert-success">
        🗑️ Material eliminado correctamente
    </div>
<?php } ?>

<!--BOTÓN AGREGAR -->
<a href="agregar_material.php" class="btn btn-primary mb-3">
    ➕ Agregar Material
</a>

<table class="table table-bordered table-hover shadow">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Unidad</th>
            <th>Stock</th>
            <th>Acciones</th>
        </tr>
    </thead>

    <tbody>
    <?php while($fila = $resultado->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $fila['id']; ?></td>
            <td><?php echo $fila['codigo']; ?></td>
            <td><?php echo $fila['nombre']; ?></td>
            <td><?php echo $fila['unidad']; ?></td>

            <!--STOCK VISUAL -->
            <td>
                <?php if ($fila['stock'] < 10) { ?>
                    <span class="badge bg-danger">
                        <?php echo $fila['stock']; ?> ⚠️ Bajo
                    </span>
                <?php } else { ?>
                    <span class="badge bg-success">
                        <?php echo $fila['stock']; ?>
                    </span>
                <?php } ?>
            </td>

            <!--ACCIONES -->
            <td>
                <a href="editar_material.php?id=<?php echo $fila['id']; ?>" 
                   class="btn btn-warning btn-sm">
                   ✏️ Editar
                </a>

                <a href="eliminar_material.php?id=<?php echo $fila['id']; ?>" 
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('¿Eliminar este material?')">
                   🗑️ Eliminar
                </a>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<?php include("footer.php"); ?>
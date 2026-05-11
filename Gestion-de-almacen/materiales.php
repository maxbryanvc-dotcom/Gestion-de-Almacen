<?php
session_start(); // 🔥 (LÍNEA 1) INICIAR SESIÓN

// 🔒 (LÍNEA 3-7) VALIDAR LOGIN
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// 🔥 (LÍNEA 9-13) VALIDAR ROL (SOLO ADMIN)
if ($_SESSION['rol'] != 'admin') {
    echo "❌ No tienes permiso para acceder a esta página";
    exit();
}

include("conexion.php"); // (LÍNEA 16)

$sql = "SELECT * FROM materiales";
$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Materiales</title>
</head>
<body>

<!-- 🔥 MENÚ -->
<a href="agregar_material.php">Agregar Material</a> |
<a href="entrada_material.php">Entrada</a> |
<a href="salida_material.php">Salida</a> |
<a href="ver_historial.php">Historial</a> |
<a href="requerimiento.php">Requerimiento</a> |
<a href="logout.php">Cerrar sesión</a>

<h2>Lista de Materiales</h2>

<table border="1">
<tr>
    <th>ID</th>
    <th>Código</th>
    <th>Nombre</th>
    <th>Unidad</th>
    <th>Stock</th>
    <th>Acciones</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()) { ?>
<tr>
    <td><?php echo $fila['id']; ?></td>
    <td><?php echo $fila['codigo']; ?></td>
    <td><?php echo $fila['nombre']; ?></td>
    <td><?php echo $fila['unidad']; ?></td>

    <!-- 🔥 STOCK CON ALERTA -->
    <td style="<?php echo ($fila['stock'] < 10) ? 'color:red; font-weight:bold;' : ''; ?>">
        <?php echo $fila['stock']; ?>
        <?php if ($fila['stock'] < 10) echo " ⚠️ Stock bajo"; ?>
    </td>

    <td>
        <a href="editar_material.php?id=<?php echo $fila['id']; ?>">Editar</a> |
        <a href="eliminar_material.php?id=<?php echo $fila['id']; ?>" 
        onclick="return confirm('¿Seguro que deseas eliminar?')">Eliminar</a>
    </td>
</tr>
<?php } ?>

</table>

</body>
</html>
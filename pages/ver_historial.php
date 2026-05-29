<?php
session_start(); // 🔐 (LÍNEA 1) Control de sesión

// (LÍNEAS 3-7) Validar login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/Conexion.php'; // 🔌 (LÍNEA 10) Conexión BD

// (LÍNEAS 13-18) Consulta con JOIN para traer nombre del material
$sql = "SELECT h.tipo, h.usuario, h.fecha, m.nombre 
        FROM historial h
        LEFT JOIN materiales m ON h.material_id = m.id
        ORDER BY h.fecha DESC";

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Historial del Sistema</title>
</head>
<body>

<h2>📊 Historial de Movimientos</h2>

<a href="materiales.php">Materiales</a> |
<a href="ver_historial.php">Historial</a> |
<a href="requerimiento.php">Requerimientos</a> |
<a href="logout.php">Cerrar sesión</a>

<br><br>

<table border="1" cellpadding="10">
<tr>
    <th>Tipo</th>
    <th>Usuario</th>
    <th>Material</th>
    <th>Fecha</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()) { ?>
<tr>
    <td>
        <?php 
        // (LÍNEAS 40-47) Mostrar tipo con color
        if ($fila['tipo'] == 'EDICION') {
            echo "<span style='color:blue;'>EDICIÓN</span>";
        } elseif ($fila['tipo'] == 'ELIMINACION') {
            echo "<span style='color:red;'>ELIMINACIÓN</span>";
        } else {
            echo $fila['tipo'];
        }
        ?>
    </td>

    <td><?php echo $fila['usuario']; ?></td>

    <td>
        <?php 
        //(LÍNEAS 55-59) Si el material ya no existe
        echo $fila['nombre'] ? $fila['nombre'] : "Material eliminado";
        ?>
    </td>

    <td><?php echo $fila['fecha']; ?></td>
</tr>
<?php } ?>

</table>

</body>
</html>
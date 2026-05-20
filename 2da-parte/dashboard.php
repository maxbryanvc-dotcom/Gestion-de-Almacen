<?php
include("conexion.php");

// CONSULTAS
$materiales = $conn->query("SELECT COUNT(*) as total FROM materiales")->fetch_assoc();
$entradas = $conn->query("SELECT COUNT(*) as total FROM entradas")->fetch_assoc();
$salidas = $conn->query("SELECT COUNT(*) as total FROM salidas")->fetch_assoc();
$requerimientos = $conn->query("SELECT COUNT(*) as total FROM requerimientos")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>

<h2>📊 Dashboard del Sistema</h2>

<a href="materiales.php">Ir a Materiales</a> |
<a href="historial.php">Historial</a> |
<a href="requerimiento.php">Requerimientos</a> |
<a href="logout.php">Cerrar sesión</a>

<hr>

<table border="1" cellpadding="20">
<tr>
    <th>Total Materiales</th>
    <th>Total Entradas</th>
    <th>Total Salidas</th>
    <th>Total Requerimientos</th>
</tr>

<tr>
    <td><?php echo $materiales['total']; ?></td>
    <td><?php echo $entradas['total']; ?></td>
    <td><?php echo $salidas['total']; ?></td>
    <td><?php echo $requerimientos['total']; ?></td>
</tr>
</table>

</body>
</html>
<?php
include("conexion.php");

// Obtener materiales y técnicos
$materiales = $conn->query("SELECT * FROM materiales");
$tecnicos = $conn->query("SELECT * FROM tecnicos");

if ($_POST) {
    $material_id = $_POST['material_id'];
    $tecnico_id = $_POST['tecnico_id'];
    $cantidad = $_POST['cantidad'];
    $fecha = date("Y-m-d");
    $fecha_hora = date("Y-m-d H:i:s");

    // 🔥 VALIDAR QUE NO ESTÉ VACÍO
    if ($cantidad <= 0) {
        echo "Cantidad inválida";
        exit();
    }

    // 🔥 OBTENER STOCK
    $res = $conn->query("SELECT stock FROM materiales WHERE id=$material_id");
    $data = $res->fetch_assoc();

    // 🔥 OBTENER NOMBRE DEL TÉCNICO
    $resTec = $conn->query("SELECT nombre FROM tecnicos WHERE id=$tecnico_id");
    $tec = $resTec->fetch_assoc();
    $nombre_tecnico = $tec['nombre'];

    if ($data['stock'] >= $cantidad) {

        // Registrar salida
        $conn->query("INSERT INTO salidas (material_id, tecnico_id, cantidad, fecha)
                      VALUES ($material_id, $tecnico_id, $cantidad, '$fecha')");

        // DESCONTAR STOCK
        $conn->query("UPDATE materiales 
                      SET stock = stock - $cantidad 
                      WHERE id = $material_id");

        // 🔥 GUARDAR EN HISTORIAL
        $conn->query("INSERT INTO movimientos (tipo, material_id, cantidad, tecnico, fecha)
                      VALUES ('salida', $material_id, $cantidad, '$nombre_tecnico', '$fecha_hora')");

        header("Location: materiales.php");

    } else {
        echo "No hay suficiente stock";
    }
}
?>

<h2>Salida de Material</h2>

<form method="POST">
    Material:
    <select name="material_id">
        <?php while($m = $materiales->fetch_assoc()) { ?>
            <option value="<?php echo $m['id']; ?>">
                <?php echo $m['nombre']; ?>
            </option>
        <?php } ?>
    </select><br>

    Técnico:
    <select name="tecnico_id">
        <?php while($t = $tecnicos->fetch_assoc()) { ?>
            <option value="<?php echo $t['id']; ?>">
                <?php echo $t['nombre']; ?>
            </option>
        <?php } ?>
    </select><br>

    Cantidad: <input type="number" name="cantidad"><br>

    <button type="submit">Registrar Salida</button>
</form>
<?php
include("conexion.php");
include("layout.php");

// OBTENER DATOS
$materiales = $conn->query("SELECT * FROM materiales");
$tecnicos = $conn->query("SELECT * FROM tecnicos");

// PROCESAR FORMULARIO
if ($_POST) {
    $material_id = $_POST['material_id'];
    $tecnico_id = $_POST['tecnico_id'];
    $cantidad = $_POST['cantidad'];
    $fecha = date("Y-m-d");

    //OBTENER STOCK ACTUAL
    $consulta = $conn->query("SELECT stock FROM materiales WHERE id=$material_id");
    $fila = $consulta->fetch_assoc();
    $stock_actual = $fila['stock'];

    // VALIDAR STOCK
    if ($cantidad > $stock_actual) {
        $error = "❌ No hay suficiente stock";
    } else {

        // INSERTAR SALIDA
        $sql = "INSERT INTO salidas (material_id, tecnico_id, cantidad, fecha)
                VALUES ('$material_id', '$tecnico_id', '$cantidad', '$fecha')";

        if ($conn->query($sql)) {

            //RESTAR STOCK
            $conn->query("UPDATE materiales 
                          SET stock = stock - $cantidad 
                          WHERE id = $material_id");

            $success = "✅ Salida registrada correctamente";
        } else {
            $error = "❌ Error al registrar salida";
        }
    }
}
?>

<h2 class="mb-4">📤 Salida de Material</h2>

<!--MENSAJES -->
<?php if(isset($error)) { ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<?php if(isset($success)) { ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php } ?>

<div class="card shadow p-4">

<form method="POST">

    <!--MATERIAL -->
    <div class="mb-3">
        <label class="form-label">Material</label>
        <select name="material_id" class="form-control" required>
            <option value="">-- Seleccionar material --</option>
            <?php while($m = $materiales->fetch_assoc()) { ?>
                <option value="<?php echo $m['id']; ?>">
                    <?php echo $m['nombre']; ?> (Stock: <?php echo $m['stock']; ?>)
                </option>
            <?php } ?>
        </select>
    </div>

    <!--TÉCNICO -->
    <div class="mb-3">
        <label class="form-label">Técnico</label>
        <select name="tecnico_id" class="form-control" required>
            <option value="">-- Seleccionar técnico --</option>
            <?php while($t = $tecnicos->fetch_assoc()) { ?>
                <option value="<?php echo $t['id']; ?>">
                    <?php echo $t['nombre']; ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <!--CANTIDAD -->
    <div class="mb-3">
        <label class="form-label">Cantidad</label>
        <input type="number" name="cantidad" class="form-control" required>
    </div>

    <!--BOTÓN -->
    <button class="btn btn-danger w-100">
        📤 Registrar Salida
    </button>

</form>

</div>

<?php include("footer.php"); ?>
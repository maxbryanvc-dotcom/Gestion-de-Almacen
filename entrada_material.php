<?php
include("conexion.php");
include("layout.php");

//OBTENER MATERIALES
$materiales = $conn->query("SELECT * FROM materiales");

// PROCESAR FORMULARIO
if ($_POST) {
    $material_id = $_POST['material_id'];
    $cantidad = $_POST['cantidad'];
    $fecha = date("Y-m-d");

    //INSERTAR EN ENTRADAS
    $sql = "INSERT INTO entradas (material_id, cantidad, fecha)
            VALUES ('$material_id', '$cantidad', '$fecha')";

    if ($conn->query($sql)) {

        //ACTUALIZAR STOCK
        $conn->query("UPDATE materiales 
                      SET stock = stock + $cantidad 
                      WHERE id = $material_id");

        $success = "✅ Entrada registrada correctamente";
    } else {
        $error = "❌ Error al registrar entrada";
    }
}
?>

<h2 class="mb-4">📥 Entrada de Material</h2>

<!--MENSAJES -->
<?php if(isset($error)) { ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<?php if(isset($success)) { ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php } ?>

<div class="card shadow p-4">

<form method="POST">

    <!--SELECCIONAR MATERIAL -->
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

    <!--CANTIDAD -->
    <div class="mb-3">
        <label class="form-label">Cantidad</label>
        <input type="number" name="cantidad" class="form-control" required>
    </div>

    <!--BOTÓN -->
    <button class="btn btn-success w-100">
        📥 Registrar Entrada
    </button>

</form>

</div>

<?php include("footer.php"); ?>
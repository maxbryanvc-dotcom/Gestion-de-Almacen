<?php
session_start();
include("conexion.php");
include("layout.php");

// OBTENER DATOS
$materiales = $conn->query("SELECT * FROM materiales");

if ($_POST) {

    if (empty($_POST['material_id']) || empty($_POST['cantidad'])) {
        $error = "Todos los campos son obligatorios";
    } else {

        $fecha = date("Y-m-d");

        // guardar cabecera
        $sql = "INSERT INTO requerimientos (fecha, estado)
                VALUES ('$fecha', 'Aprobado')";

        if ($conn->query($sql)) {

            // 🔥 CLAVE
            $id_generado = $conn->insert_id;

            // guardar detalle
            $material_id = $_POST['material_id'];
            $cantidad = $_POST['cantidad'];

            $conn->query("INSERT INTO detalle_requerimiento 
            (requerimiento_id, material_id, cantidad)
            VALUES ('$id_generado', '$material_id', '$cantidad')");

            $success = "Requerimiento registrado correctamente";

        } else {
            $error = "Error al registrar";
        }
    }
}
?>

<h2>Solicitud de Material</h2>

<?php if (isset($success)) { ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php } ?>

<?php if (isset($error)) { ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<!-- 🔥 BOTÓN WORD (AQUÍ ESTÁ LA CLAVE) -->
<?php if (isset($id_generado)) { ?>
    <div class="mt-3">
        <a href="generar_word.php?id=<?php echo $id_generado; ?>"
           class="btn btn-success"
           target="_blank">
           Descargar Word
        </a>
    </div>
<?php } ?>

<div class="card p-4">
<form method="POST">

    <label>Material</label>
    <select name="material_id" required>
        <option value="">Seleccionar</option>
        <?php while($m = $materiales->fetch_assoc()) { ?>
            <option value="<?php echo $m['id']; ?>">
                <?php echo $m['nombre']; ?>
            </option>
        <?php } ?>
    </select>

    <br><br>

    <label>Cantidad</label>
    <input type="number" name="cantidad" required>

    <br><br>

    <button type="submit">Registrar</button>

</form>
</div>
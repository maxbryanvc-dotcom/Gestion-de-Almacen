<?php
include("conexion.php");
include("layout.php");

if ($_POST) {
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $stock = $_POST['stock'];

    // VALIDAR CÓDIGO DUPLICADO
    $verificar = $conn->query("SELECT * FROM materiales WHERE codigo='$codigo'");

    if ($verificar->num_rows > 0) {
        $error = "❌ El código ya existe";
    } else {

        $sql = "INSERT INTO materiales (codigo, nombre, unidad, stock)
                VALUES ('$codigo', '$nombre', '$unidad', '$stock')";

        if ($conn->query($sql)) {
            $success = "✅ Material agregado correctamente";
        } else {
            $error = "❌ Error al guardar";
        }
    }
}
?>

<h2 class="mb-4">➕ Agregar Material</h2>

<!--MENSAJES -->
<?php if(isset($error)) { ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<?php if(isset($success)) { ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php } ?>

<div class="card shadow p-4">

<form method="POST">

    <div class="mb-3">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Unidad</label>
        <input type="text" name="unidad" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Stock Inicial</label>
        <input type="number" name="stock" class="form-control" required>
    </div>

    <button class="btn btn-success w-100">
        💾 Guardar Material
    </button>

</form>

</div>

<?php include("footer.php"); ?>
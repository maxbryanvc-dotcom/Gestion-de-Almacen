<?php
include("conexion.php");
include("layout.php");

// VALIDAR ID
if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>❌ ID no especificado</div>";
    include("footer.php");
    exit();
}

$id = $_GET['id'];

//  OBTENER DATOS
$sql = "SELECT * FROM materiales WHERE id=$id";
$resultado = $conn->query($sql);

if ($resultado->num_rows == 0) {
    echo "<div class='alert alert-danger'>❌ Material no encontrado</div>";
    include("footer.php");
    exit();
}

$fila = $resultado->fetch_assoc();

// ACTUALIZAR
if ($_POST) {
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $unidad = $_POST['unidad'];
    $stock = $_POST['stock'];

    // VALIDAR CÓDIGO DUPLICADO (EXCEPTO ESTE MISMO)
    $verificar = $conn->query("SELECT * FROM materiales WHERE codigo='$codigo' AND id != $id");

    if ($verificar->num_rows > 0) {
        $error = "❌ El código ya pertenece a otro material";
    } else {

        $sql = "UPDATE materiales SET 
                codigo='$codigo',
                nombre='$nombre',
                unidad='$unidad',
                stock='$stock'
                WHERE id=$id";

        if ($conn->query($sql)) {
            $success = "✅ Material actualizado correctamente";

            // 🔄 RECARGAR DATOS
            $resultado = $conn->query("SELECT * FROM materiales WHERE id=$id");
            $fila = $resultado->fetch_assoc();
        } else {
            $error = "❌ Error al actualizar";
        }
    }
}
?>

<h2 class="mb-4">✏️ Editar Material</h2>

<!-- MENSAJES -->
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
        <input type="text" name="codigo" class="form-control"
               value="<?php echo $fila['codigo']; ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control"
               value="<?php echo $fila['nombre']; ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Unidad</label>
        <input type="text" name="unidad" class="form-control"
               value="<?php echo $fila['unidad']; ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Stock</label>
        <input type="number" name="stock" class="form-control"
               value="<?php echo $fila['stock']; ?>" required>
    </div>

    <button class="btn btn-warning w-100">
        💾 Actualizar Material
    </button>

</form>

</div>

<?php include("footer.php"); ?>
<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// CREAR USUARIO
if (isset($_POST['crear'])) {
    $usuario = $_POST['usuario'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];

    $conn->query("INSERT INTO usuarios (usuario, password, rol)
    VALUES ('$usuario', '$password', '$rol')");
}

// ACTUALIZAR
if (isset($_POST['editar'])) {
    $id = $_POST['id'];
    $usuario = $_POST['usuario'];
    $rol = $_POST['rol'];

    $conn->query("UPDATE usuarios 
    SET usuario='$usuario', rol='$rol'
    WHERE id=$id");
}

// ELIMINAR (lógico)
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conn->query("UPDATE usuarios SET estado=0 WHERE id=$id");
}

// LISTAR
$usuarios = $conn->query("SELECT * FROM usuarios WHERE estado=1");
?>

<h2>Gestión de Usuarios</h2>

<!-- CREAR -->
<form method="POST" class="mb-4">
    <input type="text" name="usuario" placeholder="Usuario" required>
    <input type="password" name="password" placeholder="Contraseña" required>

    <select name="rol">
        <option value="admin">Admin</option>
        <option value="almacen">Almacén</option>
        <option value="tecnico">Técnico</option>
    </select>

    <button name="crear">Crear Usuario</button>
</form>

<!-- TABLA -->
<table border="1" cellpadding="10">
<tr>
    <th>ID</th>
    <th>Usuario</th>
    <th>Rol</th>
    <th>Acciones</th>
</tr>

<?php while($u = $usuarios->fetch_assoc()) { ?>
<tr>
<form method="POST">
    <td><?php echo $u['id']; ?></td>

    <td>
        <input type="text" name="usuario" value="<?php echo $u['usuario']; ?>">
    </td>

    <td>
        <select name="rol">
            <option <?php if($u['rol']=='admin') echo 'selected'; ?>>admin</option>
            <option <?php if($u['rol']=='almacen') echo 'selected'; ?>>almacen</option>
            <option <?php if($u['rol']=='tecnico') echo 'selected'; ?>>tecnico</option>
        </select>
    </td>

    <td>
        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">

        <button name="editar">Actualizar</button>
        <a href="?eliminar=<?php echo $u['id']; ?>">Eliminar</a>
    </td>
</form>
</tr>
<?php } ?>

</table>

<?php include("footer.php"); ?>
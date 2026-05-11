<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conexion.php");
session_start();

if ($_POST) {
    $usuario = $_POST['usuario'];
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM usuarios 
            WHERE usuario='$usuario' AND TRIM(password)='$password'";

    $resultado = $conn->query($sql);

    if ($resultado->num_rows > 0) {

        $fila = $resultado->fetch_assoc();

        $_SESSION['usuario'] = $fila['usuario'];
        $_SESSION['rol'] = $fila['rol'];

        header("Location: dashboard.php");
        exit();

    } else {
        $error = "❌ Usuario o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>

<h2>🔐 Login</h2>

<?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form method="POST">
    Usuario: <input type="text" name="usuario"><br><br>
    Contraseña: <input type="password" name="password"><br><br>
    <button type="submit">Ingresar</button>
</form>

</body>
</html>
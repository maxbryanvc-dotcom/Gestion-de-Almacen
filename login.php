<?php
include("conexion.php");
session_start();

if ($_POST) {
    $usuario = $_POST['usuario'];
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM usuarios WHERE usuario='$usuario'";
    $resultado = $conn->query($sql);

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();

        if (password_verify($password, $fila['password'])) {
            $_SESSION['usuario'] = $fila['usuario'];
            $_SESSION['rol'] = $fila['rol'];

            header("Location: materiales.php");
            exit();
        } else {
            $error = "❌ Contraseña incorrecta";
        }
    } else {
        $error = "❌ Usuario no existe";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light d-flex align-items-center" style="height:100vh;">

<div class="container">
    <div class="row justify-content-center">

        <div class="col-md-4">

            <div class="card shadow">

                <div class="card-body">

                    <h3 class="text-center mb-4">🔐 Iniciar Sesión</h3>

                    <?php if(isset($error)) { ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php } ?>

                    <form method="POST">

                        <div class="mb-3">
                            <label>Usuario</label>
                            <input type="text" name="usuario" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button class="btn btn-primary w-100">
                            Ingresar
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>
</div>

</body>
</html>
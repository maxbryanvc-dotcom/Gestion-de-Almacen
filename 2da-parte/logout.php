<?php
session_start();

// destruir sesión
$_SESSION = [];
session_destroy();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cerrando sesión</title>
    <meta http-equiv="refresh" content="1;url=http://localhost/Gestion-de-almacen/login.php">
</head>
<body>

<h3>Cerrando sesión...</h3>
<p>Serás redirigido al login automáticamente.</p>

</body>
</html>
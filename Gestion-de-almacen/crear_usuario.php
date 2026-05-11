<?php
include("conexion.php");

$usuario = "admin";
$password = password_hash("1234", PASSWORD_DEFAULT);

$conn->query("INSERT INTO usuarios (usuario, password)
VALUES ('$usuario', '$password')");

echo "Usuario seguro creado";
?>
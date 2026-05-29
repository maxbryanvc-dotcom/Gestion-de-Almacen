<?php
require_once __DIR__ . '/../includes/Conexion.php';

$usuario = "almacen";
$password_texto = "1234";
$rol = "almacen";

$password = password_hash($password_texto, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $usuario, $password, $rol);

if($stmt->execute()){
    echo "✅ Usuario creado correctamente";
} else {
    echo "❌ Error";
}
?>
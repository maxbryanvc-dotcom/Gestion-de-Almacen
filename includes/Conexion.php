<?php
// ============================================================
// CONEXIÓN A BASE DE DATOS
// ============================================================

$host = "localhost";
$user = "root";
$pass = "";
$db   = "almacen_sistema";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("DB Error: " . $conn->connect_error);
    die("Error de conexión. Contacte al administrador.");
}

$conn->set_charset("utf8mb4");

// Alias en minúsculas para compatibilidad con includes que usan "conexion.php"
// (PHP en Windows no distingue mayúsculas en includes, pero por claridad se mantiene)

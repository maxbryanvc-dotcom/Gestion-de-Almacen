<?php
// ============================================================
// CONEXIÓN A BASE DE DATOS
// Compatible con XAMPP (local) y Docker automáticamente
// ============================================================

// Docker usa variables de entorno; XAMPP usa valores por defecto
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'almacen_sistema';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("DB Error: " . $conn->connect_error);
    die("Error de conexión. Contacte al administrador.");
}

$conn->set_charset("utf8mb4");

// Alias en minúsculas para compatibilidad con includes que usan "conexion.php"
// (PHP en Windows no distingue mayúsculas en includes, pero por claridad se mantiene)

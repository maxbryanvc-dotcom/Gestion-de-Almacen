<?php
// ============================================================
// CONEXION A BASE DE DATOS
// Compatible con XAMPP (local) y Docker automaticamente
// ============================================================

// Docker usa variables de entorno; XAMPP usa valores por defecto
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'maxBryan';
$pass = getenv('DB_PASS') ?: '1996max2307';
$db   = getenv('DB_NAME') ?: 'almacen_sistema';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("DB Error: " . $conn->connect_error);
    die("Error de conexion. Contacte al administrador.");
}

$conn->set_charset("utf8mb4");

<?php
// Punto de entrada: redirige al dashboard si hay sesión, si no al login
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['usuario'])) {
    header("Location: pages/dashboard.php");
} else {
    header("Location: login.php");
}
exit();

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">

        <span class="navbar-brand">📦 Sistema de Almacén</span>

        <!--BOTÓN RESPONSIVE -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- MENÚ -->
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">🏠 Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="materiales.php">📦 Materiales</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="entrada_material.php">📥 Entradas</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="salida_material.php">📤 Salidas</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="historial.php">📊 Historial</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="requerimiento.php">📋 Requerimientos</a>
                </li>

            </ul>

            <!--USUARIO -->
            <span class="text-white me-3">
                👤 <?php echo $_SESSION['usuario']; ?>
            </span>

            <a href="logout.php" class="btn btn-danger btn-sm">
                Cerrar sesión
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
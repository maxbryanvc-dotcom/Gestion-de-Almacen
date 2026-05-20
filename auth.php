<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

function verificarRol($rolesPermitidos){

    if(!in_array($_SESSION['rol'], $rolesPermitidos)){

        echo "
        <div style='padding:20px; background:#ffdddd; border:1px solid red;'>
            No tienes permisos para acceder.
        </div>
        ";

        exit();
    }
}
?>
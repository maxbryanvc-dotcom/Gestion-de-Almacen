<?php
require_once(__DIR__ . "/auth.php");
require_once(__DIR__ . "/conexion.php");
require_once(__DIR__ . "/layout.php");

verificarRol(['admin','almacen']);

// TARJETAS
$totalMateriales = $conn->query("SELECT COUNT(*) as total FROM materiales")->fetch_assoc();
$totalEntradas = $conn->query("SELECT COUNT(*) as total FROM entradas")->fetch_assoc();
$totalSalidas = $conn->query("SELECT COUNT(*) as total FROM salidas")->fetch_assoc();
$totalReq = $conn->query("SELECT COUNT(*) as total FROM requerimientos")->fetch_assoc();

// TABLA MATERIALES
$materiales = $conn->query("SELECT * FROM materiales ORDER BY stock ASC");

if (!$materiales) {
    die("Error SQL: " . $conn->error);
}
?>

<div class="container mt-4">

<h2 class="mb-4">Dashboard del Sistema</h2>

<div class="row mb-4">

    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h5>Total Materiales</h5>
            <h2><?php echo $totalMateriales['total']; ?></h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h5>Entradas</h5>
            <h2><?php echo $totalEntradas['total']; ?></h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h5>Salidas</h5>
            <h2><?php echo $totalSalidas['total']; ?></h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow text-center p-3">
            <h5>Requerimientos</h5>
            <h2><?php echo $totalReq['total']; ?></h2>
        </div>
    </div>

</div>

<div class="card shadow">

    <div class="card-header bg-dark text-white">
        Materiales en Stock
    </div>

    <div class="card-body">

        <table class="table table-bordered table-hover">

            <tr>
                <th>Código</th>
                <th>Material</th>
                <th>Stock Actual</th>
                <th>Estado</th>
            </tr>

            <?php while($m = $materiales->fetch_assoc()) { ?>

            <?php

            $color = "";
            $estado = "";

            if($m['stock'] <= 0){

                $color = "table-danger";
                $estado = "Agotado";

            }
            elseif($m['stock'] <= 5){

                $color = "table-danger";
                $estado = "Crítico";

            }
            elseif($m['stock'] <= 10){

                $color = "table-warning";
                $estado = "Bajo";

            }
            else{

                $color = "table-success";
                $estado = "Disponible";
            }

            ?>

            <tr class="<?php echo $color; ?>">

                <td><?php echo $m['codigo']; ?></td>

                <td><?php echo $m['nombre']; ?></td>

                <td>
                    <strong>
                        <?php echo $m['stock']; ?>
                    </strong>
                </td>

                <td><?php echo $estado; ?></td>

            </tr>

            <?php } ?>

        </table>

    </div>

</div>

</div>

<?php include("footer.php"); ?>

<?php
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
// LAYOUT

require_once __DIR__ . '/../includes/layout.php';

// ===============================
// CONSULTA KARDEX
// ===============================
$sql = "

SELECT

    'ENTRADA' AS tipo,

    m.nombre AS material,

    e.cantidad,

    e.fecha,

    '-' AS tecnico,

    e.observacion

FROM entradas e

INNER JOIN materiales m
ON e.material_id = m.id

UNION ALL

SELECT

    'SALIDA' AS tipo,

    m.nombre AS material,

    s.cantidad,

    s.fecha,

    t.nombre AS tecnico,

    s.observacion

FROM salidas s

INNER JOIN materiales m
ON s.material_id = m.id

LEFT JOIN tecnicos t
ON s.tecnico_id = t.id

ORDER BY fecha DESC

";

$resultado = $conn->query($sql);

?>

<!-- ===============================
CONTENEDOR GENERAL
=============================== -->
<div class="container-fluid">

    <!-- ===============================
    ENCABEZADO
    =============================== -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

        <div>

            <h2 class="fw-bold mb-1">

                Kardex de Inventario

            </h2>

            <p class="text-secondary mb-0">

                Historial completo de movimientos del almacén

            </p>

        </div>

    </div>

    <!-- ===============================
    CARD PRINCIPAL
    =============================== -->
    <div class="card-dashboard">

        <!-- CABECERA -->
        <div class="mb-4">

            <h4 class="fw-bold mb-1">

                Movimientos del Sistema

            </h4>

            <small class="text-secondary">

                Entradas y salidas registradas

            </small>

        </div>

        <!-- TABLA -->
        <div class="table-responsive">

            <table 
                id="tablaKardex"
                class="table table-hover align-middle"
            >

                <thead>

                    <tr>

                        <th>Tipo</th>

                        <th>Material</th>

                        <th>Cantidad</th>

                        <th>Técnico</th>

                        <th>Fecha</th>

                        <th>Observación</th>

                    </tr>

                </thead>

                <tbody>

                    <?php while($row = $resultado->fetch_assoc()) { ?>

                    <?php

                    // ===============================
                    // COLOR DEL MOVIMIENTO
                    // ===============================
                    $badge = "success";

                    if($row['tipo'] == "SALIDA"){

                        $badge = "danger";
                    }

                    ?>

                    <tr>

                        <!-- TIPO -->
                        <td>

                            <span class="badge bg-<?php echo $badge; ?> p-2">

                                <?php echo $row['tipo']; ?>

                            </span>

                        </td>

                        <!-- MATERIAL -->
                        <td>

                            <div class="d-flex align-items-center gap-2">

                                <div class="material-icon">

                                    <i class="fa-solid fa-box"></i>

                                </div>

                                <strong>

                                    <?php echo htmlspecialchars($row['material']); ?>

                                </strong>

                            </div>

                        </td>

                        <!-- CANTIDAD -->
                        <td>

                            <strong>

                                <?php echo $row['cantidad']; ?>

                            </strong>

                        </td>

                        <!-- TÉCNICO -->
                        <td>

                            <?php echo htmlspecialchars($row['tecnico']); ?>

                        </td>

                        <!-- FECHA -->
                        <td>

                            <?php echo $row['fecha'] ? date('d/m/Y H:i', strtotime($row['fecha'])) : '—'; ?>

                        </td>

                        <!-- OBSERVACIÓN -->
                        <td>

                            <?php echo htmlspecialchars($row['observacion']); ?>

                        </td>

                    </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- ===============================
ESTILOS EXTRA
=============================== -->
<style>

.material-icon{

    width:40px;

    height:40px;

    border-radius:12px;

    background:rgba(59,130,246,0.15);

    display:flex;

    align-items:center;

    justify-content:center;

    color:#3b82f6;
}

.table tbody tr{

    transition:0.3s ease;
}

.table tbody tr:hover{

    transform:scale(1.01);
}

</style>

<!-- ===============================
DATATABLE
=============================== -->
<script>

$(document).ready(function(){

    $('#tablaKardex').DataTable({

        responsive:true,

        pageLength:10,

        order:[[4,'desc']],

        language:{

            search:"Buscar:",

            lengthMenu:"Mostrar _MENU_ registros",

            info:"Mostrando _START_ a _END_ de _TOTAL_ registros",

            paginate:{
                next:"Siguiente",
                previous:"Anterior"
            }

        }

    });

});

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
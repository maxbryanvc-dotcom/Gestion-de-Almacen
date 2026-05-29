<?php
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// ===============================
// CONSULTA HISTORIAL
// ===============================
$sql = "
SELECT 
    r.*,
    t.nombre AS tecnico
FROM requerimientos r
LEFT JOIN tecnicos t
ON r.tecnico_id = t.id
ORDER BY r.id DESC
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

        <!-- TÍTULO -->
        <div>

            <h2 class="fw-bold mb-1">

                Historial de Requerimientos

            </h2>

            <p class="text-secondary mb-0">

                Registro completo de requerimientos realizados

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

                Lista de Requerimientos

            </h4>

            <small class="text-secondary">

                Historial general del sistema

            </small>

        </div>

        <!-- ===============================
        TABLA
        =============================== -->
        <div class="table-responsive">

            <table 
                id="tablaHistorial"
                class="table table-hover align-middle"
            >

                <!-- CABECERA -->
                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Técnico</th>

                        <th>Fecha</th>

                        <th>Estado</th>

                        <th>Documento</th>

                    </tr>

                </thead>

                <!-- CUERPO -->
                <tbody>

                    <?php while($row = $resultado->fetch_assoc()) { ?>

                    <?php

                    // ===============================
                    // COLOR DEL ESTADO
                    // ===============================
                    $estado = strtolower($row['estado']);

                    $badge = "secondary";

                    if($estado == "pendiente"){

                        $badge = "warning";

                    }
                    elseif($estado == "aprobado"){

                        $badge = "success";

                    }
                    elseif($estado == "rechazado"){

                        $badge = "danger";
                    }

                    ?>

                    <!-- FILA -->
                    <tr>

                        <!-- ID -->
                        <td>

                            <strong>

                                #<?php echo $row['id']; ?>

                            </strong>

                        </td>

                        <!-- TÉCNICO -->
                        <td>

                            <div class="d-flex align-items-center gap-2">

                                <div class="material-icon">

                                    <i class="fa-solid fa-user"></i>

                                </div>

                                <div>

                                    <strong>

                                        <?php echo htmlspecialchars($row['tecnico']); ?>

                                    </strong>

                                </div>

                            </div>

                        </td>

                        <!-- FECHA -->
                        <td>

                            <?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?>

                        </td>

                        <!-- ESTADO -->
                        <td>

                            <span class="badge bg-<?php echo $badge; ?> p-2">

                                <?php echo ucfirst($row['estado']); ?>

                            </span>

                        </td>

                        <!-- DOCUMENTO -->
                        <td>

                            <a 
                                href="<?= BASE_URL ?>/exports/generar_word.php?id=<?php echo $row['id']; ?>"
                                class="btn btn-primary btn-sm"
                            >

                                <i class="fa-solid fa-file-word"></i>

                                Descargar

                            </a>

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

    $('#tablaHistorial').DataTable({

        responsive:true,

        pageLength:5,

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
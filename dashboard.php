```php
<?php
require_once(__DIR__ . "/auth.php");
require_once(__DIR__ . "/conexion.php");
require_once(__DIR__ . "/layout.php");

verificarRol(['admin','almacen']);

/*
|--------------------------------------------------------------------------
| KPI
|--------------------------------------------------------------------------
*/

$totalMateriales = $conn->query("SELECT COUNT(*) total FROM materiales")->fetch_assoc();
$totalEntradas = $conn->query("SELECT COUNT(*) total FROM entradas")->fetch_assoc();
$totalSalidas = $conn->query("SELECT COUNT(*) total FROM salidas")->fetch_assoc();
$totalReq = $conn->query("SELECT COUNT(*) total FROM requerimientos")->fetch_assoc();

$stockCritico = $conn->query("SELECT COUNT(*) total FROM materiales WHERE stock <= 5")->fetch_assoc();
$agotados = $conn->query("SELECT COUNT(*) total FROM materiales WHERE stock <= 0")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| INVENTARIO
|--------------------------------------------------------------------------
*/

$materiales = $conn->query("SELECT * FROM materiales ORDER BY stock ASC");

/*
|--------------------------------------------------------------------------
| ACTIVIDAD
|--------------------------------------------------------------------------
*/

$actividad = $conn->query("SELECT * FROM salidas ORDER BY id DESC LIMIT 5");
?>

<style>

.container-fluid{
    max-width: 1700px;
}

.top-section{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:20px;
}

.search-box{
    min-width:320px;
}

.sidebar-fixed{
    position: sticky;
    top: 20px;
}


body{
    background: #0f172a;
    color: white;
}

.dashboard-title{
    font-size: 30px;
    font-weight: bold;
}

.card-dashboard{
    background: #1e293b;
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    border: none;
}

.card-dashboard:hover{
    transform: translateY(-3px);
    transition: 0.3s;
}

.kpi-title{
    font-size: 14px;
    color: #cbd5e1;
}

.kpi-value{
    font-size: 34px;
    font-weight: bold;
}

.section-title{
    font-size: 20px;
    margin-bottom: 20px;
    font-weight: bold;
}

.table{
    color: white;
}

.table thead{
    background: #111827;
}

.table td,
.table th{
    vertical-align: middle;
}

.alert-custom{
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    font-weight: bold;
}

.alert-danger-custom{
    background: #7f1d1d;
}

.alert-warning-custom{
    background: #78350f;
}

.quick-btn{
    width: 100%;
    margin-bottom: 12px;
    border-radius: 12px;
    padding: 12px;
    font-weight: bold;
}

.search-box{
    width: 250px;
}

</style>

<div class="container-fluid mt-4">

    <!-- HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">

        <div>
            <h1 class="dashboard-title">
                Dashboard Empresarial
            </h1>

            <small>
                Bienvenido <?php echo $_SESSION['usuario']; ?>
            </small>
        </div>

        <div class="d-flex gap-2 search-box">
            <input 
                type="text" 
                id="buscarMaterial"
                class="form-control" 
                placeholder="Buscar material..."
            >

            <button 
                class="btn btn-primary"
                onclick="buscarTabla()"
            >
                Buscar
            </button>
        </div>

    </div>

    <!-- KPI -->

    <div class="row g-4 mb-4">

        <div class="col-md-3">
            <div class="card-dashboard text-center">
                <div class="kpi-title">Total Materiales</div>
                <div class="kpi-value"><?php echo $totalMateriales['total']; ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-dashboard text-center">
                <div class="kpi-title">Entradas</div>
                <div class="kpi-value"><?php echo $totalEntradas['total']; ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-dashboard text-center">
                <div class="kpi-title">Salidas</div>
                <div class="kpi-value"><?php echo $totalSalidas['total']; ?></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card-dashboard text-center">
                <div class="kpi-title">Requerimientos</div>
                <div class="kpi-value"><?php echo $totalReq['total']; ?></div>
            </div>
        </div>

    </div>

    <!-- CONTENIDO PRINCIPAL -->

<div class="row g-4 align-items-start">

    <!-- SIDEBAR IZQUIERDO -->

    <div class="col-lg-3">

        <div class="sidebar-fixed">

            <!-- ACCESOS RAPIDOS -->

            <div class="card-dashboard mb-4">

                <div class="section-title">
                    Accesos Rápidos
                </div>

                <a href="materiales.php" class="btn btn-primary quick-btn">
                    Registrar Producto
                </a>

                <a href="entrada_material.php" class="btn btn-success quick-btn">
                    Registrar Entrada
                </a>

                <a href="salida_material.php" class="btn btn-danger quick-btn">
                    Registrar Salida
                </a>

                <a href="historial.php" class="btn btn-warning quick-btn">
                    Ver Historial
                </a>

            </div>

            <!-- ALERTAS -->

            <div class="card-dashboard mb-4">

                <div class="section-title">
                    Alertas Inteligentes
                </div>

                <div class="alert-custom alert-danger-custom">
                    Productos agotados: <?php echo $agotados['total']; ?>
                </div>

                <div class="alert-custom alert-warning-custom">
                    Stock crítico: <?php echo $stockCritico['total']; ?>
                </div>

            </div>

        </div>

    </div>

    <div class="col-lg-9">

        <!-- INVENTARIO -->

        <div class="col-lg-9">

            <div class="card-dashboard h-100">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div class="section-title mb-0">
                        Inventario General
                    </div>

                    <span class="badge bg-primary p-2">
                        Stock en tiempo real
                    </span>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle" id="tablaMateriales">

                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Material</th>
                                <th>Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>

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

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

        </div>

    </div>

    <!-- ACTIVIDAD RECIENTE -->

    <div class="card-dashboard mt-4">

        <div class="d-flex justify-content-between align-items-center mb-2">

            <div class="section-title mb-0">
                Actividad Reciente
            </div>

            <span class="badge bg-success p-2">
                Últimos movimientos
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Material</th>
                        <th>Cantidad</th>
                        <th>Fecha</th>
                    </tr>
                </thead>

                <tbody>

                <?php while($a = $actividad->fetch_assoc()) { ?>

                    <tr>

                        <td><?php echo $a['id']; ?></td>

                        <td><?php echo $a['material_id']; ?></td>

                        <td><?php echo $a['cantidad']; ?></td>

                        <td><?php echo $a['fecha']; ?></td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
function buscarTabla() {

    let input = document.getElementById('buscarMaterial').value.toLowerCase();

    let tabla = document.getElementById('tablaMateriales');

    let filas = tabla.getElementsByTagName('tr');

    for (let i = 1; i < filas.length; i++) {

        let texto = filas[i].textContent.toLowerCase();

        if (texto.includes(input)) {
            filas[i].style.display = '';
        } else {
            filas[i].style.display = 'none';
        }
    }
}
</script>

<?php include("footer.php"); ?>




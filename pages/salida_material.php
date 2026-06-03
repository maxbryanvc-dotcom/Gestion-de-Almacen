<?php
// ============================================================
// SALIDA DE MATERIAL — múltiples materiales por transacción
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

$tecnicos = $conn->query("SELECT id, nombre, cargo FROM tecnicos ORDER BY nombre ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tecnico_id  = intval($_POST['tecnico_id']  ?? 0) ?: null;
    $observacion = trim($_POST['observacion']   ?? '');
    $material_ids = $_POST['material_id']       ?? [];
    $cantidades   = $_POST['cantidad']          ?? [];
    $usuario      = $_SESSION['usuario'];
    $fecha        = date('Y-m-d H:i:s');

    // Validar y verificar stock de cada material
    $filas_validas = [];
    $errores_stock = [];

    foreach ($material_ids as $k => $mid) {
        $mid  = intval($mid);
        $cant = floatval($cantidades[$k] ?? 0);
        if ($mid <= 0 || $cant <= 0) continue;

        $chk = $conn->prepare("SELECT nombre, stock FROM materiales WHERE id=? AND activo=1 LIMIT 1");
        $chk->bind_param('i', $mid);
        $chk->execute();
        $mat = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$mat) continue;
        if ($cant > $mat['stock']) {
            $errores_stock[] = "«{$mat['nombre']}»: necesita $cant, disponible {$mat['stock']}";
            continue;
        }
        $filas_validas[] = ['id'=>$mid, 'cant'=>$cant, 'nombre'=>$mat['nombre']];
    }

    if (!empty($errores_stock)) {
        $msg = 'Stock insuficiente: ' . implode(' | ', $errores_stock);
        $tipo_msg = 'error';
    } elseif (empty($filas_validas)) {
        $msg = 'Agrega al menos un material con cantidad válida.';
        $tipo_msg = 'error';
    } else {
        $stmtS = $conn->prepare(
            "INSERT INTO salidas (material_id, tecnico_id, cantidad, fecha, observacion, usuario)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtU = $conn->prepare(
            "UPDATE materiales SET stock = stock - ? WHERE id = ?"
        );

        foreach ($filas_validas as $fv) {
            $stmtS->bind_param('iiisss', $fv['id'], $tecnico_id, $fv['cant'], $fecha, $observacion, $usuario);
            $stmtS->execute();
            $stmtU->bind_param('di', $fv['cant'], $fv['id']);
            $stmtU->execute();
        }
        $stmtS->close();
        $stmtU->close();

        audit_log($conn, 'SALIDA_MATERIAL',
            count($filas_validas) . ' material(es) despachados por ' . $usuario);

        $msg = 'Salida registrada correctamente (' . count($filas_validas) . ' material(es)).';
        $tipo_msg = 'success';
    }
}

// Últimas salidas
$ultimas = $conn->query("
    SELECT s.fecha, m.nombre AS material, s.cantidad,
           COALESCE(t.nombre,'—') AS tecnico, s.usuario
    FROM salidas s
    JOIN materiales m ON m.id = s.material_id
    LEFT JOIN tecnicos t ON t.id = s.tecnico_id
    ORDER BY s.id DESC LIMIT 30
");
?>

<div class="container-fluid">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">
            <i class="fa-solid fa-upload me-2 text-danger"></i>
            Salida de Material
        </h2>
        <p class="text-secondary">Despacho de materiales a técnicos</p>
    </div>

    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Correcto":"Error" ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:4000,timerProgressBar:true,confirmButtonColor:'#3b82f6'
    }));
    </script>
    <?php endif; ?>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-6">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4">
                    <i class="fa-solid fa-arrow-up me-2 text-danger"></i>
                    Registrar Salida
                </h5>
                <form method="POST" id="formSalida">

                    <!-- Técnico -->
                    <div class="mb-3">
                        <label class="form-label">Técnico Responsable</label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            <?php while ($t = $tecnicos->fetch_assoc()): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                                <?= $t['cargo'] ? ' — '.$t['cargo'] : '' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Materiales dinámicos -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0 fw-semibold">
                                <i class="fa-solid fa-boxes-stacked me-1"></i>
                                Materiales a despachar *
                            </label>
                            <button type="button" class="btn btn-danger btn-sm btn-custom"
                                    onclick="agregarFila('tbodySalida')">
                                <i class="fa-solid fa-plus me-1"></i>Agregar
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle" style="table-layout:fixed;">
                                <colgroup>
                                    <col style="width:42%"><col style="width:13%">
                                    <col style="width:11%"><col style="width:22%">
                                    <col style="width:8%"><col style="width:4%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Material</th><th>Código</th>
                                        <th>Stock</th><th>Cantidad</th>
                                        <th>Unidad</th><th></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodySalida"></tbody>
                            </table>
                        </div>
                        <p id="sinFilasSalida" class="text-secondary text-center py-2"
                           style="font-size:13px;">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Haz clic en "Agregar" para añadir materiales
                        </p>
                    </div>

                    <!-- Observación -->
                    <div class="mb-3">
                        <label class="form-label">Observación</label>
                        <input type="text" name="observacion" class="form-control"
                               placeholder="Orden de trabajo, proyecto, etc." maxlength="255">
                    </div>

                    <!-- Info -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);text-align:center;">
                                <small class="text-secondary">Fecha y hora</small>
                                <div style="font-size:13px;font-weight:600;"><?= date('d/m/Y H:i') ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);text-align:center;">
                                <small class="text-secondary">Registrado por</small>
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($_SESSION['usuario']) ?></div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger btn-custom w-100" id="btnGuardarSalida" disabled>
                        <i class="fa-solid fa-upload me-2"></i>Registrar Salida
                    </button>
                </form>
            </div>
        </div>

        <!-- TABLA ÚLTIMAS SALIDAS -->
        <div class="col-lg-6">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4">
                    <i class="fa-solid fa-list me-2 text-secondary"></i>
                    Últimas Salidas
                </h5>
                <div class="table-responsive">
                    <table id="tablaSalidas" class="table table-hover align-middle">
                        <thead>
                            <tr><th>Fecha</th><th>Material</th><th>Cant.</th><th>Técnico</th><th>Por</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($s = $ultimas->fetch_assoc()): ?>
                        <tr>
                            <td><small><?= $s['fecha'] ? date('d/m/Y H:i', strtotime($s['fecha'])) : '—' ?></small></td>
                            <td><strong><?= htmlspecialchars($s['material']) ?></strong></td>
                            <td><span class="badge bg-danger p-2">-<?= $s['cantidad'] ?></span></td>
                            <td><?= htmlspecialchars($s['tecnico']) ?></td>
                            <td><code style="color:#60a5fa;"><?= htmlspecialchars($s['usuario'] ?? '—') ?></code></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones agregarFila, iniciarAC, seleccionar, actualizarBtn
// vienen de: assets/js/materiales_ajax.js (cargado en layout.php)

$(document).ready(()=>{
    $('#tablaSalidas').DataTable({responsive:true,pageLength:10,language:dtLang,order:[[0,'desc']]});
    agregarFila('tbodySalida');
});

document.getElementById('formSalida').addEventListener('submit',function(e){
    e.preventDefault();
    const validas=[...document.querySelectorAll('#tbodySalida .fila-mat')].filter(tr=>{
        return tr.querySelector('.inp-id').value>0 &&
               parseFloat(tr.querySelector('.inp-cant').value)>0;
    });
    if(!validas.length){Swal.fire('Aviso','Agrega al menos un material.','warning');return;}
    Swal.fire({
        title:'¿Registrar salida?',
        text:`Se descontará stock de ${validas.length} material(es).`,
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#ef4444',cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, registrar',cancelButtonText:'Revisar'
    }).then(r=>{if(r.isConfirmed)this.submit();});
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

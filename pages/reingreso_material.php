<?php
// ============================================================
// REINGRESO DE MATERIAL — múltiples materiales por devolución
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

$tecnicos = $conn->query("SELECT id, nombre, cargo FROM tecnicos ORDER BY nombre ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tecnico_id   = intval($_POST['tecnico_id']  ?? 0) ?: null;
    $motivo       = trim($_POST['motivo']         ?? '');
    $observacion  = trim($_POST['observacion']    ?? '');
    $material_ids = $_POST['material_id']         ?? [];
    $cantidades   = $_POST['cantidad']            ?? [];
    $registrado   = $_SESSION['usuario'];
    $fecha        = date('Y-m-d H:i:s');

    $filas_validas = [];
    foreach ($material_ids as $k => $mid) {
        $mid  = intval($mid);
        $cant = floatval($cantidades[$k] ?? 0);
        if ($mid <= 0 || $cant <= 0) continue;
        $filas_validas[] = ['id'=>$mid, 'cant'=>$cant];
    }

    if (empty($motivo)) {
        $msg = 'El motivo de devolución es obligatorio.'; $tipo_msg = 'error';
    } elseif (empty($filas_validas)) {
        $msg = 'Agrega al menos un material con cantidad válida.'; $tipo_msg = 'error';
    } else {
        $stmtR = $conn->prepare(
            "INSERT INTO reingresos (material_id, tecnico_id, cantidad, motivo, observacion, registrado_por, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtU = $conn->prepare(
            "UPDATE materiales SET stock = stock + ? WHERE id = ?"
        );

        foreach ($filas_validas as $fv) {
            $cant = $fv['cant'];
            $stmtR->bind_param('iiissss',
                $fv['id'], $tecnico_id, $cant,
                $motivo, $observacion, $registrado, $fecha);
            $stmtR->execute();
            $stmtU->bind_param('di', $cant, $fv['id']);
            $stmtU->execute();
        }
        $stmtR->close();
        $stmtU->close();

        audit_log($conn, 'REINGRESO_MATERIAL',
            count($filas_validas) . ' material(es) devueltos. Motivo: ' . $motivo);

        $msg = 'Reingreso registrado (' . count($filas_validas) . ' material(es)). Stock actualizado.';
        $tipo_msg = 'success';
    }
}

// Historial
$historial = $conn->query("
    SELECT r.fecha, m.nombre AS material, m.codigo, r.cantidad,
           COALESCE(t.nombre,'—') AS tecnico, r.motivo, r.registrado_por
    FROM reingresos r
    JOIN materiales m ON m.id = r.material_id
    LEFT JOIN tecnicos t ON t.id = r.tecnico_id
    ORDER BY r.id DESC LIMIT 50
");
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-rotate-left me-2" style="color:#10b981;"></i>
                Reingreso de Material
            </h2>
            <p class="text-secondary mb-0">Registro de materiales devueltos por técnicos</p>
        </div>
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
        <div class="col-lg-5">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4">
                    <i class="fa-solid fa-clipboard-list me-2 text-success"></i>
                    Registrar Devolución
                </h5>
                <form method="POST" id="formReingreso">

                    <!-- Técnico -->
                    <div class="mb-3">
                        <label class="form-label">Técnico que devuelve</label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            <?php
                            $tecnicos->data_seek(0);
                            while ($t = $tecnicos->fetch_assoc()):
                            ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                                <?= $t['cargo'] ? ' — '.$t['cargo'] : '' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-3">
                        <label class="form-label">Motivo de Devolución *</label>
                        <select name="motivo" class="form-select" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="Trabajo no ejecutado">Trabajo no ejecutado</option>
                            <option value="Material sobrante">Material sobrante</option>
                            <option value="Material defectuoso">Material defectuoso</option>
                            <option value="Cambio de especificación">Cambio de especificación</option>
                            <option value="Cancelación de orden">Cancelación de orden de trabajo</option>
                            <option value="Error de despacho">Error de despacho</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <!-- Materiales dinámicos -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0 fw-semibold">
                                <i class="fa-solid fa-boxes-stacked me-1"></i>
                                Materiales a devolver *
                            </label>
                            <button type="button" class="btn btn-success btn-sm btn-custom"
                                    onclick="agregarFila('tbodyReingreso')">
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
                                <tbody id="tbodyReingreso"></tbody>
                            </table>
                        </div>
                        <p id="sinFilasReingreso" class="text-secondary text-center py-2"
                           style="font-size:13px;">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Haz clic en "Agregar" para añadir materiales
                        </p>
                    </div>

                    <!-- Observación -->
                    <div class="mb-3">
                        <label class="form-label">Observaciones adicionales</label>
                        <textarea name="observacion" class="form-control" rows="2"
                                  placeholder="Detalles adicionales..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-custom w-100"
                            id="btnGuardarReingreso" disabled>
                        <i class="fa-solid fa-rotate-left me-2"></i>Registrar Devolución
                    </button>
                </form>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="col-lg-7">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4">
                    <i class="fa-solid fa-list-check me-2 text-success"></i>
                    Historial de Reingresos
                </h5>
                <div class="table-responsive">
                    <table id="tablaReingresos" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th><th>Material</th><th>Cant.</th>
                                <th>Técnico</th><th>Motivo</th><th>Por</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = $historial->fetch_assoc()): ?>
                        <tr>
                            <td><small><?= $r['fecha'] ? date('d/m/Y H:i', strtotime($r['fecha'])) : '—' ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($r['material']) ?></strong>
                                <br><small class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '') ?></small>
                            </td>
                            <td><span class="badge bg-success p-2">+<?= $r['cantidad'] ?></span></td>
                            <td><?= htmlspecialchars($r['tecnico']) ?></td>
                            <td><small><?= htmlspecialchars($r['motivo']) ?></small></td>
                            <td><code style="color:#60a5fa;font-size:11px;"><?= htmlspecialchars($r['registrado_por']) ?></code></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript compartido con salida_material.php -->
<script>
$(document).ready(()=>{
    $('#tablaReingresos').DataTable({responsive:true,pageLength:10,language:dtLang,order:[[0,'desc']]});
    agregarFila('tbodyReingreso');
});

document.getElementById('formReingreso').addEventListener('submit',function(e){
    e.preventDefault();
    const validas=[...document.querySelectorAll('#tbodyReingreso .fila-mat')].filter(tr=>{
        return tr.querySelector('.inp-id').value>0 &&
               parseFloat(tr.querySelector('.inp-cant').value)>0;
    });
    if(!validas.length){Swal.fire('Aviso','Agrega al menos un material.','warning');return;}
    Swal.fire({
        title:'¿Registrar devolución?',
        text:`Stock de ${validas.length} material(es) será actualizado.`,
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#22c55e',cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, registrar',cancelButtonText:'Revisar'
    }).then(r=>{if(r.isConfirmed)this.submit();});
});
</script>

<style>
.ac-list div:last-child{border-bottom:none;}
body.light-mode .ac-list{background:white!important;border-color:#e2e8f0!important;}
body.light-mode .ac-list div{color:#0f172a!important;}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ============================================================
// ENTRADA DE MATERIAL
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

$materiales = $conn->query("SELECT id, nombre, stock FROM materiales WHERE activo = 1 ORDER BY nombre ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $material_id = intval($_POST['material_id'] ?? 0);
    $cantidad    = intval($_POST['cantidad']    ?? 0);
    $observacion = trim($_POST['observacion']   ?? '');
    $usuario     = $_SESSION['usuario'];

    if ($material_id <= 0 || $cantidad <= 0) {
        $msg = 'Selecciona un material e ingresa una cantidad válida.';
        $tipo_msg = 'error';
    } else {
        // Prepared statement — sin SQL injection
        $stmt = $conn->prepare(
            "INSERT INTO entradas (material_id, cantidad, observacion, usuario)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('iiss', $material_id, $cantidad, $observacion, $usuario);

        if ($stmt->execute()) {
            $stmt->close();

            // Actualizar stock
            $stmt2 = $conn->prepare("UPDATE materiales SET stock = stock + ? WHERE id = ?");
            $stmt2->bind_param('ii', $cantidad, $material_id);
            $stmt2->execute();
            $stmt2->close();

            audit_log($conn, 'ENTRADA_MATERIAL', "Material ID $material_id, cantidad $cantidad");

            $msg = 'Entrada registrada correctamente.';
            $tipo_msg = 'success';
        } else {
            $stmt->close();
            $msg = 'Error al registrar la entrada.';
            $tipo_msg = 'error';
        }
    }
}
?>

<div class="container-fluid">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">
            <i class="fa-solid fa-arrow-down-to-line me-2 text-success"></i>
            Entrada de Material
        </h2>
        <p class="text-secondary">Registro de ingresos al almacén</p>
    </div>

    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        Swal.fire({
            title: '<?= $tipo_msg==='success'?'Correcto':'Error' ?>',
            text:  '<?= addslashes($msg) ?>',
            icon:  '<?= $tipo_msg ?>',
            confirmButtonColor:'#3b82f6', timer:3500, timerProgressBar:true
        });
    });
    </script>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-plus me-2 text-success"></i>Registrar Entrada</h5>
                <form method="POST">

                    <div class="mb-3">
                        <label class="form-label">Material *</label>
                        <select name="material_id" class="form-select" required id="selectMat">
                            <option value="">Seleccionar material...</option>
                            <?php while ($m = $materiales->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" data-stock="<?= $m['stock'] ?>">
                                <?= htmlspecialchars($m['nombre']) ?> — Stock: <?= $m['stock'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div id="infoStock" class="mb-3" style="display:none;">
                        <div class="alert-card info" style="padding:12px;">
                            <i class="fa-solid fa-cube" style="color:#3b82f6;"></i>
                            <div><strong>Stock actual:</strong> <span id="stockVal"></span> unidades</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" class="form-control" min="1" required placeholder="0">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Observación</label>
                        <textarea name="observacion" class="form-control" rows="3" placeholder="Proveedor, número de guía, etc."></textarea>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);text-align:center;">
                                <small class="text-secondary">Fecha</small>
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

                    <button type="submit" class="btn btn-success btn-custom w-100 mt-4">
                        <i class="fa-solid fa-arrow-down-to-line me-2"></i>Registrar Entrada
                    </button>
                </form>
            </div>
        </div>

        <!-- Últimas entradas -->
        <div class="col-lg-6">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-list me-2 text-secondary"></i>Últimas Entradas</h5>
                <div class="table-responsive">
                    <table id="tablaEntradas" class="table table-hover align-middle">
                        <thead>
                            <tr><th>Fecha</th><th>Material</th><th>Cantidad</th><th>Registrado por</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $ult = $conn->query("
                            SELECT e.*, m.nombre AS material
                            FROM entradas e JOIN materiales m ON m.id=e.material_id
                            ORDER BY e.id DESC LIMIT 20
                        ");
                        while ($e = $ult->fetch_assoc()):
                        ?>
                        <tr>
                            <td><small><?= isset($e['fecha']) ? date('d/m/Y H:i', strtotime($e['fecha'])) : '—' ?></small></td>
                            <td><strong><?= htmlspecialchars($e['material']) ?></strong></td>
                            <td><span class="badge bg-success p-2">+<?= $e['cantidad'] ?></span></td>
                            <td><code style="color:#60a5fa;"><?= htmlspecialchars($e['usuario'] ?? '—') ?></code></td>
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
$(document).ready(function(){
    $('#tablaEntradas').DataTable({ responsive:true, pageLength:8, language:dtLang, order:[[0,'desc']] });
});
document.getElementById('selectMat').addEventListener('change', function(){
    const s = this.options[this.selectedIndex].dataset.stock;
    if(s !== undefined && this.value){ document.getElementById('stockVal').textContent=s; document.getElementById('infoStock').style.display='block'; }
    else { document.getElementById('infoStock').style.display='none'; }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ============================================================
// SALIDA DE MATERIAL
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

$materiales = $conn->query("SELECT id, nombre, stock FROM materiales WHERE activo = 1 AND stock > 0 ORDER BY nombre ASC");
$tecnicos   = $conn->query("SELECT id, nombre, area FROM tecnicos ORDER BY nombre ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $material_id = intval($_POST['material_id'] ?? 0);
    $tecnico_id  = intval($_POST['tecnico_id']  ?? 0) ?: null;
    $cantidad    = intval($_POST['cantidad']    ?? 0);
    $observacion = trim($_POST['observacion']   ?? '');
    $usuario     = $_SESSION['usuario'];

    if ($material_id <= 0 || $cantidad <= 0) {
        $msg = 'Selecciona un material e ingresa una cantidad válida.';
        $tipo_msg = 'error';
    } else {
        // Verificar stock disponible con prepared statement
        $chk = $conn->prepare("SELECT stock FROM materiales WHERE id=? LIMIT 1");
        $chk->bind_param('i', $material_id);
        $chk->execute();
        $stock = $chk->get_result()->fetch_assoc()['stock'] ?? 0;
        $chk->close();

        if ($cantidad > $stock) {
            $msg = "Stock insuficiente. Disponible: $stock unidades.";
            $tipo_msg = 'error';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO salidas (material_id, tecnico_id, cantidad, observacion, usuario)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iiiss', $material_id, $tecnico_id, $cantidad, $observacion, $usuario);

            if ($stmt->execute()) {
                $stmt->close();

                $stmt2 = $conn->prepare("UPDATE materiales SET stock = stock - ? WHERE id = ?");
                $stmt2->bind_param('ii', $cantidad, $material_id);
                $stmt2->execute();
                $stmt2->close();

                audit_log($conn, 'SALIDA_MATERIAL', "Material ID $material_id, cantidad $cantidad, técnico ID $tecnico_id");

                $msg = 'Salida registrada correctamente.';
                $tipo_msg = 'success';
            } else {
                $stmt->close();
                $msg = 'Error al registrar la salida.';
                $tipo_msg = 'error';
            }
        }
    }
}
?>

<div class="container-fluid">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">
            <i class="fa-solid fa-arrow-up-from-line me-2 text-danger"></i>
            Salida de Material
        </h2>
        <p class="text-secondary">Registro de materiales despachados a técnicos</p>
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
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-arrow-up me-2 text-danger"></i>Registrar Salida</h5>
                <form method="POST">

                    <div class="mb-3">
                        <label class="form-label">Material *</label>
                        <select name="material_id" class="form-select" required id="selectMatSal">
                            <option value="">Seleccionar material...</option>
                            <?php while ($m = $materiales->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" data-stock="<?= $m['stock'] ?>">
                                <?= htmlspecialchars($m['nombre']) ?> — Stock: <?= $m['stock'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div id="infoStockSal" class="mb-3" style="display:none;">
                        <div class="alert-card warning" style="padding:12px;">
                            <i class="fa-solid fa-cube" style="color:#f59e0b;"></i>
                            <div><strong>Stock disponible:</strong> <span id="stockValSal"></span> unidades</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Técnico Responsable</label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">Sin asignar</option>
                            <?php
                            $tecnicos->data_seek(0);
                            while ($t = $tecnicos->fetch_assoc()):
                            ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                                <?= $t['area'] ? '— ' . htmlspecialchars($t['area']) : '' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" class="form-control" min="1" required placeholder="0" id="inputCantSal">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Observación</label>
                        <textarea name="observacion" class="form-control" rows="3" placeholder="Orden de trabajo, proyecto, etc."></textarea>
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

                    <button type="submit" class="btn btn-danger btn-custom w-100 mt-4">
                        <i class="fa-solid fa-arrow-up-from-line me-2"></i>Registrar Salida
                    </button>
                </form>
            </div>
        </div>

        <!-- Últimas salidas -->
        <div class="col-lg-6">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-list me-2 text-secondary"></i>Últimas Salidas</h5>
                <div class="table-responsive">
                    <table id="tablaSalidas" class="table table-hover align-middle">
                        <thead>
                            <tr><th>Fecha</th><th>Material</th><th>Cant.</th><th>Técnico</th><th>Por</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $ult = $conn->query("
                            SELECT s.*, m.nombre AS material, COALESCE(t.nombre,'—') AS tecnico
                            FROM salidas s
                            JOIN materiales m ON m.id=s.material_id
                            LEFT JOIN tecnicos t ON t.id=s.tecnico_id
                            ORDER BY s.id DESC LIMIT 20
                        ");
                        while ($s = $ult->fetch_assoc()):
                        ?>
                        <tr>
                            <td><small><?= isset($s['fecha']) ? date('d/m/Y H:i', strtotime($s['fecha'])) : '—' ?></small></td>
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
$(document).ready(function(){
    $('#tablaSalidas').DataTable({ responsive:true, pageLength:8, language:dtLang, order:[[0,'desc']] });
});
document.getElementById('selectMatSal').addEventListener('change', function(){
    const s = this.options[this.selectedIndex].dataset.stock;
    if(s !== undefined && this.value){
        document.getElementById('stockValSal').textContent = s;
        document.getElementById('infoStockSal').style.display = 'block';
        document.getElementById('inputCantSal').max = s;
    } else {
        document.getElementById('infoStockSal').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ============================================================
// REINGRESO DE MATERIAL — devoluciones de técnicos
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg      = '';
$tipo_msg = '';

// ============================================================
// REGISTRAR REINGRESO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $material_id = intval($_POST['material_id'] ?? 0);
    $tecnico_id  = intval($_POST['tecnico_id']  ?? 0) ?: null;
    $cantidad    = intval($_POST['cantidad']     ?? 0);
    $motivo      = trim($_POST['motivo']         ?? '');
    $observacion = trim($_POST['observacion']    ?? '');
    $registrado  = $_SESSION['usuario'];

    if ($material_id <= 0 || $cantidad <= 0 || empty($motivo)) {
        $msg = 'Material, cantidad y motivo son obligatorios.';
        $tipo_msg = 'error';
    } else {
        // Insertar reingreso
        $stmt = $conn->prepare(
            "INSERT INTO reingresos (material_id, tecnico_id, cantidad, motivo, observacion, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iiisss', $material_id, $tecnico_id, $cantidad, $motivo, $observacion, $registrado);

        if ($stmt->execute()) {
            $stmt->close();

            // Aumentar stock
            $stmt2 = $conn->prepare("UPDATE materiales SET stock = stock + ? WHERE id = ?");
            $stmt2->bind_param('ii', $cantidad, $material_id);
            $stmt2->execute();
            $stmt2->close();

            audit_log($conn, 'REINGRESO_MATERIAL', "Material ID $material_id, cantidad $cantidad, motivo: $motivo");

            $msg = "Reingreso registrado correctamente. Stock actualizado.";
            $tipo_msg = 'success';
        } else {
            $stmt->close();
            $msg = 'Error al registrar reingreso.';
            $tipo_msg = 'error';
        }
    }
}

// ============================================================
// DATOS PARA FORMULARIO
// ============================================================
$materiales = $conn->query("SELECT id, nombre, stock FROM materiales WHERE activo = 1 ORDER BY nombre ASC");
$tecnicos   = $conn->query("SELECT id, nombre, area FROM tecnicos ORDER BY nombre ASC");

// ============================================================
// HISTORIAL DE REINGRESOS
// ============================================================
$historial = $conn->query("
    SELECT r.*, m.nombre AS material, m.codigo,
           COALESCE(t.nombre, '—') AS tecnico
    FROM reingresos r
    JOIN materiales m ON m.id = r.material_id
    LEFT JOIN tecnicos t ON t.id = r.tecnico_id
    ORDER BY r.fecha DESC
    LIMIT 100
");
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-rotate-left me-2" style="color:#10b981;"></i>
                Reingreso de Material
            </h2>
            <p class="text-secondary mb-0">Registro de materiales devueltos por técnicos</p>
        </div>
    </div>

    <!-- ALERTA -->
    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        Swal.fire({
            title: '<?= $tipo_msg === 'success' ? 'Correcto' : 'Error' ?>',
            text:  '<?= addslashes($msg) ?>',
            icon:  '<?= $tipo_msg ?>',
            confirmButtonColor:'#3b82f6',
            timer:3500, timerProgressBar:true
        });
    });
    </script>
    <?php endif; ?>

    <div class="row g-4">

        <!-- FORMULARIO -->
        <div class="col-lg-5">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4">
                    <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>
                    Nuevo Reingreso
                </h5>
                <form method="POST">

                    <!-- Material -->
                    <div class="mb-3">
                        <label class="form-label">Material *</label>
                        <select name="material_id" class="form-select" required id="selectMaterial">
                            <option value="">Seleccionar material...</option>
                            <?php while ($m = $materiales->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" data-stock="<?= $m['stock'] ?>">
                                <?= htmlspecialchars($m['nombre']) ?> — Stock: <?= $m['stock'] ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Stock actual (info) -->
                    <div class="mb-3" id="stockInfo" style="display:none;">
                        <div class="alert-card info">
                            <i class="fa-solid fa-cube" style="color:#3b82f6;"></i>
                            <div>
                                <strong>Stock actual:</strong>
                                <span id="stockActual"></span> unidades
                            </div>
                        </div>
                    </div>

                    <!-- Técnico -->
                    <div class="mb-3">
                        <label class="form-label">Técnico que devuelve <small class="text-secondary">(opcional)</small></label>
                        <select name="tecnico_id" class="form-select">
                            <option value="">Sin técnico asignado</option>
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

                    <!-- Cantidad -->
                    <div class="mb-3">
                        <label class="form-label">Cantidad a Reingresar *</label>
                        <input type="number" name="cantidad" class="form-control"
                               min="1" placeholder="0" required>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-3">
                        <label class="form-label">Motivo de Devolución *</label>
                        <select name="motivo" class="form-select" required>
                            <option value="">Seleccionar motivo...</option>
                            <option value="Trabajo no ejecutado">Trabajo no ejecutado</option>
                            <option value="Material sobrante">Material sobrante</option>
                            <option value="Material defectuoso">Material defectuoso — reposición</option>
                            <option value="Cambio de especificación">Cambio de especificación</option>
                            <option value="Cancelación de orden">Cancelación de orden de trabajo</option>
                            <option value="Error de despacho">Error de despacho</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-4">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observacion" class="form-control" rows="3"
                                  placeholder="Detalles adicionales..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-custom w-100">
                        <i class="fa-solid fa-rotate-left me-2"></i>
                        Registrar Reingreso
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
                                <th>Fecha</th>
                                <th>Material</th>
                                <th>Cantidad</th>
                                <th>Técnico</th>
                                <th>Motivo</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = $historial->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($r['fecha'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['material']) ?></strong>
                                <br><small class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-success p-2">+<?= $r['cantidad'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($r['tecnico']) ?></td>
                            <td>
                                <small><?= htmlspecialchars($r['motivo']) ?></small>
                            </td>
                            <td>
                                <code style="color:#60a5fa;"><?= htmlspecialchars($r['registrado_por']) ?></code>
                            </td>
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
    $('#tablaReingresos').DataTable({ responsive:true, pageLength:10, language:dtLang, order:[[0,'desc']] });
});

// Mostrar stock actual al seleccionar material
document.getElementById('selectMaterial').addEventListener('change', function(){
    const opt   = this.options[this.selectedIndex];
    const stock = opt.dataset.stock;
    if (stock !== undefined && this.value) {
        document.getElementById('stockActual').textContent = stock;
        document.getElementById('stockInfo').style.display = 'block';
    } else {
        document.getElementById('stockInfo').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

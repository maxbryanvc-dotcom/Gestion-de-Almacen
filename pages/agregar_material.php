<?php
// ============================================================
// AGREGAR MATERIAL
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo  = trim($_POST['codigo']  ?? '');
    $nombre  = trim($_POST['nombre']  ?? '');
    $unidad  = trim($_POST['unidad']  ?? '');
    $stock   = intval($_POST['stock'] ?? 0);

    if (empty($codigo) || empty($nombre) || empty($unidad)) {
        $msg = 'Código, nombre y unidad son obligatorios.';
        $tipo_msg = 'error';
    } else {
        // Verificar duplicado con prepared statement
        $chk = $conn->prepare("SELECT id FROM materiales WHERE codigo = ? LIMIT 1");
        $chk->bind_param('s', $codigo);
        $chk->execute();
        $chk->store_result();
        $duplicado = $chk->num_rows > 0;
        $chk->close();

        if ($duplicado) {
            $msg = "El código '$codigo' ya está registrado.";
            $tipo_msg = 'error';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO materiales (codigo, nombre, unidad, stock) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('sssi', $codigo, $nombre, $unidad, $stock);

            if ($stmt->execute()) {
                audit_log($conn, 'AGREGAR_MATERIAL', "Código: $codigo, Nombre: $nombre");
                $msg = "Material '$nombre' agregado correctamente.";
                $tipo_msg = 'success';
                // Limpiar campos al éxito
                $codigo = $nombre = $unidad = '';
                $stock = 0;
            } else {
                $msg = 'Error al guardar el material.';
                $tipo_msg = 'error';
            }
            $stmt->close();
        }
    }
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-plus-circle me-2 text-primary"></i>
                Agregar Material
            </h2>
            <p class="text-secondary mb-0">Registrar nuevo material al inventario</p>
        </div>
        <a href="materiales.php" class="btn btn-secondary btn-custom">
            <i class="fa-solid fa-arrow-left me-2"></i>Volver
        </a>
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

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-dashboard">
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-box me-2 text-primary"></i>Datos del Material</h5>
                <form method="POST">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código *</label>
                            <input type="text" name="codigo" class="form-control"
                                   value="<?= htmlspecialchars($codigo ?? '') ?>"
                                   placeholder="MAT-001" required maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad de medida *</label>
                            <select name="unidad" class="form-select" required>
                                <option value="" <?= empty($unidad??'') ? 'selected':'' ?>>Seleccionar...</option>
                                <?php
                                $unidades = ['Unidad','Metro','Kilogramo','Litro','Rollo','Par','Caja','Bolsa','Juego','Galón'];
                                foreach ($unidades as $u):
                                ?>
                                <option value="<?= $u ?>" <?= ($unidad??'')===$u?'selected':'' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nombre del Material *</label>
                            <input type="text" name="nombre" class="form-control"
                                   value="<?= htmlspecialchars($nombre ?? '') ?>"
                                   placeholder="Cable NYY 2x10mm²" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock inicial</label>
                            <input type="number" name="stock" class="form-control"
                                   value="<?= intval($stock ?? 0) ?>" min="0" placeholder="0">
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-primary btn-custom flex-fill">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Material
                        </button>
                        <a href="materiales.php" class="btn btn-secondary btn-custom">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

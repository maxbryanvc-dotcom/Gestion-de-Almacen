<?php
// ============================================================
// EDITAR MATERIAL
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<script>document.addEventListener("DOMContentLoaded",()=>{ Swal.fire("Error","ID inválido","error").then(()=>{ window.location="materiales.php"; }); });</script>';
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// Cargar material con prepared statement
$stmt = $conn->prepare("SELECT * FROM materiales WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
    echo '<script>document.addEventListener("DOMContentLoaded",()=>{ Swal.fire("Error","Material no encontrado","error").then(()=>{ window.location="materiales.php"; }); });</script>';
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

$msg = $tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $unidad = trim($_POST['unidad'] ?? '');
    $stock  = intval($_POST['stock'] ?? 0);

    if (empty($codigo) || empty($nombre) || empty($unidad)) {
        $msg = 'Todos los campos son obligatorios.';
        $tipo_msg = 'error';
    } else {
        // Verificar duplicado excluyendo el mismo registro
        $chk = $conn->prepare("SELECT id FROM materiales WHERE codigo=? AND id != ? LIMIT 1");
        $chk->bind_param('si', $codigo, $id);
        $chk->execute();
        $chk->store_result();
        $duplicado = $chk->num_rows > 0;
        $chk->close();

        if ($duplicado) {
            $msg = "El código '$codigo' ya está en uso por otro material.";
            $tipo_msg = 'error';
        } else {
            $upd = $conn->prepare(
                "UPDATE materiales SET codigo=?, nombre=?, unidad=?, stock=? WHERE id=?"
            );
            $upd->bind_param('sssii', $codigo, $nombre, $unidad, $stock, $id);

            if ($upd->execute()) {
                audit_log($conn, 'EDITAR_MATERIAL', "ID $id: código=$codigo, nombre=$nombre, stock=$stock");
                $msg = 'Material actualizado correctamente.';
                $tipo_msg = 'success';
                // Refrescar datos
                $fila['codigo'] = $codigo;
                $fila['nombre'] = $nombre;
                $fila['unidad'] = $unidad;
                $fila['stock']  = $stock;
            } else {
                $msg = 'Error al actualizar el material.';
                $tipo_msg = 'error';
            }
            $upd->close();
        }
    }
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-pen me-2 text-warning"></i>
                Editar Material
            </h2>
            <p class="text-secondary mb-0">Modificar datos del material #<?= $id ?></p>
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
                <h5 class="fw-bold mb-4"><i class="fa-solid fa-box me-2 text-warning"></i>Datos del Material</h5>
                <form method="POST">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Código *</label>
                            <input type="text" name="codigo" class="form-control"
                                   value="<?= htmlspecialchars($fila['codigo']) ?>"
                                   required maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad de medida *</label>
                            <select name="unidad" class="form-select" required>
                                <?php
                                $unidades = ['Unidad','Metro','Kilogramo','Litro','Rollo','Par','Caja','Bolsa','Juego','Galón'];
                                foreach ($unidades as $u):
                                ?>
                                <option value="<?= $u ?>" <?= $fila['unidad']===$u?'selected':'' ?>><?= $u ?></option>
                                <?php endforeach; ?>
                                <option value="<?= htmlspecialchars($fila['unidad']) ?>"
                                    <?= !in_array($fila['unidad'],$unidades)?'selected':'' ?>>
                                    <?= htmlspecialchars($fila['unidad']) ?>
                                </option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nombre del Material *</label>
                            <input type="text" name="nombre" class="form-control"
                                   value="<?= htmlspecialchars($fila['nombre']) ?>"
                                   required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock actual</label>
                            <input type="number" name="stock" class="form-control"
                                   value="<?= intval($fila['stock']) ?>" min="0">
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 h-100 d-flex align-items-center"
                                 style="background:rgba(255,255,255,0.03);">
                                <div>
                                    <small class="text-secondary">Estado actual</small>
                                    <div class="mt-1"><?= stock_badge((int)$fila['stock']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-warning btn-custom flex-fill">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Actualizar Material
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

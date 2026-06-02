<?php
// ============================================================
// GESTIÓN DE TÉCNICOS
// Todas las acciones con header() ANTES de cargar layout.php
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

$msg = $tipo_msg = '';

// ── CREAR ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $nombre  = trim($_POST['nombre']  ?? '');
    $dni     = trim($_POST['dni']     ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $area    = trim($_POST['area']    ?? '');
    $cargo   = trim($_POST['cargo']   ?? '');

    if (empty($nombre)) {
        $msg = 'El nombre es obligatorio.'; $tipo_msg = 'error';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO tecnicos (nombre, dni, celular, area, cargo) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('sssss', $nombre, $dni, $celular, $area, $cargo);
        if ($stmt->execute()) {
            audit_log($conn, 'CREAR_TECNICO', "Técnico: $nombre");
            $msg = "Técnico '$nombre' registrado."; $tipo_msg = 'success';
        } else {
            $msg = 'Error al guardar.'; $tipo_msg = 'error';
        }
        $stmt->close();
    }
}

// ── EDITAR ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $id      = intval($_POST['id']     ?? 0);
    $nombre  = trim($_POST['nombre']   ?? '');
    $dni     = trim($_POST['dni']      ?? '');
    $celular = trim($_POST['celular']  ?? '');
    $area    = trim($_POST['area']     ?? '');
    $cargo   = trim($_POST['cargo']    ?? '');

    if ($id > 0 && !empty($nombre)) {
        $stmt = $conn->prepare(
            "UPDATE tecnicos SET nombre=?,dni=?,celular=?,area=?,cargo=? WHERE id=?"
        );
        $stmt->bind_param('sssssi', $nombre, $dni, $celular, $area, $cargo, $id);
        $stmt->execute(); $stmt->close();
        audit_log($conn, 'EDITAR_TECNICO', "ID $id: $nombre");
        $msg = 'Técnico actualizado.'; $tipo_msg = 'success';
    }
}

// ── ELIMINAR — va ANTES de layout.php para poder redirigir ───
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);

    // Obtener nombre para el log
    $stmtN = $conn->prepare("SELECT nombre FROM tecnicos WHERE id=? LIMIT 1");
    $stmtN->bind_param('i', $id);
    $stmtN->execute();
    $nomTec = $stmtN->get_result()->fetch_assoc()['nombre'] ?? 'desconocido';
    $stmtN->close();

    // Desvincular registros históricos (poner NULL en tecnico_id)
    foreach (['requerimientos','salidas','ordenes_trabajo','reingresos'] as $tabla) {
        $st = $conn->prepare("UPDATE `$tabla` SET tecnico_id = NULL WHERE tecnico_id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
    }

    // Eliminar técnico
    $stmt = $conn->prepare("DELETE FROM tecnicos WHERE id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        audit_log($conn, 'ELIMINAR_TECNICO', "ID $id: $nomTec — renunció");
        header("Location: " . BASE_URL . "/pages/tecnicos.php?msg=eliminado");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['msg'])) {
    $msg = 'Técnico eliminado del sistema.'; $tipo_msg = 'success';
}

// ── LISTAR ───────────────────────────────────────────────────
$tecnicos = $conn->query("SELECT * FROM tecnicos ORDER BY nombre ASC");

// ── LAYOUT — se carga DESPUÉS de todas las acciones ──────────
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-hard-hat me-2 text-warning"></i>Técnicos
            </h2>
            <p class="text-secondary mb-0">Personal técnico asociado a requerimientos</p>
        </div>
        <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="fa-solid fa-plus me-2"></i>Nuevo Técnico
        </button>
    </div>

    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Correcto":"Error" ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:3000,timerProgressBar:true,confirmButtonColor:'#3b82f6'
    }));
    </script>
    <?php endif; ?>

    <div class="card-dashboard">
        <div class="table-responsive">
            <table id="tablaTecnicos" class="table table-hover align-middle">
                <thead>
                    <tr><th>#</th><th>Nombre</th><th>DNI</th><th>Celular</th><th>Área</th><th>Cargo</th><th>Reqs.</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php $n = 1; while ($t = $tecnicos->fetch_assoc()):
                    $nReqs = $conn->query("SELECT COUNT(*) c FROM requerimientos WHERE tecnico_id={$t['id']}")->fetch_assoc()['c'];
                    $nOTs  = $conn->query("SELECT COUNT(*) c FROM ordenes_trabajo WHERE tecnico_id={$t['id']}")->fetch_assoc()['c'];
                    $total = $nReqs + $nOTs;
                ?>
                <tr>
                    <td><strong><?= $n++ ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($t['nombre'],0,1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($t['dni'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($t['celular'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($t['area'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($t['cargo'] ?? '—') ?></td>
                    <td>
                        <span class="badge bg-primary" title="Requerimientos"><?= $nReqs ?> req</span>
                        <span class="badge bg-secondary ms-1" title="OTs"><?= $nOTs ?> OT</span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-warning btn-sm"
                                onclick='abrirEditar(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-danger btn-sm"
                                onclick="eliminarTecnico(<?= $t['id'] ?>, '<?= htmlspecialchars($t['nombre']) ?>', <?= $total ?>)"
                                title="Eliminar técnico">
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR -->
<div class="modal fade" id="modalCrear" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-plus me-2 text-primary"></i>Nuevo Técnico</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
    <input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nombre completo *</label>
                <input type="text" name="nombre" class="form-control" required maxlength="100" placeholder="Juan Pérez García">
            </div>
            <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" class="form-control" maxlength="20" placeholder="12345678">
            </div>
            <div class="col-md-6">
                <label class="form-label">Celular</label>
                <input type="text" name="celular" class="form-control" maxlength="20" placeholder="987654321">
            </div>
            <div class="col-md-6">
                <label class="form-label">Área</label>
                <input type="text" name="area" class="form-control" maxlength="100" placeholder="Instalaciones">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cargo</label>
                <input type="text" name="cargo" class="form-control" maxlength="100" placeholder="Técnico Electricista">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-custom"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar</button>
    </div>
    </form>
</div></div></div>

<!-- MODAL EDITAR -->
<div class="modal fade" id="modalEditar" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-pen me-2 text-warning"></i>Editar Técnico</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
    <input type="hidden" name="accion" value="editar">
    <input type="hidden" name="id" id="e_id">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nombre completo *</label>
                <input type="text" name="nombre" id="e_nombre" class="form-control" required maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" id="e_dni" class="form-control" maxlength="20">
            </div>
            <div class="col-md-6">
                <label class="form-label">Celular</label>
                <input type="text" name="celular" id="e_celular" class="form-control" maxlength="20">
            </div>
            <div class="col-md-6">
                <label class="form-label">Área</label>
                <input type="text" name="area" id="e_area" class="form-control" maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label">Cargo</label>
                <input type="text" name="cargo" id="e_cargo" class="form-control" maxlength="100">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning btn-custom"><i class="fa-solid fa-floppy-disk me-2"></i>Actualizar</button>
    </div>
    </form>
</div></div></div>

<script>
$(document).ready(()=>$('#tablaTecnicos').DataTable({responsive:true,pageLength:10,language:dtLang}));

function abrirEditar(t){
    document.getElementById('e_id').value     = t.id;
    document.getElementById('e_nombre').value = t.nombre;
    document.getElementById('e_dni').value    = t.dni    || '';
    document.getElementById('e_celular').value= t.celular|| '';
    document.getElementById('e_area').value   = t.area   || '';
    document.getElementById('e_cargo').value  = t.cargo  || '';
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function eliminarTecnico(id, nombre, totalRegistros){
    const tieneHistorial = totalRegistros > 0;
    Swal.fire({
        title: `¿Eliminar a ${nombre}?`,
        html: tieneHistorial
            ? `<p>Este técnico tiene <strong>${totalRegistros} registro(s)</strong> en el sistema.</p>
               <p class="text-warning" style="font-size:13px;">
                 <i class="fa-solid fa-triangle-exclamation me-1"></i>
                 Sus requerimientos y órdenes de trabajo quedarán sin técnico asignado,
                 pero el historial <strong>no se perderá</strong>.
               </p>`
            : `<p>El técnico no tiene registros asociados.</p>`,
        icon: tieneHistorial ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor:  '#64748b',
        confirmButtonText:  '<i class="fa-solid fa-user-minus me-1"></i>Sí, eliminar',
        cancelButtonText:   'Cancelar',
    }).then(r => {
        if (r.isConfirmed) {
            window.location.href = 'tecnicos.php?eliminar=' + id;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

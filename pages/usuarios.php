<?php
// ============================================================
// GESTIÓN DE USUARIOS — solo admin
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin']);
require_once __DIR__ . '/../includes/layout.php';

$msg = '';
$tipo_msg = '';

// ============================================================
// CREAR USUARIO
// ============================================================
if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {

    $nombre   = trim($_POST['nombre_completo'] ?? '');
    $dni      = trim($_POST['dni']             ?? '');
    $fnac     = $_POST['fecha_nacimiento']      ?? null;
    $celular  = trim($_POST['celular']          ?? '');
    $usuario  = trim($_POST['usuario']          ?? '');
    $password = $_POST['password']              ?? '';
    $rol      = $_POST['rol']                   ?? 'tecnico';

    if (empty($nombre) || empty($usuario) || empty($password)) {
        $msg = 'Nombre, usuario y contraseña son obligatorios.';
        $tipo_msg = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO usuarios (nombre_completo, dni, fecha_nacimiento, celular, usuario, password, rol, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('sssssss', $nombre, $dni, $fnac, $celular, $usuario, $hash, $rol);

        if ($stmt->execute()) {
            audit_log($conn, 'CREAR_USUARIO', "Creado: $usuario ($rol)");
            $msg = "Usuario '$usuario' creado correctamente.";
            $tipo_msg = 'success';
        } else {
            $msg = 'Error al crear usuario. El nombre de usuario ya puede existir.';
            $tipo_msg = 'error';
        }
        $stmt->close();
    }
}

// ============================================================
// EDITAR USUARIO
// ============================================================
if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {

    $id       = intval($_POST['id']            ?? 0);
    $nombre   = trim($_POST['nombre_completo'] ?? '');
    $dni      = trim($_POST['dni']             ?? '');
    $fnac     = $_POST['fecha_nacimiento']      ?? null;
    $celular  = trim($_POST['celular']          ?? '');
    $usuario  = trim($_POST['usuario']          ?? '');
    $rol      = $_POST['rol']                   ?? 'tecnico';
    $nuevoPass= $_POST['nuevo_password']        ?? '';

    if ($id <= 0 || empty($nombre) || empty($usuario)) {
        $msg = 'Datos incompletos.'; $tipo_msg = 'error';
    } else {
        if (!empty($nuevoPass)) {
            $hash = password_hash($nuevoPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE usuarios SET nombre_completo=?, dni=?, fecha_nacimiento=?, celular=?,
                 usuario=?, password=?, rol=? WHERE id=?"
            );
            $stmt->bind_param('sssssssi', $nombre, $dni, $fnac, $celular, $usuario, $hash, $rol, $id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE usuarios SET nombre_completo=?, dni=?, fecha_nacimiento=?, celular=?,
                 usuario=?, rol=? WHERE id=?"
            );
            $stmt->bind_param('ssssssi', $nombre, $dni, $fnac, $celular, $usuario, $rol, $id);
        }

        if ($stmt->execute()) {
            audit_log($conn, 'EDITAR_USUARIO', "Editado ID $id: $usuario");
            $msg = "Usuario actualizado correctamente."; $tipo_msg = 'success';
        } else {
            $msg = 'Error al actualizar usuario.'; $tipo_msg = 'error';
        }
        $stmt->close();
    }
}

// ============================================================
// ACTIVAR / DESACTIVAR
// ============================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    // No se puede desactivar al propio admin logueado
    if ($id !== (int)$_SESSION['id_usuario']) {
        $conn->prepare("UPDATE usuarios SET estado = 1 - estado WHERE id=?")
             ->bind_param('i', $id) && $conn->execute();
        $stmt2 = $conn->prepare("UPDATE usuarios SET estado = 1 - estado WHERE id=?");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $stmt2->close();
        audit_log($conn, 'TOGGLE_USUARIO', "Toggle estado ID $id");
    }
    header("Location: usuarios.php?msg=toggle");
    exit();
}

// ============================================================
// ELIMINAR USUARIO
// ============================================================
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($id !== (int)$_SESSION['id_usuario']) {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'ELIMINAR_USUARIO', "Eliminado ID $id");
    }
    header("Location: usuarios.php?msg=eliminado");
    exit();
}

// ============================================================
// LISTAR USUARIOS
// ============================================================
$usuarios = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");

// Mensaje de URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'eliminado') { $msg = 'Usuario eliminado.'; $tipo_msg = 'success'; }
    if ($_GET['msg'] === 'toggle')    { $msg = 'Estado actualizado.'; $tipo_msg = 'info'; }
}
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Gestión de Usuarios</h2>
            <p class="text-secondary mb-0">Administración de accesos y roles del sistema</p>
        </div>
        <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="fa-solid fa-plus me-2"></i>Nuevo Usuario
        </button>
    </div>

    <!-- ALERTAS JS -->
    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        Swal.fire({
            title: '<?= $tipo_msg === 'success' ? 'Correcto' : ($tipo_msg === 'error' ? 'Error' : 'Aviso') ?>',
            text:  '<?= addslashes($msg) ?>',
            icon:  '<?= $tipo_msg ?>',
            confirmButtonColor:'#3b82f6', timer:3000, timerProgressBar:true
        });
    });
    </script>
    <?php endif; ?>

    <!-- TABLA -->
    <div class="card-dashboard">

        <div class="mb-4">
            <h5 class="fw-bold mb-1">Lista de Usuarios</h5>
            <small class="text-secondary">Total registrados: <?= $usuarios->num_rows ?></small>
        </div>

        <div class="table-responsive">
            <table id="tablaUsuarios" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre Completo</th>
                        <th>Usuario</th>
                        <th>DNI</th>
                        <th>Celular</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($u = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= $u['id'] ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#6366f1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($u['nombre_completo'] ?: $u['usuario'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($u['nombre_completo'] ?: '—') ?></strong>
                                <?php if ($u['fecha_nacimiento']): ?>
                                <br><small class="text-secondary"><?= date('d/m/Y', strtotime($u['fecha_nacimiento'])) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><code style="color:#60a5fa;"><?= htmlspecialchars($u['usuario']) ?></code></td>
                    <td><?= htmlspecialchars($u['dni'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($u['celular'] ?: '—') ?></td>
                    <td><?= rol_badge($u['rol']) ?></td>
                    <td>
                        <?php if ((int)$u['estado'] === 1): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- Editar -->
                            <button class="btn btn-warning btn-sm"
                                onclick='abrirEditar(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <!-- Toggle estado -->
                            <?php if ($u['id'] !== (int)$_SESSION['id_usuario']): ?>
                            <a href="usuarios.php?toggle=<?= $u['id'] ?>"
                               class="btn btn-<?= $u['estado'] ? 'secondary' : 'success' ?> btn-sm"
                               title="<?= $u['estado'] ? 'Desactivar' : 'Activar' ?>"
                               onclick="return confirm('¿Cambiar estado del usuario?')">
                                <i class="fa-solid fa-<?= $u['estado'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                            </a>
                            <!-- Eliminar -->
                            <button class="btn btn-danger btn-sm"
                                onclick="eliminarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['usuario']) ?>')"
                                title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= MODAL CREAR ================= -->
<div class="modal fade" id="modalCrear" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Nuevo Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
    <input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre Completo *</label>
                <input type="text" name="nombre_completo" class="form-control" placeholder="Juan Pérez García" required maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" class="form-control" placeholder="12345678" maxlength="20">
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Celular</label>
                <input type="text" name="celular" class="form-control" placeholder="987654321" maxlength="20">
            </div>
            <div class="col-md-4">
                <label class="form-label">Usuario (login) *</label>
                <input type="text" name="usuario" class="form-control" placeholder="jperez" required maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label">Contraseña *</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="col-md-4">
                <label class="form-label">Rol *</label>
                <select name="rol" class="form-select" required>
                    <option value="admin">Administrador</option>
                    <option value="almacen" selected>Almacenero</option>
                    <option value="tecnico">Técnico</option>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-custom">
            <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Usuario
        </button>
    </div>
    </form>
</div>
</div>
</div>

<!-- ================= MODAL EDITAR ================= -->
<div class="modal fade" id="modalEditar" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-pen me-2 text-warning"></i>Editar Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST">
    <input type="hidden" name="accion" value="editar">
    <input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre Completo *</label>
                <input type="text" name="nombre_completo" id="edit_nombre" class="form-control" required maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label">DNI</label>
                <input type="text" name="dni" id="edit_dni" class="form-control" maxlength="20">
            </div>
            <div class="col-md-6">
                <label class="form-label">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" id="edit_fnac" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Celular</label>
                <input type="text" name="celular" id="edit_celular" class="form-control" maxlength="20">
            </div>
            <div class="col-md-4">
                <label class="form-label">Usuario (login) *</label>
                <input type="text" name="usuario" id="edit_usuario" class="form-control" required maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nuevo password <small class="text-secondary">(vacío = no cambia)</small></label>
                <input type="password" name="nuevo_password" class="form-control" minlength="6" placeholder="••••••">
            </div>
            <div class="col-md-4">
                <label class="form-label">Rol *</label>
                <select name="rol" id="edit_rol" class="form-select" required>
                    <option value="admin">Administrador</option>
                    <option value="almacen">Almacenero</option>
                    <option value="tecnico">Técnico</option>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-warning btn-custom">
            <i class="fa-solid fa-floppy-disk me-2"></i>Actualizar
        </button>
    </div>
    </form>
</div>
</div>
</div>

<script>
$(document).ready(function(){
    $('#tablaUsuarios').DataTable({ responsive:true, pageLength:10, language:dtLang });
});

function abrirEditar(u){
    document.getElementById('edit_id').value      = u.id;
    document.getElementById('edit_nombre').value  = u.nombre_completo || '';
    document.getElementById('edit_dni').value     = u.dni || '';
    document.getElementById('edit_fnac').value    = u.fecha_nacimiento || '';
    document.getElementById('edit_celular').value = u.celular || '';
    document.getElementById('edit_usuario').value = u.usuario;
    document.getElementById('edit_rol').value     = u.rol;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function eliminarUsuario(id, nombre){
    Swal.fire({
        title:'¿Eliminar usuario?',
        text: `El usuario "${nombre}" será eliminado permanentemente.`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#ef4444',
        cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, eliminar',
        cancelButtonText:'Cancelar'
    }).then(r=>{ if(r.isConfirmed) window.location.href='usuarios.php?eliminar='+id; });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

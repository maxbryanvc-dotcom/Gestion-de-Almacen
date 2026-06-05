<?php
// ============================================================
// GESTIÓN DE MATERIALES
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin','almacen','tecnico']);
require_once __DIR__ . '/../includes/layout.php';

$rolActual = $_SESSION['rol'] ?? 'tecnico';

$sql       = "SELECT * FROM materiales WHERE activo = 1 ORDER BY nombre ASC";
$resultado = $conn->query($sql);
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>
                Gestión de Materiales
            </h2>
            <p class="text-secondary mb-0">Administración del inventario</p>
        </div>
        <?php if (in_array($rolActual, ['admin','almacen'])): ?>
        <a href="agregar_material.php" class="btn btn-primary btn-custom">
            <i class="fa-solid fa-plus me-2"></i>Agregar Material
        </a>
        <?php endif; ?>
    </div>

    <!-- MSG URL -->
    <?php if (isset($_GET['msg'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        const msgs = {
            eliminado:['success','Material eliminado correctamente'],
            editado:  ['success','Material actualizado correctamente'],
            negado:   ['warning','Permiso denegado o código inválido'],
        };
        const m = msgs['<?= htmlspecialchars($_GET['msg']) ?>'];
        if(m) Swal.fire({ title:m[0]==='success'?'Correcto':'Aviso', text:m[1], icon:m[0], timer:3000, timerProgressBar:true, confirmButtonColor:'#3b82f6' });
    });
    </script>
    <?php endif; ?>

    <!-- TABLA -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1">Inventario de Materiales</h5>
                <small class="text-secondary">Lista completa del almacén</small>
            </div>
            <?php if (in_array($rolActual, ['admin','almacen'])): ?>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/exports/exportar_materiales_excel.php" class="btn btn-success btn-sm btn-custom">
                    <i class="fa-solid fa-file-excel me-1"></i>Excel
                </a>
                <a href="generar_pdf.php" class="btn btn-danger btn-sm btn-custom">
                    <i class="fa-solid fa-file-pdf me-1"></i>PDF
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table id="tablaMateriales" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Material</th>
                        <th>Unidad</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <?php if (in_array($rolActual, ['admin','almacen'])): ?>
                        <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php $n = 1; while ($fila = $resultado->fetch_assoc()): ?>
                <?php
                    $stock = (int)$fila['stock'];
                    $badgeHtml = stock_badge($stock);
                ?>
                <tr>
                    <td><strong><?= $n++ ?></strong></td>
                    <td><span class="fw-semibold"><?= htmlspecialchars($fila['codigo']) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:36px;height:36px;border-radius:10px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;color:#3b82f6;flex-shrink:0;">
                                <i class="fa-solid fa-box"></i>
                            </div>
                            <strong><?= htmlspecialchars($fila['nombre']) ?></strong>
                        </div>
                    </td>
                    <td><span class="badge bg-secondary p-2"><?= htmlspecialchars($fila['unidad']) ?></span></td>
                    <td><strong><?= $stock ?></strong></td>
                    <td><?= $badgeHtml ?></td>
                    <?php if (in_array($rolActual, ['admin','almacen'])): ?>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($rolActual === 'admin'): ?>
                                <!-- Admin: acceso directo -->
                                <a href="editar_material.php?id=<?= $fila['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <button class="btn btn-danger btn-sm" onclick="confirmarEliminacion(<?= $fila['id'] ?>)" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <!-- Almacenero: requiere permiso temporal -->
                                <button class="btn btn-warning btn-sm" onclick="solicitarPermiso('editar', <?= $fila['id'] ?>)" title="Editar (requiere permiso)">
                                    <i class="fa-solid fa-pen"></i>
                                    <i class="fa-solid fa-lock fa-xs ms-1 opacity-50"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="solicitarPermiso('eliminar', <?= $fila['id'] ?>)" title="Eliminar (requiere permiso)">
                                    <i class="fa-solid fa-trash"></i>
                                    <i class="fa-solid fa-lock fa-xs ms-1 opacity-50"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ MODAL PERMISO TEMPORAL ============ -->
<div class="modal fade" id="modalPermiso" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">
            <i class="fa-solid fa-shield-halved me-2 text-warning"></i>
            Autorización Requerida
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center">

        <!-- Paso 1: Notificar al admin y esperar -->
        <div id="paso1">
            <div style="width:70px;height:70px;border-radius:20px;background:rgba(245,158,11,0.15);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-bell fa-2x" style="color:#f59e0b;"></i>
            </div>
            <h6 class="fw-bold mb-2">Acción restringida</h6>
            <p class="text-secondary mb-3" style="font-size:13px;">
                Esta acción requiere autorización del administrador.
            </p>
            <!-- Estado de la solicitud -->
            <div id="estadoSolicitud" style="display:none;"
                 class="mb-3 p-3 rounded-3"
                 style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);">
                <i class="fa-solid fa-circle-check text-success me-2"></i>
                <span id="textoEstado" style="font-size:13px;"></span>
            </div>
            <button class="btn btn-warning btn-custom w-100 mb-2" onclick="enviarSolicitud()" id="btnNotificar">
                <i class="fa-solid fa-bell me-2"></i>
                Notificar al Administrador
            </button>
            <button class="btn btn-primary btn-custom w-100 mb-2" onclick="mostrarPaso2()" id="btnTengoCode" style="display:none;">
                <i class="fa-solid fa-key me-2"></i>
                Ya tengo el código
            </button>
            <button type="button" class="btn btn-secondary btn-custom w-100" data-bs-dismiss="modal">
                Cancelar
            </button>
        </div>

        <!-- Paso 2: Ingresar código -->
        <div id="paso2" style="display:none;">
            <div style="width:70px;height:70px;border-radius:20px;background:rgba(59,130,246,0.15);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-lock-open fa-2x" style="color:#3b82f6;"></i>
            </div>
            <h6 class="fw-bold mb-2">Ingresa el código</h6>
            <p class="text-secondary mb-3" style="font-size:13px;">
                El administrador te proporcionó un código de 6 dígitos.
            </p>
            <input type="text" id="inputCodigo"
                   class="form-control text-center fw-bold mb-3"
                   maxlength="6" placeholder="000000"
                   style="font-size:28px;letter-spacing:8px;"
                   oninput="this.value=this.value.replace(/\D/g,'')">
            <div id="errorPermiso" class="text-danger mb-3" style="display:none;font-size:13px;"></div>
            <button class="btn btn-primary btn-custom w-100 mb-2" onclick="verificarPermiso()">
                <i class="fa-solid fa-check me-2"></i>Verificar Código
            </button>
            <button class="btn btn-secondary btn-custom w-100" onclick="mostrarPaso1()">
                Atrás
            </button>
        </div>

    </div>
</div>
</div>
</div>

<!-- PANEL ADMIN: Generar código (solo si es admin) -->
<?php if ($rolActual === 'admin'): ?>
<!-- El admin puede generar códigos para almaceneros desde aquí también -->
<div class="modal fade" id="modalGenerarCodigo" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-key me-2 text-success"></i>Generar Código de Permiso</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Usuario solicitante</label>
            <input type="text" id="gen_solicitante" class="form-control" placeholder="Nombre del almacenero">
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción de la acción</label>
            <input type="text" id="gen_descripcion" class="form-control" value="Edición/eliminación de material">
        </div>
        <button class="btn btn-success btn-custom w-100" onclick="generarCodigo()">
            <i class="fa-solid fa-dice me-2"></i>Generar Código
        </button>
        <div id="codigoGenerado" class="text-center mt-4" style="display:none;">
            <p class="text-secondary mb-2">Código generado (válido <?= PERMISO_MINUTOS ?> min):</p>
            <div style="font-size:42px;font-weight:800;letter-spacing:10px;color:#22c55e;" id="codigoTexto"></div>
            <small class="text-secondary">Comparte solo este código con el almacenero</small>
        </div>
    </div>
</div>
</div>
</div>
<?php endif; ?>

<script>
$(document).ready(function(){
    $('#tablaMateriales').DataTable({
        responsive:true, pageLength:10, language:dtLang
    });
});

let _permisoAccion  = '';
let _permisoRecurso = 0;

function confirmarEliminacion(id){
    Swal.fire({
        title:'¿Eliminar material?', text:'Esta acción no se puede deshacer.',
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#ef4444', cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
    }).then(r=>{ if(r.isConfirmed) window.location.href='eliminar_material.php?id='+id; });
}

function solicitarPermiso(accion, id){
    _permisoAccion  = accion;
    _permisoRecurso = id;
    mostrarPaso1();
    new bootstrap.Modal(document.getElementById('modalPermiso')).show();
}

function mostrarPaso1(){
    document.getElementById('paso1').style.display='block';
    document.getElementById('paso2').style.display='none';
    // Resetear estado
    document.getElementById('estadoSolicitud').style.display='none';
    document.getElementById('btnTengoCode').style.display='none';
    document.getElementById('btnNotificar').style.display='block';
    document.getElementById('btnNotificar').disabled=false;
    document.getElementById('btnNotificar').innerHTML='<i class="fa-solid fa-bell me-2"></i>Notificar al Administrador';
}

// Enviar solicitud de permiso al admin
function enviarSolicitud(){
    const btn = document.getElementById('btnNotificar');
    btn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-2"></i>Enviando...';

    fetch(BASE_URL+'/api/solicitar_permiso.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`accion=crear&tipo=${_permisoAccion==='editar'?'Editar material':'Eliminar material'}&recurso_id=${_permisoRecurso}`
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.ok){
            const est = document.getElementById('estadoSolicitud');
            est.style.display='block';
            est.style.background='rgba(34,197,94,0.1)';
            est.style.border='1px solid rgba(34,197,94,0.2)';
            est.style.borderRadius='12px';
            est.style.padding='12px';
            document.getElementById('textoEstado').innerHTML=
                '<i class="fa-solid fa-circle-check text-success me-2"></i>'+
                '<strong>Solicitud enviada.</strong> El administrador recibio una alerta en su panel.';
            btn.style.display='none';
            document.getElementById('btnTengoCode').style.display='block';
        } else {
            btn.disabled=false;
            btn.innerHTML='<i class="fa-solid fa-bell me-2"></i>Notificar al Administrador';
        }
    });
}
function mostrarPaso2(){ document.getElementById('paso1').style.display='none'; document.getElementById('paso2').style.display='block'; document.getElementById('inputCodigo').focus(); }

function verificarPermiso(){
    const codigo = document.getElementById('inputCodigo').value.trim();
    if(codigo.length !== 6){ mostrarErrorPermiso('El código debe tener 6 dígitos'); return; }

    fetch(BASE_URL + '/api/permiso_temporal.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`accion=verificar&codigo=${codigo}&descripcion=${_permisoAccion}`
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.ok){
            bootstrap.Modal.getInstance(document.getElementById('modalPermiso')).hide();
            if(_permisoAccion === 'editar'){
                window.location.href = 'editar_material.php?id=' + _permisoRecurso;
            } else if(_permisoAccion === 'eliminar'){
                confirmarEliminacionDirecta(_permisoRecurso);
            }
        } else {
            mostrarErrorPermiso(data.msg || 'Código incorrecto o expirado');
        }
    });
}

function mostrarErrorPermiso(msg){
    const el = document.getElementById('errorPermiso');
    el.textContent = msg;
    el.style.display = 'block';
    document.getElementById('inputCodigo').value = '';
    document.getElementById('inputCodigo').focus();
}

function confirmarEliminacionDirecta(id){
    Swal.fire({
        title:'Permiso validado — ¿Eliminar material?',
        text:'Esta acción no se puede deshacer.',
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#ef4444', confirmButtonText:'Eliminar'
    }).then(r=>{ if(r.isConfirmed) window.location.href='eliminar_material.php?id='+id; });
}

<?php if ($rolActual === 'admin'): ?>
function mostrarGeneradorCodigo(){
    new bootstrap.Modal(document.getElementById('modalGenerarCodigo')).show();
}

function generarCodigo(){
    const sol  = document.getElementById('gen_solicitante').value;
    const desc = document.getElementById('gen_descripcion').value;
    fetch(BASE_URL + '/api/permiso_temporal.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`accion=generar&solicitante=${encodeURIComponent(sol)}&descripcion=${encodeURIComponent(desc)}`
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.ok){
            document.getElementById('codigoTexto').textContent = data.codigo;
            document.getElementById('codigoGenerado').style.display = 'block';
        } else {
            Swal.fire('Error', data.msg, 'error');
        }
    });
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

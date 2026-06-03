<?php
// ============================================================
// REINGRESO DE MATERIAL — múltiples materiales
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
        if ($mid > 0 && $cant > 0) $filas_validas[] = ['id'=>$mid,'cant'=>$cant];
    }

    if (empty($motivo)) {
        $msg = 'Selecciona el motivo de devolución.'; $tipo_msg = 'error';
    } elseif (empty($filas_validas)) {
        $msg = 'Agrega al menos un material.'; $tipo_msg = 'error';
    } else {
        $stmtR = $conn->prepare(
            "INSERT INTO reingresos (material_id,tecnico_id,cantidad,motivo,observacion,registrado_por,fecha)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmtU = $conn->prepare("UPDATE materiales SET stock=stock+? WHERE id=?");
        foreach ($filas_validas as $fv) {
            $c = $fv['cant'];
            $stmtR->bind_param('iiissss',$fv['id'],$tecnico_id,$c,$motivo,$observacion,$registrado,$fecha);
            $stmtR->execute();
            $stmtU->bind_param('di',$c,$fv['id']); $stmtU->execute();
        }
        $stmtR->close(); $stmtU->close();
        audit_log($conn,'REINGRESO_MATERIAL',count($filas_validas).' material(es). Motivo: '.$motivo);
        $msg = 'Devolución registrada — '.count($filas_validas).' material(es) reingresado(s).';
        $tipo_msg = 'success';
    }
}

$historial = $conn->query("
    SELECT r.fecha, m.nombre AS material, m.codigo, r.cantidad,
           COALESCE(t.nombre,'—') AS tecnico, r.motivo, r.registrado_por
    FROM reingresos r
    JOIN materiales m ON m.id=r.material_id
    LEFT JOIN tecnicos t ON t.id=r.tecnico_id
    ORDER BY r.id DESC LIMIT 60
");
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-rotate-left me-2" style="color:#10b981;"></i>
                Reingreso de Material
            </h2>
            <p class="text-secondary mb-0">Registro de devoluciones de materiales por técnicos</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Registrado":"Error" ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:4000,timerProgressBar:true,confirmButtonColor:'#10b981'
    }));
    </script>
    <?php endif; ?>

    <!-- FORMULARIO PRINCIPAL -->
    <div class="card-dashboard mb-4">
        <h5 class="fw-bold mb-4">
            <i class="fa-solid fa-clipboard-check me-2 text-success"></i>
            Nueva Devolución
        </h5>

        <form method="POST" id="formReingreso">

            <!-- Fila 1: Técnico + Motivo -->
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label">Técnico que devuelve</label>
                    <select name="tecnico_id" class="form-select">
                        <option value="">— Sin asignar —</option>
                        <?php $tecnicos->data_seek(0); while ($t=$tecnicos->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['nombre']) ?>
                            <?= $t['cargo']?' — '.htmlspecialchars($t['cargo']):'' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Motivo de Devolución *</label>
                    <select name="motivo" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <option value="Trabajo no ejecutado">Trabajo no ejecutado</option>
                        <option value="Material sobrante">Material sobrante</option>
                        <option value="Material defectuoso">Material defectuoso</option>
                        <option value="Cambio de especificación">Cambio de especificación</option>
                        <option value="Cancelación de orden">Cancelación de orden</option>
                        <option value="Error de despacho">Error de despacho</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Observación</label>
                    <input type="text" name="observacion" class="form-control"
                           placeholder="Detalle adicional..." maxlength="255">
                </div>
            </div>

            <!-- Buscador de materiales -->
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1 text-success"></i>
                    Buscar y agregar materiales
                </label>
                <div class="d-flex gap-2 align-items-start" style="position:relative;">
                    <div style="flex:1;position:relative;">
                        <input type="text" id="buscadorReingreso"
                               class="form-control"
                               placeholder="Escribe el nombre del material..."
                               autocomplete="off">
                        <!-- Dropdown resultados -->
                        <div id="dropdownReingreso" class="drop-container" style="display:none;">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-custom"
                            onclick="limpiarBuscador()"
                            title="Limpiar búsqueda" style="white-space:nowrap;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <small class="text-secondary mt-1 d-block">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Escribe al menos 1 letra para buscar. Haz clic en el material para agregarlo.
                </small>
            </div>

            <!-- Lista de materiales seleccionados -->
            <div id="listaMateriales" class="mb-4">
                <!-- Los items se insertan aquí dinámicamente -->
            </div>

            <div id="sinMateriales" class="text-center py-4 mb-3"
                 style="border:2px dashed rgba(16,185,129,0.2);border-radius:14px;">
                <i class="fa-solid fa-boxes-stacked fa-2x mb-2"
                   style="color:rgba(16,185,129,0.3);"></i>
                <p class="text-secondary mb-0" style="font-size:13px;">
                    No hay materiales agregados. Usa el buscador de arriba.
                </p>
            </div>

            <!-- Resumen + Botón -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div id="resumenReingreso" style="display:none;">
                    <span class="text-secondary" style="font-size:13px;">
                        <i class="fa-solid fa-check-circle text-success me-1"></i>
                        <strong id="contadorMats">0</strong> material(es) listo(s) para registrar
                    </span>
                </div>
                <button type="submit" id="btnRegistrar"
                        class="btn btn-success btn-custom px-5" disabled>
                    <i class="fa-solid fa-rotate-left me-2"></i>Registrar Devolución
                </button>
            </div>

        </form>
    </div>

    <!-- HISTORIAL -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <i class="fa-solid fa-list-check me-2 text-success"></i>
                Historial de Reingresos
            </h5>
            <small class="text-secondary"><?= $historial->num_rows ?> registros</small>
        </div>
        <div class="table-responsive">
            <table id="tablaReingresos" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Material</th><th>Cantidad</th>
                        <th>Técnico</th><th>Motivo</th><th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($r=$historial->fetch_assoc()): ?>
                <tr>
                    <td><small><?= $r['fecha']?date('d/m/Y H:i',strtotime($r['fecha'])):'—' ?></small></td>
                    <td>
                        <strong><?= htmlspecialchars($r['material']) ?></strong>
                        <?php if($r['codigo']): ?>
                        <br><small class="text-secondary"><?= htmlspecialchars($r['codigo']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-success" style="font-size:12px;">+<?= $r['cantidad'] ?></span></td>
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

<style>
/* Items de material seleccionado */
.mat-item {
    display:flex; align-items:center; gap:12px;
    background:rgba(16,185,129,0.06);
    border:1px solid rgba(16,185,129,0.2);
    border-radius:14px; padding:12px 16px;
    margin-bottom:8px; transition:.2s;
    animation: slideIn .2s ease;
}
.mat-item:hover { background:rgba(16,185,129,0.1); border-color:rgba(16,185,129,0.35); }
@keyframes slideIn {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}
.mat-icon {
    width:40px; height:40px; border-radius:12px; flex-shrink:0;
    background:rgba(16,185,129,0.15);
    display:flex; align-items:center; justify-content:center;
    color:#10b981; font-size:16px;
}
.mat-info { flex:1; min-width:0; }
.mat-nombre { font-weight:600; font-size:13px; }
.mat-meta  { font-size:11px; color:#64748b; }
.mat-stock-badge {
    font-size:11px; padding:3px 8px; border-radius:8px;
    font-weight:600;
}
.mat-cant-input {
    width:100px; text-align:center;
    background:#0f172a; border:1px solid #334155;
    color:white; border-radius:10px; padding:6px 10px;
    font-size:13px; font-weight:600;
}
.mat-cant-input:focus {
    outline:none; border-color:#10b981;
    box-shadow:0 0 0 3px rgba(16,185,129,0.15);
}
body.light-mode .mat-item { background:rgba(16,185,129,0.04); }
body.light-mode .mat-cant-input { background:#f8fafc; color:#0f172a; border-color:#cbd5e1; }

/* ── Dropdown resultados de búsqueda ── */
.drop-container {
    position:absolute; top:100%; left:0; right:0; z-index:9999;
    background:#1e293b;
    border:1px solid #334155;
    border-radius:14px;
    box-shadow:0 12px 36px rgba(0,0,0,0.35);
    overflow:hidden;
    margin-top:4px;
}
/* MODO CLARO — fondo blanco, borde visible */
body.light-mode .drop-container {
    background:#ffffff !important;
    border:1px solid #cbd5e1 !important;
    box-shadow:0 8px 30px rgba(0,0,0,0.12) !important;
}
.drop-item {
    display:flex; align-items:center; gap:14px;
    padding:13px 16px; cursor:pointer;
    border-bottom:1px solid rgba(255,255,255,0.06);
    transition:background .15s, border-left .15s;
    border-left:3px solid transparent;
}
.drop-item:last-child { border-bottom:none; }

/* Hover — modo oscuro */
.drop-item:hover {
    background:rgba(16,185,129,0.12);
    border-left-color:#10b981;
}
/* Ya agregado (activo) */
.drop-item.activo {
    background:rgba(16,185,129,0.08);
    border-left-color:#10b981;
    opacity:.7; cursor:default;
}

/* Icono del item */
.drop-item .di-icon {
    width:38px; height:38px; border-radius:10px; flex-shrink:0;
    background:rgba(16,185,129,0.15);
    display:flex; align-items:center; justify-content:center;
    color:#10b981; font-size:15px;
    transition:background .15s;
}
.drop-item:hover .di-icon { background:rgba(16,185,129,0.28); }

/* Texto nombre — modo oscuro */
.drop-item-nombre {
    font-size:13px; font-weight:600;
    color:#f1f5f9;
}
/* Texto meta — modo oscuro */
.drop-item-meta { font-size:11px; color:#94a3b8; margin-top:2px; }

/* Pills de stock */
.drop-stock {
    font-size:11px; font-weight:700;
    padding:4px 10px; border-radius:20px;
    white-space:nowrap; margin-left:auto; flex-shrink:0;
}
.drop-stock.verde    { background:rgba(34,197,94,0.15);  color:#22c55e; border:1px solid rgba(34,197,94,0.25); }
.drop-stock.amarillo { background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.25); }
.drop-stock.rojo     { background:rgba(239,68,68,0.15);  color:#ef4444; border:1px solid rgba(239,68,68,0.25); }

/* ── MODO CLARO — todos los elementos del dropdown ── */
body.light-mode .drop-item {
    border-bottom-color:#e2e8f0;
}
body.light-mode .drop-item:hover {
    background:rgba(16,185,129,0.07);
    border-left-color:#10b981;
}
body.light-mode .drop-item .di-icon {
    background:rgba(16,185,129,0.1);
    color:#059669;
}
/* CRÍTICO: texto nombre visible en fondo blanco */
body.light-mode .drop-item-nombre {
    color:#0f172a !important;
}
body.light-mode .drop-item-meta {
    color:#64748b !important;
}
/* Pills en modo claro — mantener colores */
body.light-mode .drop-stock.verde    { background:rgba(34,197,94,0.12);  color:#16a34a; }
body.light-mode .drop-stock.amarillo { background:rgba(245,158,11,0.12); color:#b45309; }
body.light-mode .drop-stock.rojo     { background:rgba(239,68,68,0.12);  color:#dc2626; }
</style>

<script>
$(document).ready(()=>{
    $('#tablaReingresos').DataTable({
        responsive:true, pageLength:10, language:dtLang, order:[[0,'desc']]
    });
});

// ── Estado interno ────────────────────────────────────────────
const materialesSeleccionados = {}; // {id: {id, nombre, codigo, unidad, stock, cant}}

// ── Buscador ─────────────────────────────────────────────────
let timer = null;
document.getElementById('buscadorReingreso').addEventListener('input', function(){
    clearTimeout(timer);
    const q = this.value.trim();
    const drop = document.getElementById('dropdownReingreso');
    if(q.length < 1){ drop.style.display='none'; return; }

    timer = setTimeout(()=>{
        fetch(BASE_URL+'/api/buscar_material.php?q='+encodeURIComponent(q))
        .then(r=>r.json())
        .then(data=>{
            drop.innerHTML='';
            if(!data.length){
                drop.innerHTML='<div class="drop-item"><span class="drop-item-nombre text-secondary">Sin resultados</span></div>';
                drop.style.display='block'; return;
            }
            data.forEach(m=>{
                const yaEsta   = !!materialesSeleccionados[m.id];
                const stockCls = m.stock<=0?'rojo':(m.stock<=5?'amarillo':'verde');
                const div = document.createElement('div');
                div.className = 'drop-item' + (yaEsta?' activo':'');
                div.innerHTML = `
                    <div class="di-icon">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="drop-item-nombre">
                            ${m.nombre}
                            ${yaEsta?'<span class="badge bg-success ms-2" style="font-size:9px;vertical-align:middle;">✓ Agregado</span>':''}
                        </div>
                        <div class="drop-item-meta">
                            ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                            <span>${m.unidad}</span>
                        </div>
                    </div>
                    <span class="drop-stock ${stockCls}">Stock: ${m.stock}</span>`;
                if(!yaEsta){
                    div.addEventListener('click',()=>{
                        agregarMaterial(m);
                        document.getElementById('buscadorReingreso').value='';
                        drop.style.display='none';
                    });
                }
                drop.appendChild(div);
            });
            drop.style.display='block';
        });
    }, 260);
});

// Cerrar dropdown al click fuera
document.addEventListener('click', e=>{
    const inp  = document.getElementById('buscadorReingreso');
    const drop = document.getElementById('dropdownReingreso');
    if(!inp.contains(e.target) && !drop.contains(e.target))
        drop.style.display='none';
});

// ── Agregar material a la lista ───────────────────────────────
function agregarMaterial(m){
    if(materialesSeleccionados[m.id]) return;
    materialesSeleccionados[m.id] = {...m, cant:1};

    const sc  = m.stock<=0?'danger':(m.stock<=5?'warning':(m.stock<=10?'info':'success'));
    const div = document.createElement('div');
    div.className='mat-item';
    div.id='mat-item-'+m.id;
    div.innerHTML=`
        <div class="mat-icon"><i class="fa-solid fa-box"></i></div>
        <div class="mat-info">
            <div class="mat-nombre">${m.nombre}</div>
            <div class="mat-meta">
                ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                <span>${m.unidad}</span>
                <span class="badge mat-stock-badge bg-${sc} ms-2">Stock disponible: ${m.stock}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCant(${m.id},-1)">
                <i class="fa-solid fa-minus" style="font-size:10px;"></i>
            </button>
            <input type="number" class="mat-cant-input"
                   id="cant-${m.id}"
                   name="cantidad[]" value="1" min="0.01" step="0.01"
                   onchange="validarCant(${m.id},${m.stock})"
                   oninput="validarCant(${m.id},${m.stock})">
            <input type="hidden" name="material_id[]" value="${m.id}">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCant(${m.id},1)">
                <i class="fa-solid fa-plus" style="font-size:10px;"></i>
            </button>
        </div>
        <button type="button" class="btn btn-sm"
                style="background:rgba(239,68,68,.12);border:none;color:#ef4444;
                       width:34px;height:34px;border-radius:10px;flex-shrink:0;"
                onclick="quitarMaterial(${m.id})" title="Quitar">
            <i class="fa-solid fa-xmark"></i>
        </button>`;

    document.getElementById('listaMateriales').appendChild(div);
    actualizarEstado();

    if(m.stock <= 0){
        Swal.fire({title:'Sin stock',
            text:`"${m.nombre}" tiene stock 0, pero igual puedes registrar la devolución.`,
            icon:'info',timer:3000,showConfirmButton:false});
    }
}

function cambiarCant(id, delta){
    const inp = document.getElementById('cant-'+id);
    const m   = materialesSeleccionados[id];
    if(!inp || !m) return;
    let v = parseFloat(inp.value)||0;
    v = Math.max(0.01, v+delta);
    inp.value = v;
    m.cant = v;
    actualizarEstado();
}

function validarCant(id, stock){
    const inp = document.getElementById('cant-'+id);
    const m   = materialesSeleccionados[id];
    if(!inp || !m) return;
    const v = parseFloat(inp.value)||0;
    m.cant = v > 0 ? v : 0.01;
    inp.style.borderColor = v <= 0 ? '#ef4444' : '';
    actualizarEstado();
}

function quitarMaterial(id){
    delete materialesSeleccionados[id];
    const el = document.getElementById('mat-item-'+id);
    if(el){ el.style.animation='none'; el.style.opacity='0'; el.style.transform='translateX(10px)';
            setTimeout(()=>el.remove(), 180); }
    actualizarEstado();
}

function limpiarBuscador(){
    document.getElementById('buscadorReingreso').value='';
    document.getElementById('dropdownReingreso').style.display='none';
    document.getElementById('buscadorReingreso').focus();
}

function actualizarEstado(){
    const total = Object.keys(materialesSeleccionados).length;
    const validos = Object.values(materialesSeleccionados).filter(m=>m.cant>0).length;

    document.getElementById('sinMateriales').style.display  = total===0?'block':'none';
    document.getElementById('resumenReingreso').style.display= total>0?'block':'none';
    document.getElementById('contadorMats').textContent     = validos;
    document.getElementById('btnRegistrar').disabled        = validos===0;
}

// ── Validación antes de enviar ───────────────────────────────
document.getElementById('formReingreso').addEventListener('submit',function(e){
    e.preventDefault();
    const validos = Object.values(materialesSeleccionados).filter(m=>m.cant>0);
    if(!validos.length){Swal.fire('Aviso','Agrega al menos un material.','warning');return;}

    const motivo = document.querySelector('[name=motivo]').value;
    if(!motivo){Swal.fire('Aviso','Selecciona el motivo de devolución.','warning');return;}

    const nombres = validos.slice(0,3).map(m=>m.nombre).join(', ') + (validos.length>3?'...':'');
    Swal.fire({
        title:'¿Confirmar devolución?',
        html:`<div style="font-size:13px;">
                <p class="mb-2">Se reingresarán <strong>${validos.length}</strong> material(es):</p>
                <p class="text-secondary">${nombres}</p>
                <p class="mb-0">Motivo: <strong>${motivo}</strong></p>
              </div>`,
        icon:'question', showCancelButton:true,
        confirmButtonColor:'#10b981', cancelButtonColor:'#64748b',
        confirmButtonText:'<i class="fa-solid fa-rotate-left me-1"></i>Registrar',
        cancelButtonText:'Revisar'
    }).then(r=>{if(r.isConfirmed)this.submit();});
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

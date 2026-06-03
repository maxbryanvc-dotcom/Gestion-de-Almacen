<?php
// ============================================================
// SALIDA DE MATERIAL — múltiples materiales por transacción
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';
$tecnicos = $conn->query("SELECT id, nombre, cargo FROM tecnicos ORDER BY nombre ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tecnico_id   = intval($_POST['tecnico_id']  ?? 0) ?: null;
    $observacion  = trim($_POST['observacion']   ?? '');
    $material_ids = $_POST['material_id']        ?? [];
    $cantidades   = $_POST['cantidad']           ?? [];
    $usuario      = $_SESSION['usuario'];
    $fecha        = date('Y-m-d H:i:s');

    $filas_validas = [];
    $errores_stock = [];

    foreach ($material_ids as $k => $mid) {
        $mid  = intval($mid);
        $cant = floatval($cantidades[$k] ?? 0);
        if ($mid <= 0 || $cant <= 0) continue;

        $chk = $conn->prepare("SELECT nombre, stock FROM materiales WHERE id=? AND activo=1 LIMIT 1");
        $chk->bind_param('i', $mid); $chk->execute();
        $mat = $chk->get_result()->fetch_assoc(); $chk->close();

        if (!$mat) continue;
        if ($cant > $mat['stock']) {
            $errores_stock[] = "«{$mat['nombre']}»: necesita $cant, disponible {$mat['stock']}";
            continue;
        }
        $filas_validas[] = ['id'=>$mid, 'cant'=>$cant, 'nombre'=>$mat['nombre']];
    }

    if (!empty($errores_stock)) {
        $msg = 'Stock insuficiente: ' . implode(' | ', $errores_stock);
        $tipo_msg = 'error';
    } elseif (empty($filas_validas)) {
        $msg = 'Agrega al menos un material con cantidad válida.';
        $tipo_msg = 'error';
    } else {
        $stmtS = $conn->prepare(
            "INSERT INTO salidas (material_id, tecnico_id, cantidad, fecha, observacion, usuario)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmtU = $conn->prepare("UPDATE materiales SET stock = stock - ? WHERE id = ?");
        foreach ($filas_validas as $fv) {
            $stmtS->bind_param('iiisss', $fv['id'], $tecnico_id, $fv['cant'], $fecha, $observacion, $usuario);
            $stmtS->execute();
            $stmtU->bind_param('di', $fv['cant'], $fv['id']); $stmtU->execute();
        }
        $stmtS->close(); $stmtU->close();
        audit_log($conn, 'SALIDA_MATERIAL', count($filas_validas).' material(es) despachados por '.$usuario);
        $msg = 'Salida registrada — '.count($filas_validas).' material(es) despachados.';
        $tipo_msg = 'success';
    }
}

$ultimas = $conn->query("
    SELECT s.fecha, m.nombre AS material, m.codigo, s.cantidad,
           COALESCE(t.nombre,'—') AS tecnico, s.usuario
    FROM salidas s
    JOIN materiales m ON m.id = s.material_id
    LEFT JOIN tecnicos t ON t.id = s.tecnico_id
    ORDER BY s.id DESC LIMIT 50
");
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-upload me-2 text-danger"></i>
                Salida de Material
            </h2>
            <p class="text-secondary mb-0">Despacho de materiales a técnicos</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Despachado":"Error" ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:4000,timerProgressBar:true,confirmButtonColor:'#ef4444'
    }));
    </script>
    <?php endif; ?>

    <!-- FORMULARIO PRINCIPAL -->
    <div class="card-dashboard mb-4">
        <h5 class="fw-bold mb-4">
            <i class="fa-solid fa-arrow-up me-2 text-danger"></i>
            Registrar Despacho
        </h5>

        <form method="POST" id="formSalida">

            <!-- Técnico + Observación -->
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label">Técnico Responsable</label>
                    <select name="tecnico_id" class="form-select">
                        <option value="">— Sin asignar —</option>
                        <?php while ($t=$tecnicos->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['nombre']) ?>
                            <?= $t['cargo']?' — '.htmlspecialchars($t['cargo']):'' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Observación</label>
                    <input type="text" name="observacion" class="form-control"
                           placeholder="Orden de trabajo, proyecto, etc." maxlength="255">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha y hora</label>
                    <div class="form-control" style="opacity:.7;cursor:default;">
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>

            <!-- Buscador -->
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1 text-danger"></i>
                    Buscar y agregar materiales
                </label>
                <div class="d-flex gap-2">
                    <div style="flex:1;position:relative;">
                        <input type="text" id="buscadorSalida"
                               class="form-control"
                               placeholder="Escribe el nombre del material..."
                               autocomplete="off">
                        <div id="dropdownSalida" class="drop-container"
                             style="display:none;position:absolute;
                                    top:100%;left:0;right:0;z-index:9999;
                                    max-height:240px;overflow-y:auto;margin-top:4px;">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-custom"
                            onclick="limpiarBuscadorSalida()" title="Limpiar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <small class="text-secondary mt-1 d-block">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Haz clic en el material para agregarlo. Solo muestra materiales con stock disponible.
                </small>
            </div>

            <!-- Lista de materiales seleccionados -->
            <div id="listaSalida" class="mb-4"></div>

            <div id="sinMaterialesSalida" class="text-center py-4 mb-3"
                 style="border:2px dashed rgba(239,68,68,0.2);border-radius:14px;">
                <i class="fa-solid fa-boxes-stacked fa-2x mb-2"
                   style="color:rgba(239,68,68,0.3);"></i>
                <p class="text-secondary mb-0" style="font-size:13px;">
                    No hay materiales seleccionados. Usa el buscador de arriba.
                </p>
            </div>

            <!-- Botón guardar -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div id="resumenSalida" style="display:none;">
                    <span class="text-secondary" style="font-size:13px;">
                        <i class="fa-solid fa-check-circle text-danger me-1"></i>
                        <strong id="contadorSalida">0</strong> material(es) listo(s) para despachar
                    </span>
                </div>
                <button type="submit" id="btnGuardarSalida"
                        class="btn btn-danger btn-custom px-5" disabled>
                    <i class="fa-solid fa-upload me-2"></i>Registrar Salida
                </button>
            </div>

        </form>
    </div>

    <!-- HISTORIAL -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <i class="fa-solid fa-list me-2 text-secondary"></i>
                Últimas Salidas
            </h5>
            <small class="text-secondary"><?= $ultimas->num_rows ?> registros</small>
        </div>
        <div class="table-responsive">
            <table id="tablaSalidas" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Material</th><th>Cantidad</th>
                        <th>Técnico</th><th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($s=$ultimas->fetch_assoc()): ?>
                <tr>
                    <td><small><?= $s['fecha']?date('d/m/Y H:i',strtotime($s['fecha'])):'—' ?></small></td>
                    <td>
                        <strong><?= htmlspecialchars($s['material']) ?></strong>
                        <?php if($s['codigo']): ?>
                        <br><small class="text-secondary"><?= htmlspecialchars($s['codigo']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-danger" style="font-size:12px;">-<?= $s['cantidad'] ?></span></td>
                    <td><?= htmlspecialchars($s['tecnico']) ?></td>
                    <td><code style="color:#60a5fa;font-size:11px;"><?= htmlspecialchars($s['usuario']??'—') ?></code></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
/* Cards de material seleccionado — estilo ROJO para salidas */
.mat-salida-item {
    display:flex; align-items:center; gap:12px;
    background:rgba(239,68,68,0.05);
    border:1px solid rgba(239,68,68,0.2);
    border-radius:14px; padding:12px 16px;
    margin-bottom:8px; transition:.2s;
    animation:slideInS .2s ease;
}
.mat-salida-item:hover { background:rgba(239,68,68,0.09); border-color:rgba(239,68,68,0.35); }
@keyframes slideInS {
    from{opacity:0;transform:translateY(-6px);}
    to  {opacity:1;transform:translateY(0);}
}
.mat-salida-icon {
    width:40px;height:40px;border-radius:12px;flex-shrink:0;
    background:rgba(239,68,68,0.12);
    display:flex;align-items:center;justify-content:center;
    color:#ef4444;font-size:16px;
}
.cant-salida {
    width:100px;text-align:center;
    background:#0f172a;border:1px solid #334155;
    color:white;border-radius:10px;padding:6px 10px;
    font-size:13px;font-weight:600;
}
.cant-salida:focus{outline:none;border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,0.15);}
body.light-mode .mat-salida-item{background:rgba(239,68,68,0.04);}
body.light-mode .cant-salida{background:#f8fafc;color:#0f172a;border-color:#cbd5e1;}
</style>

<script>
$(document).ready(()=>{
    $('#tablaSalidas').DataTable({responsive:true,pageLength:10,language:dtLang,order:[[0,'desc']]});
});

const seleccionadosSalida = {};
let timerSalida = null;

// ── Buscador ─────────────────────────────────────────────────
document.getElementById('buscadorSalida').addEventListener('input', function(){
    clearTimeout(timerSalida);
    const q = this.value.trim();
    const drop = document.getElementById('dropdownSalida');
    if(q.length < 1){ drop.style.display='none'; return; }

    timerSalida = setTimeout(()=>{
        fetch(BASE_URL+'/api/buscar_material.php?q='+encodeURIComponent(q))
        .then(r=>r.json())
        .then(data=>{
            drop.innerHTML='';
            if(!data.length){
                drop.innerHTML='<div class="drop-item"><span class="drop-item-nombre" style="opacity:.5;">Sin resultados</span></div>';
                drop.style.display='block'; return;
            }
            data.forEach(m=>{
                const yaEsta   = !!seleccionadosSalida[m.id];
                const stockCls = m.stock<=0?'rojo':(m.stock<=5?'amarillo':'verde');
                const div = document.createElement('div');
                div.className = 'drop-item'+(yaEsta?' ac-active':'');
                div.innerHTML = `
                    <div class="di-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="drop-item-nombre">
                            ${m.nombre}
                            ${yaEsta?'<span class="badge bg-danger ms-2" style="font-size:9px;">✓ Agregado</span>':''}
                        </div>
                        <div class="drop-item-meta">
                            ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                            <span>${m.unidad}</span>
                        </div>
                    </div>
                    <span class="drop-stock ${stockCls}">Stock: ${m.stock}</span>`;

                if(!yaEsta && m.stock > 0){
                    div.addEventListener('click',()=>{
                        agregarSalida(m);
                        document.getElementById('buscadorSalida').value='';
                        drop.style.display='none';
                    });
                } else if(m.stock <= 0){
                    div.style.opacity='.5'; div.style.cursor='not-allowed';
                }
                drop.appendChild(div);
            });
            drop.style.display='block';
        });
    },260);
});

document.addEventListener('click',e=>{
    const inp  = document.getElementById('buscadorSalida');
    const drop = document.getElementById('dropdownSalida');
    if(!inp.contains(e.target)&&!drop.contains(e.target)) drop.style.display='none';
});

// ── Agregar material ─────────────────────────────────────────
function agregarSalida(m){
    if(seleccionadosSalida[m.id] || m.stock<=0) return;
    seleccionadosSalida[m.id] = {...m, cant:1};

    const sc = m.stock<=5?'amarillo':'verde';
    const div = document.createElement('div');
    div.className='mat-salida-item';
    div.id='sal-item-'+m.id;
    div.innerHTML=`
        <div class="mat-salida-icon"><i class="fa-solid fa-box"></i></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;">${m.nombre}</div>
            <div style="font-size:11px;color:#64748b;">
                ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                <span>${m.unidad}</span>
                <span class="drop-stock ${sc} ms-2">Disponible: ${m.stock}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantSalida(${m.id},-1,${m.stock})">
                <i class="fa-solid fa-minus" style="font-size:10px;"></i>
            </button>
            <input type="number" class="cant-salida"
                   id="cantsal-${m.id}"
                   name="cantidad[]" value="1" min="1" step="1" max="${m.stock}"
                   onchange="validarCantSalida(${m.id},${m.stock})"
                   oninput="validarCantSalida(${m.id},${m.stock})">
            <input type="hidden" name="material_id[]" value="${m.id}">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantSalida(${m.id},1,${m.stock})">
                <i class="fa-solid fa-plus" style="font-size:10px;"></i>
            </button>
        </div>
        <button type="button" class="btn btn-sm"
                style="background:rgba(239,68,68,.12);border:none;color:#ef4444;
                       width:34px;height:34px;border-radius:10px;flex-shrink:0;"
                onclick="quitarSalida(${m.id})" title="Quitar">
            <i class="fa-solid fa-xmark"></i>
        </button>`;

    document.getElementById('listaSalida').appendChild(div);
    actualizarEstadoSalida();
}

function cambiarCantSalida(id, delta, maxStock){
    const inp = document.getElementById('cantsal-'+id);
    if(!inp) return;
    let v = parseInt(inp.value)||0;
    v = Math.max(1, Math.min(v+delta, maxStock));
    inp.value = v;
    if(seleccionadosSalida[id]) seleccionadosSalida[id].cant = v;
    actualizarEstadoSalida();
}

function validarCantSalida(id, maxStock){
    const inp = document.getElementById('cantsal-'+id);
    if(!inp) return;
    let v = parseInt(inp.value)||0;
    if(v > maxStock){ inp.value=maxStock; v=maxStock; }
    if(v < 1){ inp.value=1; v=1; }
    inp.style.borderColor = '';
    if(seleccionadosSalida[id]) seleccionadosSalida[id].cant = v;
    actualizarEstadoSalida();
}

function quitarSalida(id){
    delete seleccionadosSalida[id];
    const el=document.getElementById('sal-item-'+id);
    if(el){el.style.opacity='0';el.style.transform='translateX(10px)';
           setTimeout(()=>el.remove(),180);}
    actualizarEstadoSalida();
}

function limpiarBuscadorSalida(){
    document.getElementById('buscadorSalida').value='';
    document.getElementById('dropdownSalida').style.display='none';
    document.getElementById('buscadorSalida').focus();
}

function actualizarEstadoSalida(){
    const total  = Object.keys(seleccionadosSalida).length;
    const validos= Object.values(seleccionadosSalida).filter(m=>m.cant>0).length;
    document.getElementById('sinMaterialesSalida').style.display = total===0?'block':'none';
    document.getElementById('resumenSalida').style.display       = total>0?'block':'none';
    document.getElementById('contadorSalida').textContent        = validos;
    document.getElementById('btnGuardarSalida').disabled         = validos===0;
}

// ── Confirmación al enviar ────────────────────────────────────
document.getElementById('formSalida').addEventListener('submit',function(e){
    e.preventDefault();
    const validos=Object.values(seleccionadosSalida).filter(m=>m.cant>0);
    if(!validos.length){Swal.fire('Aviso','Agrega al menos un material.','warning');return;}

    const nombres=validos.slice(0,3).map(m=>m.nombre).join(', ')+(validos.length>3?'...':'');
    Swal.fire({
        title:'¿Confirmar despacho?',
        html:`<div style="font-size:13px;">
                <p class="mb-2">Se descontará stock de <strong>${validos.length}</strong> material(es):</p>
                <p class="text-secondary mb-0">${nombres}</p>
              </div>`,
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#ef4444',cancelButtonColor:'#64748b',
        confirmButtonText:'<i class="fa-solid fa-upload me-1"></i>Despachar',
        cancelButtonText:'Revisar'
    }).then(r=>{if(r.isConfirmed)this.submit();});
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

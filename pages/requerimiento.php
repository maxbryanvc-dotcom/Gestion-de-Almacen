<?php
// ============================================================
// MÓDULO REQUERIMIENTOS — Solicitud de materiales a técnicos
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

$msg = $tipo_msg = '';

// ── APROBAR ──────────────────────────────────────────────────
if (isset($_GET['aprobar']) && esAdmin()) {
    $rid = intval($_GET['aprobar']);
    $aprobado = $_SESSION['usuario'];
    $stmt = $conn->prepare("UPDATE requerimientos SET estado='Aprobado', aprobado_por=? WHERE id=?");
    $stmt->bind_param('si', $aprobado, $rid);
    $stmt->execute(); $stmt->close();
    header("Location: " . BASE_URL . "/pages/requerimiento.php?ok=aprobado"); exit();
}

// ── ANULAR ───────────────────────────────────────────────────
if (isset($_GET['anular']) && esAdmin()) {
    $rid = intval($_GET['anular']);
    $stmt = $conn->prepare("UPDATE requerimientos SET estado='Anulado' WHERE id=?");
    $stmt->bind_param('i', $rid);
    $stmt->execute(); $stmt->close();
    header("Location: " . BASE_URL . "/pages/requerimiento.php?ok=anulado"); exit();
}

// ── ELIMINAR ─────────────────────────────────────────────────
if (isset($_GET['eliminar']) && esAdmin()) {
    $rid = intval($_GET['eliminar']);
    // Eliminar detalle primero (clave foránea)
    $stmt = $conn->prepare("DELETE FROM detalle_requerimiento WHERE requerimiento_id=?");
    $stmt->bind_param('i', $rid); $stmt->execute(); $stmt->close();
    // Eliminar cabecera
    $stmt2 = $conn->prepare("DELETE FROM requerimientos WHERE id=?");
    $stmt2->bind_param('i', $rid); $stmt2->execute(); $stmt2->close();
    audit_log($conn, 'ELIMINAR_REQUERIMIENTO', "ID $rid eliminado");
    header("Location: " . BASE_URL . "/pages/requerimiento.php?ok=eliminado"); exit();
}

// ── CREAR REQUERIMIENTO ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {

    $tipo_liq     = in_array($_POST['tipo_liq'] ?? '', ['Instalaciones','Mantenimiento'])
                    ? $_POST['tipo_liq'] : 'Instalaciones';
    $material_ids = $_POST['material_id'] ?? [];
    $cantidades   = $_POST['cantidad']             ?? [];

    // Validar filas
    $filas_validas = [];
    $errores_stock = [];

    // El requerimiento es una SOLICITUD A ELECTROSU ESTE — NO descuenta stock
    // Solo registra qué materiales se están pidiendo y en qué cantidad
    foreach ($material_ids as $k => $mid) {
        $mid  = intval($mid);
        $cant = intval($cantidades[$k] ?? 0);
        if ($mid <= 0 || $cant <= 0) continue;

        // Solo verificar que el material existe (sin validar stock)
        $chk = $conn->prepare("SELECT nombre FROM materiales WHERE id=? AND activo=1 LIMIT 1");
        $chk->bind_param('i', $mid);
        $chk->execute();
        $mat = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$mat) continue;
        $filas_validas[] = ['id' => $mid, 'cant' => $cant, 'nombre' => $mat['nombre']];
    }

    if (empty($filas_validas)) {
        $msg = 'Agrega al menos un material con cantidad válida.';
        $tipo_msg = 'error';
    } else {
        $fecha   = date('Y-m-d');
        $codigo  = 'REQ-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $usuario = $_SESSION['usuario'];

        // Insertar cabecera
        $stmtR = $conn->prepare(
            "INSERT INTO requerimientos (codigo_req, tipo_liq, fecha, estado, aprobado_por)
             VALUES (?, ?, ?, 'Pendiente', ?)"
        );
        $stmtR->bind_param('ssss', $codigo, $tipo_liq, $fecha, $usuario);

        if ($stmtR->execute()) {
            $req_id = $conn->insert_id;
            $stmtR->close();

            // Solo guardar el detalle — SIN tocar el stock
            $stmtD = $conn->prepare(
                "INSERT INTO detalle_requerimiento (requerimiento_id, material_id, cantidad) VALUES (?,?,?)"
            );

            foreach ($filas_validas as $fv) {
                $stmtD->bind_param('iii', $req_id, $fv['id'], $fv['cant']);
                $stmtD->execute();
            }
            $stmtD->close();

            audit_log($conn, 'CREAR_REQUERIMIENTO', "Código: $codigo, ID: $req_id, ítems: " . count($filas_validas));

            $_SESSION['ultimo_req_id']     = $req_id;
            $_SESSION['ultimo_req_codigo'] = $codigo;
            header("Location: " . BASE_URL . "/pages/requerimiento.php?ok=creado"); exit();
        } else {
            $stmtR->close();
            $msg = 'Error al guardar el requerimiento.'; $tipo_msg = 'error';
        }
    }
}

// ── LISTAR REQUERIMIENTOS ────────────────────────────────────
$lista = $conn->query("
    SELECT r.*, COALESCE(t.nombre,'Sin asignar') AS tecnico_nombre,
           COALESCE(t.cargo,'') AS tecnico_cargo,
           (SELECT COUNT(*) FROM detalle_requerimiento d WHERE d.requerimiento_id=r.id) AS num_items
    FROM requerimientos r
    LEFT JOIN tecnicos t ON t.id = r.tecnico_id
    ORDER BY r.id DESC
    LIMIT 100
");

$tecnicos_lista = $conn->query("SELECT id, nombre, cargo FROM tecnicos ORDER BY nombre ASC");

// ── LAYOUT — siempre al final, después de todas las acciones ──
require_once __DIR__ . '/../includes/layout.php';
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-file-lines me-2 text-primary"></i>Requerimientos
            </h2>
            <p class="text-secondary mb-0">Solicitudes de materiales para técnicos</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/pages/tecnicos.php" class="btn btn-warning btn-custom">
                <i class="fa-solid fa-hard-hat me-2"></i>Técnicos
            </a>
            <button class="btn btn-primary btn-custom" id="btnToggleForm">
                <i class="fa-solid fa-plus me-2"></i>Nuevo Requerimiento
            </button>
        </div>
    </div>

    <!-- ALERTAS DE URL -->
    <?php
    $okMsgs = [
        'creado'    => ['success', 'Requerimiento creado y stock descontado.'],
        'aprobado'  => ['success', 'Requerimiento aprobado.'],
        'anulado'   => ['warning', 'Requerimiento anulado.'],
        'eliminado' => ['success', 'Requerimiento eliminado.'],
    ];
    $okKey = $_GET['ok'] ?? '';
    if (isset($okMsgs[$okKey])): [$tipo_msg, $msg] = $okMsgs[$okKey]; endif;
    ?>
    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Correcto":($tipo_msg==="error"?"Error":"Aviso") ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:4000,timerProgressBar:true,confirmButtonColor:'#3b82f6'
    }));
    </script>
    <?php endif; ?>

    <!-- BANNER DE DESCARGA TRAS CREAR -->
    <?php if (isset($_SESSION['ultimo_req_id']) && $okKey === 'creado'):
        $reqId     = $_SESSION['ultimo_req_id'];
        $reqCodigo = $_SESSION['ultimo_req_codigo'];
    ?>
    <div class="mb-4" style="background:linear-gradient(135deg,#1e3a5f,#1e293b);
         border:1px solid rgba(59,130,246,0.3);border-radius:20px;padding:24px;">
        <div class="d-flex align-items-start gap-4 flex-wrap">

            <!-- Ícono y texto -->
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(34,197,94,0.15);
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-check fa-xl" style="color:#22c55e;"></i>
            </div>
            <div style="flex:1;min-width:200px;">
                <h5 class="fw-bold mb-1" style="color:white;">
                    Requerimiento registrado correctamente
                </h5>
                <p class="mb-0" style="color:#94a3b8;font-size:13px;">
                    <strong style="color:#60a5fa;"><?= htmlspecialchars($reqCodigo) ?></strong>
                    — Ahora genera la Carta Pedido para enviar a ElectroSur Este.
                </p>
            </div>

            <!-- Botones de descarga -->
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="<?= BASE_URL ?>/exports/generar_word.php?id=<?= $reqId ?>"
                   class="btn btn-primary btn-custom"
                   style="background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;
                          box-shadow:0 4px 14px rgba(37,99,235,0.4);">
                    <i class="fa-solid fa-file-word me-2"></i>
                    Descargar Carta Word
                </a>
                <a href="<?= BASE_URL ?>/exports/generar_pdf.php?id=<?= $reqId ?>"
                   class="btn btn-custom"
                   style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);
                          color:#fca5a5;">
                    <i class="fa-solid fa-file-pdf me-2"></i>PDF
                </a>
                <a href="<?= BASE_URL ?>/pages/ver_requerimiento.php?id=<?= $reqId ?>"
                   class="btn btn-custom" target="_blank"
                   style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);
                          color:#94a3b8;">
                    <i class="fa-solid fa-eye me-2"></i>Ver detalle
                </a>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['ultimo_req_id'], $_SESSION['ultimo_req_codigo']); ?>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         FORMULARIO NUEVO REQUERIMIENTO (colapsable)
    ═══════════════════════════════════════════════════════════ -->
    <div id="panelForm" style="display:none;" class="mb-4">
    <div class="card-dashboard">
        <h5 class="fw-bold mb-4">
            <i class="fa-solid fa-file-circle-plus me-2 text-primary"></i>
            Nuevo Requerimiento
        </h5>
        <form method="POST" id="formReq">
        <input type="hidden" name="accion" value="crear">

        <!-- Tipo de liquidación -->
        <div class="mb-3">
            <label class="form-label fw-semibold">Tipo de Pedido *</label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_liq"
                           id="tipoInst" value="Instalaciones" checked>
                    <label class="form-check-label" for="tipoInst">
                        <i class="fa-solid fa-bolt me-1 text-primary"></i>Instalaciones Nuevas
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="tipo_liq"
                           id="tipoMmto" value="Mantenimiento">
                    <label class="form-check-label" for="tipoMmto">
                        <i class="fa-solid fa-wrench me-1 text-warning"></i>Mantenimiento y Reposición
                    </label>
                </div>
            </div>
        </div>

        <!-- Buscador de materiales -->
        <div class="mb-3">
            <label class="form-label fw-semibold">
                <i class="fa-solid fa-magnifying-glass me-1 text-primary"></i>
                Materiales solicitados *
            </label>
            <div class="d-flex gap-2">
                <div style="flex:1;position:relative;">
                    <input type="text" id="buscadorReq" class="form-control"
                           placeholder="Escribe el nombre del material a solicitar..."
                           autocomplete="off">
                    <div id="dropdownReq" class="drop-container"
                         style="display:none;position:absolute;top:100%;left:0;right:0;
                                z-index:9999;max-height:220px;overflow-y:auto;margin-top:4px;">
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-custom"
                        onclick="document.getElementById('buscadorReq').value='';
                                 document.getElementById('dropdownReq').style.display='none';">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <small class="text-secondary mt-1 d-block" style="font-size:11px;">
                <i class="fa-solid fa-circle-info me-1"></i>
                Agrega los materiales que necesitas pedir a ElectroSur Este. Puedes solicitar cualquier cantidad — el stock no se modifica aquí.
            </small>
        </div>

        <!-- Cards de materiales seleccionados -->
        <div id="listaMatsReq" class="mb-3"></div>

        <div id="msgSinFilas" class="text-center py-4 mb-3"
             style="border:2px dashed rgba(59,130,246,0.2);border-radius:14px;">
            <i class="fa-solid fa-file-lines fa-2x mb-2"
               style="color:rgba(59,130,246,0.25);"></i>
            <p class="text-secondary mb-0" style="font-size:13px;">
                No hay materiales agregados. Usa el buscador de arriba.
            </p>
        </div>

        <!-- inputs ocultos para el POST -->
        <div id="inputsOcultosReq"></div>

        <!-- Botón guardar -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div id="resumenReq" style="display:none;">
                <span class="text-secondary" style="font-size:13px;">
                    <i class="fa-solid fa-check-circle text-primary me-1"></i>
                    <strong id="totalItems">0</strong> material(es) listo(s)
                </span>
            </div>
            <button type="submit" class="btn btn-primary btn-custom px-5" id="btnGuardar" disabled>
                <i class="fa-solid fa-floppy-disk me-2"></i>Registrar Requerimiento
            </button>
        </div>
        </form>
    </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         HISTORIAL DE REQUERIMIENTOS
    ═══════════════════════════════════════════════════════════ -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1">Historial de Requerimientos</h5>
                <small class="text-secondary">Últimos 100 registros</small>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaReq" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Ítems</th>
                        <th>Estado</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($r = $lista->fetch_assoc()):
                    $est = $r['estado'] ?? 'Pendiente';
                    $bc  = match($est) {
                        'Aprobado' => 'success', 'Anulado' => 'danger', default => 'warning'
                    };
                ?>
                <tr>
                    <td><code style="color:#60a5fa;"><?= htmlspecialchars($r['codigo_req'] ?? 'REQ-'.$r['id']) ?></code></td>
                    <td>
                        <?php $tl = $r['tipo_liq'] ?? 'Instalaciones'; ?>
                        <span class="badge bg-<?= $tl==='Instalaciones'?'primary':'warning' ?>">
                            <?= $tl==='Instalaciones'?'Inst.':'Mmto.' ?>
                        </span>
                    </td>
                    <td><?= $r['fecha'] ? date('d/m/Y', strtotime($r['fecha'])) : '—' ?></td>
                    <td><span class="badge bg-secondary"><?= $r['num_items'] ?></span></td>
                    <td><span class="badge bg-<?= $bc ?>"><?= $est ?></span></td>
                    <td><code style="color:#60a5fa;font-size:12px;"><?= htmlspecialchars($r['aprobado_por'] ?? '—') ?></code></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- Ver detalle -->
                            <a href="<?= BASE_URL ?>/pages/ver_requerimiento.php?id=<?= $r['id'] ?>"
                               class="btn btn-secondary btn-sm" title="Ver detalle" target="_blank">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <!-- Word (plantilla oficial) -->
                            <a href="<?= BASE_URL ?>/exports/generar_word.php?id=<?= $r['id'] ?>"
                               class="btn btn-primary btn-sm" title="Word (plantilla fija)">
                                <i class="fa-solid fa-file-word"></i>
                            </a>
                            <!-- PDF -->
                            <a href="<?= BASE_URL ?>/exports/generar_pdf.php?id=<?= $r['id'] ?>"
                               class="btn btn-danger btn-sm" title="PDF">
                                <i class="fa-solid fa-file-pdf"></i>
                            </a>
                            <!-- Usar plantilla dinámica -->
                            <button class="btn btn-warning btn-sm"
                                title="Usar plantilla personalizada"
                                onclick="usarPlantilla(<?= $r['id'] ?>)">
                                <i class="fa-solid fa-file-contract"></i>
                            </button>
                            <!-- Aprobar / Anular (solo admin, solo Pendiente) -->
                            <?php if (esAdmin() && $est === 'Pendiente'): ?>
                            <button class="btn btn-success btn-sm" title="Aprobar"
                                onclick="confirmarAccion('aprobar', <?= $r['id'] ?>, '<?= htmlspecialchars($r['codigo_req'] ?? 'REQ-'.$r['id']) ?>')">
                                <i class="fa-solid fa-check"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" title="Anular"
                                onclick="confirmarAccion('anular', <?= $r['id'] ?>, '<?= htmlspecialchars($r['codigo_req'] ?? 'REQ-'.$r['id']) ?>')">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            <?php endif; ?>
                            <!-- Eliminar (solo admin) -->
                            <?php if (esAdmin()): ?>
                            <button class="btn btn-danger btn-sm" title="Eliminar"
                                onclick="confirmarAccion('eliminar', <?= $r['id'] ?>, '<?= htmlspecialchars($r['codigo_req'] ?? 'REQ-'.$r['id']) ?>')">
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

<!-- ════════════════════════ ESTILOS ════════════════════════ -->
<style>
/* Autocomplete dropdown */
.autocomplete-wrap { position:relative; }
.autocomplete-list {
    position:absolute; top:100%; left:0; right:0; z-index:9999;
    background:#1e293b; border:1px solid #334155; border-radius:12px;
    max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,0.3);
    margin-top:3px;
}
body.light-mode .autocomplete-list { background:white; border-color:#e2e8f0; }
.ac-item {
    padding:10px 14px; cursor:pointer;
    display:flex; justify-content:space-between; align-items:center;
    font-size:13px; border-bottom:1px solid rgba(255,255,255,0.05);
    transition:background 0.15s;
}
body.light-mode .ac-item { border-bottom-color:#f1f5f9; }
.ac-item:hover, .ac-item.ac-active { background:rgba(59,130,246,0.18); }
.ac-item:last-child { border-bottom:none; }
.ac-nombre { font-weight:500; color:#e2e8f0; }
body.light-mode .ac-nombre { color:#0f172a; }
.ac-meta { font-size:11px; color:#64748b; text-align:right; }
.stock-badge-inline {
    display:inline-block; padding:2px 8px; border-radius:8px;
    font-size:11px; font-weight:700;
}

/* Fila de material en tabla */
.fila-material td { vertical-align:middle; }
.input-material-nombre {
    background:transparent !important; border:none !important;
    border-bottom:2px solid #334155 !important; border-radius:0 !important;
    color:inherit; padding:6px 4px; font-size:13px;
}
.input-material-nombre:focus { border-bottom-color:#3b82f6 !important; box-shadow:none !important; }
</style>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
// ── Toggle formulario ────────────────────────────────────────
document.getElementById('btnToggleForm').addEventListener('click', function(){
    const panel = document.getElementById('panelForm');
    const visible = panel.style.display !== 'none';
    panel.style.display = visible ? 'none' : 'block';
    this.innerHTML = visible
        ? '<i class="fa-solid fa-plus me-2"></i>Nuevo Requerimiento'
        : '<i class="fa-solid fa-minus me-2"></i>Cerrar formulario';
    if (!visible && document.getElementById('tbodyMateriales').children.length === 0) {
        agregarFila();
    }
});

// ── DataTable historial ──────────────────────────────────────
$(document).ready(()=>$('#tablaReq').DataTable({
    responsive:true, pageLength:10, language:dtLang, order:[[0,'desc']]
}));

// ── Sistema de cards para materiales del requerimiento ────────
const matsReq   = {};
let timerReq    = null;

document.getElementById('buscadorReq').addEventListener('input', function(){
    clearTimeout(timerReq);
    const q   = this.value.trim();
    const drop= document.getElementById('dropdownReq');
    if(q.length < 1){ drop.style.display='none'; return; }

    timerReq = setTimeout(()=>{
        fetch(BASE_URL+'/api/buscar_material.php?q='+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{
            drop.innerHTML='';
            if(!data.length){
                drop.innerHTML='<div class="drop-item"><span class="drop-item-nombre" style="opacity:.5;">Sin resultados</span></div>';
                drop.style.display='block'; return;
            }
            data.forEach(m=>{
                const yaEsta = !!matsReq[m.id];
                const sc = m.stock<=0?'rojo':(m.stock<=5?'amarillo':'verde');
                const div= document.createElement('div');
                div.className='drop-item'+(yaEsta?' ac-active':'');
                div.innerHTML=`
                    <div class="di-icon" style="background:rgba(59,130,246,0.12);color:#60a5fa;">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="drop-item-nombre">${m.nombre}
                            ${yaEsta?'<span class="badge bg-primary ms-2" style="font-size:9px;">✓ Agregado</span>':''}
                        </div>
                        <div class="drop-item-meta">${m.codigo||''} · ${m.unidad}</div>
                    </div>
                    <span class="drop-stock ${sc}">Stock: ${m.stock}</span>`;
                if(!yaEsta){
                    div.addEventListener('click',()=>{
                        agregarMatReq(m);
                        document.getElementById('buscadorReq').value='';
                        drop.style.display='none';
                    });
                }
                drop.appendChild(div);
            });
            drop.style.display='block';
        });
    },260);
});

document.addEventListener('click',e=>{
    const inp =document.getElementById('buscadorReq');
    const drop=document.getElementById('dropdownReq');
    if(inp&&drop&&!inp.contains(e.target)&&!drop.contains(e.target))
        drop.style.display='none';
});

function agregarMatReq(m){
    if(matsReq[m.id]) return;
    matsReq[m.id]={...m, cant:1};

    const sc = m.stock<=0?'rojo':(m.stock<=5?'amarillo':'verde');
    const div= document.createElement('div');
    div.id   = 'reqmat-'+m.id;
    div.className='mat-req-item';
    div.innerHTML=`
        <div class="mat-req-icon"><i class="fa-solid fa-box"></i></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;">${m.nombre}</div>
            <div style="font-size:11px;color:#64748b;">
                ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                <span>${m.unidad}</span>
                <span class="drop-stock ${sc} ms-2" style="font-size:10px;">Stock: ${m.stock}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantReq(${m.id},-1)">
                <i class="fa-solid fa-minus" style="font-size:10px;"></i>
            </button>
            <input type="number" class="cant-req-input" id="cantreq-${m.id}"
                   value="1" min="1" step="1" max="${m.stock > 0 ? m.stock : 9999}"
                   onchange="validarCantReq(${m.id},${m.stock})"
                   oninput="validarCantReq(${m.id},${m.stock})">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantReq(${m.id},1)">
                <i class="fa-solid fa-plus" style="font-size:10px;"></i>
            </button>
        </div>
        <button type="button" class="btn btn-sm"
                style="background:rgba(239,68,68,.1);border:none;color:#ef4444;
                       width:34px;height:34px;border-radius:10px;flex-shrink:0;"
                onclick="quitarMatReq(${m.id})">
            <i class="fa-solid fa-xmark"></i>
        </button>`;
    document.getElementById('listaMatsReq').appendChild(div);
    sincronizarReq();
    actualizarEstadoReq();

    if(m.stock <= 0){
        Swal.fire({title:'Sin stock',
            text:`"${m.nombre}" tiene stock 0. Igual puedes incluirlo en el requerimiento.`,
            icon:'info',timer:2500,showConfirmButton:false});
    }
}

function cambiarCantReq(id, delta){
    const inp=document.getElementById('cantreq-'+id);
    if(!inp) return;
    let v=parseInt(inp.value)||0;
    v=Math.max(1,v+delta);
    inp.value=v; if(matsReq[id]) matsReq[id].cant=v;
    sincronizarReq();
}

function validarCantReq(id, maxStock){
    const inp=document.getElementById('cantreq-'+id);
    if(!inp) return;
    let v=parseInt(inp.value)||0;
    if(v<1){inp.value=1;v=1;}
    inp.style.borderColor = (maxStock>0 && v>maxStock)?'#ef4444':'';
    if(matsReq[id]) matsReq[id].cant=v;
    sincronizarReq();
}

function quitarMatReq(id){
    delete matsReq[id];
    const el=document.getElementById('reqmat-'+id);
    if(el){el.style.opacity='0';el.style.transform='translateX(8px)';
           setTimeout(()=>el.remove(),180);}
    sincronizarReq();
    actualizarEstadoReq();
}

function actualizarEstadoReq(){
    const total=Object.keys(matsReq).length;
    document.getElementById('msgSinFilas').style.display=total===0?'block':'none';
    document.getElementById('resumenReq').style.display=total>0?'block':'none';
    document.getElementById('totalItems').textContent=total;
    document.getElementById('btnGuardar').disabled=total===0;
}

function sincronizarReq(){
    const cont=document.getElementById('inputsOcultosReq');
    cont.innerHTML='';
    Object.values(matsReq).forEach(m=>{
        cont.innerHTML+=`<input type="hidden" name="material_id[]" value="${m.id}">
                         <input type="hidden" name="cantidad[]" value="${m.cant}">`;
    });
}

// ── Validación y confirmación antes de enviar ────────────────
document.getElementById('formReq').addEventListener('submit', function(e){
    e.preventDefault();
    const total=Object.keys(matsReq).length;
    if(total===0){
        Swal.fire('Atención','Agrega al menos un material.','warning'); return;
    }
    const nombres=Object.values(matsReq).slice(0,3).map(m=>m.nombre).join(', ')
                  +(total>3?'...':'');
    Swal.fire({
        title:'¿Registrar requerimiento?',
        html:`<div style="font-size:13px;">
                <p class="mb-2">Se generará la carta pedido con <strong>${total}</strong> material(es):</p>
                <p class="text-secondary mb-1">${nombres}</p>
                <p class="mb-0" style="color:#60a5fa;font-size:11px;">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    No se modifica el stock. El stock aumenta cuando ElectroSur entregue los materiales (Entradas).
                </p>
              </div>`,
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#3b82f6',cancelButtonColor:'#64748b',
        confirmButtonText:'<i class="fa-solid fa-file-lines me-1"></i>Generar Requerimiento',
        cancelButtonText:'Revisar'
    }).then(r=>{ if(r.isConfirmed) document.getElementById('formReq').submit(); });
});
</script>

<script>
function confirmarAccion(accion, id, codigo) {
    const config = {
        aprobar: {
            title: '¿Aprobar requerimiento?',
            text:  `El requerimiento ${codigo} será marcado como Aprobado.`,
            icon:  'question',
            btnColor: '#22c55e',
            btnText: '<i class="fa-solid fa-check me-1"></i>Sí, aprobar',
            url: 'requerimiento.php?aprobar=' + id
        },
        anular: {
            title: '¿Anular requerimiento?',
            text:  `El requerimiento ${codigo} será marcado como Anulado.`,
            icon:  'warning',
            btnColor: '#f59e0b',
            btnText: '<i class="fa-solid fa-ban me-1"></i>Sí, anular',
            url: 'requerimiento.php?anular=' + id
        },
        eliminar: {
            title: '¿Eliminar requerimiento?',
            text:  `Se eliminará ${codigo} y todo su detalle. Esta acción no se puede deshacer.`,
            icon:  'error',
            btnColor: '#ef4444',
            btnText: '<i class="fa-solid fa-trash me-1"></i>Sí, eliminar',
            url: 'requerimiento.php?eliminar=' + id
        }
    };
    const c = config[accion];
    Swal.fire({
        title: c.title,
        text:  c.text,
        icon:  c.icon,
        showCancelButton:   true,
        confirmButtonColor: c.btnColor,
        cancelButtonColor:  '#64748b',
        confirmButtonText:  c.btnText,
        cancelButtonText:   'Cancelar'
    }).then(r => { if (r.isConfirmed) window.location.href = c.url; });
}
</script>

<script>
function usarPlantilla(reqId) {
    // Redirigir a Plantillas con contexto del requerimiento preseleccionado
    window.location.href = BASE_URL + '/pages/plantillas.php?tab=word&req_id=' + reqId;
}
</script>

<style>
/* Cards de materiales en Requerimiento — estilo azul */
.mat-req-item {
    display:flex; align-items:center; gap:12px;
    background:rgba(59,130,246,0.06);
    border:1px solid rgba(59,130,246,0.2);
    border-radius:14px; padding:12px 16px;
    margin-bottom:8px; transition:.2s;
    animation:slideReq .2s ease;
}
.mat-req-item:hover { background:rgba(59,130,246,0.1); border-color:rgba(59,130,246,0.35); }
@keyframes slideReq {
    from{opacity:0;transform:translateY(-5px);}
    to  {opacity:1;transform:translateY(0);}
}
.mat-req-icon {
    width:40px;height:40px;border-radius:12px;flex-shrink:0;
    background:rgba(59,130,246,0.15);
    display:flex;align-items:center;justify-content:center;
    color:#60a5fa;font-size:15px;
}
.cant-req-input {
    width:80px;text-align:center;
    background:#0f172a;border:1px solid #334155;
    color:white;border-radius:10px;padding:6px 8px;
    font-size:13px;font-weight:600;
}
.cant-req-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.15);}
body.light-mode .mat-req-item{background:rgba(59,130,246,0.04);}
body.light-mode .cant-req-input{background:#f8fafc;color:#0f172a;border-color:#cbd5e1;}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// ============================================================
// MÓDULO REQUERIMIENTOS — Solicitud de materiales a técnicos
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

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

// ── CREAR REQUERIMIENTO ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {

    $tipo_liq     = in_array($_POST['tipo_liq'] ?? '', ['Instalaciones','Mantenimiento'])
                    ? $_POST['tipo_liq'] : 'Instalaciones';
    $material_ids = $_POST['material_id'] ?? [];
    $cantidades   = $_POST['cantidad']             ?? [];

    // Validar filas
    $filas_validas = [];
    $errores_stock = [];

    foreach ($material_ids as $k => $mid) {
        $mid  = intval($mid);
        $cant = intval($cantidades[$k] ?? 0);
        if ($mid <= 0 || $cant <= 0) continue;

        // Verificar stock real
        $chk = $conn->prepare("SELECT nombre, stock FROM materiales WHERE id=? AND activo=1 LIMIT 1");
        $chk->bind_param('i', $mid);
        $chk->execute();
        $mat = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$mat) continue;
        if ($cant > $mat['stock']) {
            $errores_stock[] = "«{$mat['nombre']}»: solicitado $cant, disponible {$mat['stock']}";
            continue;
        }
        $filas_validas[] = ['id' => $mid, 'cant' => $cant, 'nombre' => $mat['nombre']];
    }

    if (!empty($errores_stock)) {
        $msg = 'Stock insuficiente en: ' . implode('; ', $errores_stock);
        $tipo_msg = 'error';
    } elseif (empty($filas_validas)) {
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

            $stmtD = $conn->prepare(
                "INSERT INTO detalle_requerimiento (requerimiento_id, material_id, cantidad) VALUES (?,?,?)"
            );
            $stmtS = $conn->prepare("UPDATE materiales SET stock = stock - ? WHERE id = ?");

            foreach ($filas_validas as $fv) {
                // Detalle
                $stmtD->bind_param('iii', $req_id, $fv['id'], $fv['cant']);
                $stmtD->execute();
                // Descontar stock
                $stmtS->bind_param('ii', $fv['cant'], $fv['id']);
                $stmtS->execute();
                // Historial
                $conn->prepare("INSERT INTO historial (tipo, material_id, usuario, fecha) VALUES ('REQUERIMIENTO',?,?,NOW())")
                     ->bind_param('is', $fv['id'], $usuario) && true;
                $stmtH = $conn->prepare("INSERT INTO historial (tipo, material_id, usuario, fecha) VALUES ('REQUERIMIENTO',?,?,NOW())");
                $stmtH->bind_param('is', $fv['id'], $usuario);
                $stmtH->execute(); $stmtH->close();
            }
            $stmtD->close(); $stmtS->close();

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
        'creado'   => ['success', 'Requerimiento creado y stock descontado.'],
        'aprobado' => ['success', 'Requerimiento aprobado.'],
        'anulado'  => ['warning', 'Requerimiento anulado.'],
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

    <!-- BOTÓN DE EXPORTACIÓN RÁPIDA tras crear -->
    <?php if (isset($_SESSION['ultimo_req_id']) && $okKey === 'creado'): ?>
    <div class="alert-card success mb-4">
        <i class="fa-solid fa-check-circle" style="color:#22c55e;font-size:22px;flex-shrink:0;"></i>
        <div class="d-flex align-items-center gap-3 flex-wrap w-100">
            <div>
                <strong><?= htmlspecialchars($_SESSION['ultimo_req_codigo']) ?></strong> registrado.
                <span class="text-secondary ms-2">¿Generar documento?</span>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= BASE_URL ?>/exports/generar_word.php?id=<?= $_SESSION['ultimo_req_id'] ?>"
                   class="btn btn-primary btn-sm btn-custom">
                    <i class="fa-solid fa-file-word me-1"></i>Word
                </a>
                <a href="<?= BASE_URL ?>/exports/generar_pdf.php?id=<?= $_SESSION['ultimo_req_id'] ?>"
                   class="btn btn-danger btn-sm btn-custom">
                    <i class="fa-solid fa-file-pdf me-1"></i>PDF
                </a>
                <a href="<?= BASE_URL ?>/pages/ver_requerimiento.php?id=<?= $_SESSION['ultimo_req_id'] ?>"
                   class="btn btn-secondary btn-sm btn-custom" target="_blank">
                    <i class="fa-solid fa-eye me-1"></i>Ver detalle
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

        <!-- Tabla de materiales -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0 fw-semibold">
                    <i class="fa-solid fa-list me-1"></i>Materiales solicitados *
                </label>
                <button type="button" class="btn btn-success btn-sm btn-custom" onclick="agregarFila()">
                    <i class="fa-solid fa-plus me-1"></i>Agregar material
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:40%">
                        <col style="width:12%">
                        <col style="width:10%">
                        <col style="width:22%">
                        <col style="width:10%">
                        <col style="width:6%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Código</th>
                            <th>Stock</th>
                            <th>Cantidad</th>
                            <th>Unidad</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMateriales"></tbody>
                </table>
            </div>
            <div id="msgSinFilas" class="text-secondary text-center py-3" style="display:none;">
                <i class="fa-solid fa-circle-info me-1"></i>
                Haz clic en "Agregar material" para comenzar
            </div>
        </div>

        <!-- Resumen -->
        <div id="resumen" class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-2" style="display:none!important;">
            <div>
                <span class="text-secondary">Total ítems:</span>
                <strong id="totalItems" class="ms-1">0</strong>
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
                            <!-- Word -->
                            <a href="<?= BASE_URL ?>/exports/generar_word.php?id=<?= $r['id'] ?>"
                               class="btn btn-primary btn-sm" title="Descargar Word">
                                <i class="fa-solid fa-file-word"></i>
                            </a>
                            <!-- PDF -->
                            <a href="<?= BASE_URL ?>/exports/generar_pdf.php?id=<?= $r['id'] ?>"
                               class="btn btn-danger btn-sm" title="Descargar PDF">
                                <i class="fa-solid fa-file-pdf"></i>
                            </a>
                            <!-- Aprobar (admin, si pendiente) -->
                            <?php if (esAdmin() && $est === 'Pendiente'): ?>
                            <a href="requerimiento.php?aprobar=<?= $r['id'] ?>"
                               class="btn btn-success btn-sm" title="Aprobar"
                               onclick="return confirm('¿Aprobar este requerimiento?')">
                                <i class="fa-solid fa-check"></i>
                            </a>
                            <!-- Anular -->
                            <a href="requerimiento.php?anular=<?= $r['id'] ?>"
                               class="btn btn-warning btn-sm" title="Anular"
                               onclick="return confirm('¿Anular este requerimiento?')">
                                <i class="fa-solid fa-ban"></i>
                            </a>
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

// ── Contador de filas y estado del botón Guardar ─────────────
function actualizarContador(){
    const filas = document.querySelectorAll('#tbodyMateriales tr.fila-material');
    const validas = [...filas].filter(tr => {
        const id   = tr.querySelector('.inp-mat-id').value;
        const cant = parseInt(tr.querySelector('.inp-cant').value)||0;
        return id > 0 && cant > 0;
    });
    document.getElementById('totalItems').textContent = validas.length;
    document.getElementById('btnGuardar').disabled = validas.length === 0;
    document.getElementById('resumen').style.removeProperty('display');
    document.getElementById('msgSinFilas').style.display = filas.length===0?'block':'none';
}

// ── Agregar fila dinámica ────────────────────────────────────
let filaIdx = 0;

function agregarFila(){
    const tbody = document.getElementById('tbodyMateriales');
    const idx = filaIdx++;

    const tr = document.createElement('tr');
    tr.className = 'fila-material';
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td>
            <input type="hidden" name="material_id[]" class="inp-mat-id" value="">
            <div class="autocomplete-wrap">
                <input type="text"
                       class="form-control input-material-nombre inp-buscar"
                       placeholder="Buscar material..."
                       autocomplete="off"
                       data-idx="${idx}">
                <div class="autocomplete-list" id="ac-${idx}" style="display:none;"></div>
            </div>
        </td>
        <td>
            <span class="inp-codigo text-secondary" style="font-size:12px;">—</span>
        </td>
        <td>
            <span class="inp-stock-badge">—</span>
        </td>
        <td>
            <input type="number" name="cantidad[]"
                   class="form-control form-control-sm inp-cant"
                   min="1" max="0" value="" placeholder="0"
                   disabled style="max-width:100px;">
        </td>
        <td>
            <span class="inp-unidad text-secondary" style="font-size:12px;">—</span>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="quitarFila(this)" title="Quitar">
                <i class="fa-solid fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('.inp-buscar').focus();
    iniciarAutocomplete(tr, idx);
    actualizarContador();
}

// ── Quitar fila ──────────────────────────────────────────────
function quitarFila(btn){
    btn.closest('tr').remove();
    actualizarContador();
}

// ── Autocomplete AJAX ────────────────────────────────────────
function iniciarAutocomplete(tr, idx){
    const input  = tr.querySelector('.inp-buscar');
    const lista  = document.getElementById('ac-' + idx);
    let timer    = null;
    let acIdx    = -1;

    input.addEventListener('input', function(){
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 1) { lista.style.display='none'; limpiarFila(tr); return; }

        timer = setTimeout(()=>{
            fetch(BASE_URL + '/api/buscar_material.php?q=' + encodeURIComponent(q))
            .then(r=>r.json())
            .then(data => renderDropdown(data, lista, tr, idx));
        }, 280);
    });

    // Navegación con teclado
    input.addEventListener('keydown', function(e){
        const items = lista.querySelectorAll('.ac-item');
        if (e.key === 'ArrowDown') { acIdx = Math.min(acIdx+1, items.length-1); resaltarItem(items,acIdx); e.preventDefault(); }
        else if (e.key === 'ArrowUp') { acIdx = Math.max(acIdx-1, 0); resaltarItem(items,acIdx); e.preventDefault(); }
        else if (e.key === 'Enter' && acIdx >= 0) { items[acIdx].click(); e.preventDefault(); }
        else if (e.key === 'Escape') { lista.style.display='none'; acIdx=-1; }
    });

    // Cerrar al hacer click fuera
    document.addEventListener('click', e=>{
        if (!tr.contains(e.target)) lista.style.display='none';
    });
}

function resaltarItem(items, idx){
    items.forEach((el,i) => el.classList.toggle('ac-active', i===idx));
    if(items[idx]) items[idx].scrollIntoView({block:'nearest'});
}

function renderDropdown(data, lista, tr, idx){
    lista.innerHTML = '';
    if (!data.length) {
        lista.innerHTML = '<div class="ac-item"><span class="ac-nombre text-secondary">Sin resultados</span></div>';
        lista.style.display = 'block';
        return;
    }
    data.forEach(m => {
        const div = document.createElement('div');
        div.className = 'ac-item';
        const stockColor = m.stock<=0?'#ef4444':(m.stock<=5?'#f59e0b':'#22c55e');
        div.innerHTML = `
            <span class="ac-nombre">${m.nombre}</span>
            <div class="ac-meta">
                <div>${m.codigo || ''}</div>
                <div style="color:${stockColor};font-weight:700;">Stock: ${m.stock}</div>
            </div>
        `;
        div.addEventListener('click', () => seleccionarMaterial(m, tr, lista));
        lista.appendChild(div);
    });
    lista.style.display = 'block';
}

function seleccionarMaterial(m, tr, lista){
    // Rellenar campos de la fila
    tr.querySelector('.inp-mat-id').value       = m.id;
    tr.querySelector('.inp-buscar').value        = m.nombre;
    tr.querySelector('.inp-codigo').textContent  = m.codigo || '—';
    tr.querySelector('.inp-unidad').textContent  = m.unidad;

    // Badge de stock
    const stock = m.stock;
    let bg = stock<=0?'danger':(stock<=5?'warning':(stock<=10?'info':'success'));
    tr.querySelector('.inp-stock-badge').innerHTML =
        `<span class="badge bg-${bg} stock-badge-inline">${stock}</span>`;

    // Input de cantidad
    const cantInput = tr.querySelector('.inp-cant');
    cantInput.disabled = stock <= 0;
    cantInput.max      = stock;
    cantInput.min      = 1;
    cantInput.value    = stock > 0 ? 1 : 0;
    if (stock <= 0) {
        cantInput.value = 0;
        Swal.fire({title:'Sin stock',text:`"${m.nombre}" no tiene stock disponible.`,icon:'warning',timer:2500});
    }

    // Validación en tiempo real de cantidad
    cantInput.oninput = function(){
        const v = parseInt(this.value)||0;
        if(v > stock){
            this.value = stock;
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '';
        }
        actualizarContador();
    };

    lista.style.display = 'none';
    actualizarContador();
    tr.querySelector('.inp-cant').focus();
}

function limpiarFila(tr){
    tr.querySelector('.inp-mat-id').value      = '';
    tr.querySelector('.inp-codigo').textContent = '—';
    tr.querySelector('.inp-unidad').textContent = '—';
    tr.querySelector('.inp-stock-badge').innerHTML = '—';
    const c = tr.querySelector('.inp-cant');
    c.disabled = true; c.value = ''; c.max = 0;
    actualizarContador();
}

// ── Validación antes de enviar ───────────────────────────────
document.getElementById('formReq').addEventListener('submit', function(e){
    const filas = [...document.querySelectorAll('#tbodyMateriales tr.fila-material')];
    const validas = filas.filter(tr=>{
        const id   = tr.querySelector('.inp-mat-id').value;
        const cant = parseInt(tr.querySelector('.inp-cant').value)||0;
        return id > 0 && cant > 0;
    });
    if(validas.length === 0){
        e.preventDefault();
        Swal.fire('Atención','Agrega al menos un material con cantidad válida.','warning');
        return;
    }
    // Confirmar
    e.preventDefault();
    Swal.fire({
        title:'¿Registrar requerimiento?',
        text:`Se descontará stock de ${validas.length} material(es).`,
        icon:'question',
        showCancelButton:true,
        confirmButtonColor:'#3b82f6',
        cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, registrar',
        cancelButtonText:'Revisar'
    }).then(r=>{ if(r.isConfirmed) document.getElementById('formReq').submit(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

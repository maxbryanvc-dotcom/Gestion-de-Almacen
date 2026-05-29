<?php
// ============================================================
// MÓDULO ÓRDENES DE TRABAJO (OT)
// Tipos: IN=Instalación | CM=Cambio Medidor | MJ=Mejora
//        REUB=Reubicación | REAC=Reactivación
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$msg = $tipo_msg = '';

// ── Catálogo de tipos de OT ──────────────────────────────────
const TIPOS_OT = [
    'IN'   => ['label' => 'Instalación Nueva',   'color' => 'primary'],
    'CM'   => ['label' => 'Cambio de Medidor',   'color' => 'info'],
    'MJ'   => ['label' => 'Mejora',              'color' => 'success'],
    'REUB' => ['label' => 'Reubicación',         'color' => 'warning'],
    'REAC' => ['label' => 'Reactivación',        'color' => 'secondary'],
];

// ── CAMBIAR ESTADO ───────────────────────────────────────────
if (isset($_GET['estado']) && isset($_GET['id'])) {
    $oid    = intval($_GET['id']);
    $estados_validos = ['Programado', 'Aprobado', 'Ejecutado'];
    $nuevo  = in_array($_GET['estado'], $estados_validos) ? $_GET['estado'] : null;

    if ($oid > 0 && $nuevo) {
        $stmt = $conn->prepare("UPDATE ordenes_trabajo SET estado=? WHERE id=?");
        $stmt->bind_param('si', $nuevo, $oid);
        $stmt->execute(); $stmt->close();
        audit_log($conn, 'CAMBIAR_ESTADO_OT', "OT ID $oid → $nuevo");
        header("Location: " . BASE_URL . "/pages/ordenes_trabajo.php?ok=estado"); exit();
    }
}

// ── ELIMINAR OT ──────────────────────────────────────────────
if (isset($_GET['eliminar']) && esAdmin()) {
    $oid = intval($_GET['eliminar']);
    if ($oid > 0) {
        // Restaurar stock de materiales del detalle
        $detalles = $conn->query("SELECT material_id, cantidad FROM detalle_ot WHERE ot_id=$oid");
        while ($d = $detalles->fetch_assoc()) {
            $cant = $d['cantidad']; $mid = $d['material_id'];
            $conn->query("UPDATE materiales SET stock = stock + $cant WHERE id = $mid");
        }
        $stmt = $conn->prepare("DELETE FROM ordenes_trabajo WHERE id=?");
        $stmt->bind_param('i', $oid);
        $stmt->execute(); $stmt->close();
        audit_log($conn, 'ELIMINAR_OT', "OT ID $oid eliminada, stock restaurado");
        header("Location: " . BASE_URL . "/pages/ordenes_trabajo.php?ok=eliminado"); exit();
    }
}

// ── CREAR OT ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {

    $numero_ot     = trim($_POST['numero_ot']    ?? '');
    $tipo          = $_POST['tipo']              ?? '';
    $tecnico_id    = intval($_POST['tecnico_id'] ?? 0);
    $estado        = $_POST['estado']            ?? 'Programado';
    $serie_medidor = trim($_POST['serie_medidor'] ?? '');
    $fecha         = $_POST['fecha']             ?? date('Y-m-d');
    $observacion   = trim($_POST['observacion']  ?? '');
    $material_ids  = $_POST['material_id']       ?? [];
    $cantidades    = $_POST['cantidad']          ?? [];
    $registrado    = $_SESSION['usuario'];

    $tipos_validos  = array_keys(TIPOS_OT);
    $estado_validos = ['Programado', 'Aprobado', 'Ejecutado'];

    if (empty($numero_ot)) {
        $msg = 'El número de OT es obligatorio.'; $tipo_msg = 'error';
    } elseif (!in_array($tipo, $tipos_validos)) {
        $msg = 'Tipo de OT inválido.'; $tipo_msg = 'error';
    } elseif ($tecnico_id <= 0) {
        $msg = 'Selecciona un técnico.'; $tipo_msg = 'error';
    } else {
        // Verificar OT duplicada
        $chkOT = $conn->prepare("SELECT id FROM ordenes_trabajo WHERE numero_ot=? LIMIT 1");
        $chkOT->bind_param('s', $numero_ot);
        $chkOT->execute(); $chkOT->store_result();
        if ($chkOT->num_rows > 0) {
            $msg = "La OT «$numero_ot» ya está registrada."; $tipo_msg = 'error';
            $chkOT->close();
        } else {
            $chkOT->close();

            // Validar materiales y stock
            $filas  = [];
            $errores = [];
            foreach ($material_ids as $k => $mid) {
                $mid  = intval($mid);
                $cant = floatval($cantidades[$k] ?? 0);
                if ($mid <= 0 || $cant <= 0) continue;

                $chkM = $conn->prepare("SELECT nombre, stock FROM materiales WHERE id=? AND activo=1 LIMIT 1");
                $chkM->bind_param('i', $mid);
                $chkM->execute();
                $mat = $chkM->get_result()->fetch_assoc();
                $chkM->close();

                if (!$mat) continue;
                if ($cant > $mat['stock']) {
                    $errores[] = "«{$mat['nombre']}»: necesario $cant, disponible {$mat['stock']}";
                    continue;
                }
                $filas[] = ['id' => $mid, 'cant' => $cant, 'nombre' => $mat['nombre']];
            }

            if (!empty($errores)) {
                $msg = 'Stock insuficiente: ' . implode('; ', $errores); $tipo_msg = 'error';
            } else {
                // Insertar OT
                $stmtOT = $conn->prepare(
                    "INSERT INTO ordenes_trabajo
                     (numero_ot, tipo, tecnico_id, estado, serie_medidor, fecha, registrado_por, observacion)
                     VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmtOT->bind_param('ssisssss',
                    $numero_ot, $tipo, $tecnico_id, $estado,
                    $serie_medidor, $fecha, $registrado, $observacion
                );

                if ($stmtOT->execute()) {
                    $ot_id = $conn->insert_id;
                    $stmtOT->close();

                    // Insertar detalle y descontar stock
                    $stmtD = $conn->prepare(
                        "INSERT INTO detalle_ot (ot_id, material_id, cantidad) VALUES (?,?,?)"
                    );
                    $stmtS = $conn->prepare(
                        "UPDATE materiales SET stock = stock - ? WHERE id = ?"
                    );

                    foreach ($filas as $fv) {
                        $stmtD->bind_param('iid', $ot_id, $fv['id'], $fv['cant']);
                        $stmtD->execute();
                        $stmtS->bind_param('di', $fv['cant'], $fv['id']);
                        $stmtS->execute();
                    }
                    $stmtD->close(); $stmtS->close();

                    audit_log($conn, 'CREAR_OT',
                        "OT $numero_ot | Tipo: $tipo | Técnico: $tecnico_id | Materiales: " . count($filas));

                    header("Location: " . BASE_URL . "/pages/ordenes_trabajo.php?ok=creado"); exit();
                } else {
                    $stmtOT->close();
                    $msg = 'Error al guardar la OT.'; $tipo_msg = 'error';
                }
            }
        }
    }
}

// ── DATOS PARA VISTAS ────────────────────────────────────────
$tecnicos = $conn->query("SELECT id, nombre, cargo FROM tecnicos ORDER BY nombre ASC");

// Filtros de listado
$filtro_tecnico = intval($_GET['tecnico'] ?? 0);
$filtro_tipo    = $_GET['tipo']           ?? '';
$filtro_estado  = $_GET['estado_f']       ?? '';
$filtro_mes     = $_GET['mes']            ?? date('Y-m');

$where = ["DATE_FORMAT(ot.fecha,'%Y-%m') = ?"];
$params = [$filtro_mes]; $types = 's';

if ($filtro_tecnico > 0) { $where[] = 'ot.tecnico_id = ?'; $params[] = $filtro_tecnico; $types .= 'i'; }
if (!empty($filtro_tipo))   { $where[] = 'ot.tipo = ?';    $params[] = $filtro_tipo;    $types .= 's'; }
if (!empty($filtro_estado)) { $where[] = 'ot.estado = ?';  $params[] = $filtro_estado;  $types .= 's'; }

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmtL = $conn->prepare("
    SELECT ot.*, t.nombre AS tecnico_nombre,
           (SELECT COUNT(*) FROM detalle_ot d WHERE d.ot_id = ot.id) AS num_mats
    FROM ordenes_trabajo ot
    JOIN tecnicos t ON t.id = ot.tecnico_id
    $whereSql
    ORDER BY ot.fecha DESC, ot.numero_ot ASC
");
$stmtL->bind_param($types, ...$params);
$stmtL->execute();
$lista = $stmtL->get_result();
$stmtL->close();

// Mensajes OK
$okMsgs = [
    'creado'   => ['success', 'OT registrada correctamente.'],
    'estado'   => ['success', 'Estado actualizado.'],
    'eliminado'=> ['warning', 'OT eliminada y stock restaurado.'],
];
$okKey = $_GET['ok'] ?? '';
if (isset($okMsgs[$okKey])) [$tipo_msg, $msg] = $okMsgs[$okKey];
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>
                Órdenes de Trabajo
            </h2>
            <p class="text-secondary mb-0">Registro diario de trabajos ejecutados en campo</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/exports/reporte_trabajos.php?mes=<?= $filtro_mes ?>" class="btn btn-success btn-custom">
                <i class="fa-solid fa-file-excel me-2"></i>Exportar Excel
            </a>
            <button class="btn btn-primary btn-custom" id="btnToggleForm">
                <i class="fa-solid fa-plus me-2"></i>Registrar OT
            </button>
        </div>
    </div>

    <!-- ALERTAS -->
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

    <!-- ═══ FORMULARIO ═══════════════════════════════════════ -->
    <div id="panelForm" style="display:none;" class="mb-4">
    <div class="card-dashboard">
        <h5 class="fw-bold mb-4">
            <i class="fa-solid fa-plus-circle me-2 text-primary"></i>Nueva Orden de Trabajo
        </h5>

        <form method="POST" id="formOT">
        <input type="hidden" name="accion" value="crear">

        <div class="row g-3 mb-3">

            <!-- N° OT -->
            <div class="col-md-3">
                <label class="form-label">N° Orden de Trabajo *</label>
                <input type="text" name="numero_ot" class="form-control"
                       placeholder="ej. 28565" required maxlength="20"
                       oninput="this.value=this.value.replace(/[^0-9a-zA-Z\-]/g,'')">
            </div>

            <!-- Tipo -->
            <div class="col-md-2">
                <label class="form-label">Tipo *</label>
                <select name="tipo" class="form-select" required id="selTipo">
                    <option value="">Seleccionar...</option>
                    <?php foreach (TIPOS_OT as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $k ?> — <?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Técnico -->
            <div class="col-md-3">
                <label class="form-label">Técnico *</label>
                <select name="tecnico_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php
                    $tecnicos->data_seek(0);
                    while ($t = $tecnicos->fetch_assoc()):
                    ?>
                    <option value="<?= $t['id'] ?>">
                        <?= htmlspecialchars($t['nombre']) ?>
                        <?= $t['cargo'] ? ' — '.$t['cargo'] : '' ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Estado -->
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="Programado">Programado</option>
                    <option value="Aprobado">Aprobado</option>
                    <option value="Ejecutado">Ejecutado</option>
                </select>
            </div>

            <!-- Fecha -->
            <div class="col-md-2">
                <label class="form-label">Fecha *</label>
                <input type="date" name="fecha" class="form-control"
                       value="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- Serie del medidor -->
            <div class="col-md-4" id="campoSerie" style="display:none;">
                <label class="form-label">
                    <i class="fa-solid fa-meter me-1 text-warning"></i>
                    Serie del Medidor
                </label>
                <input type="text" name="serie_medidor" class="form-control"
                       placeholder="Número de serie" maxlength="50">
            </div>

            <!-- Observación -->
            <div class="col-md-8" id="campoObs">
                <label class="form-label">Observación</label>
                <input type="text" name="observacion" class="form-control"
                       placeholder="Notas adicionales..." maxlength="255">
            </div>

        </div>

        <!-- Materiales usados en la OT -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0 fw-semibold">
                    <i class="fa-solid fa-boxes-stacked me-1 text-success"></i>
                    Materiales utilizados
                    <small class="text-secondary fw-normal ms-1">(opcional)</small>
                </label>
                <button type="button" class="btn btn-success btn-sm btn-custom" onclick="agregarFila()">
                    <i class="fa-solid fa-plus me-1"></i>Agregar material
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:42%"><col style="width:13%">
                        <col style="width:11%"><col style="width:22%">
                        <col style="width:8%"> <col style="width:4%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Material</th><th>Código</th>
                            <th>Stock</th><th>Cantidad usada</th>
                            <th>Unidad</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMats"></tbody>
                </table>
            </div>
            <p id="sinMats" class="text-secondary text-center py-2" style="display:none;font-size:13px;">
                <i class="fa-solid fa-circle-info me-1"></i>
                Sin materiales — la OT se registrará sin consumo de stock
            </p>
        </div>

        <div class="d-flex justify-content-end gap-3">
            <button type="button" onclick="cerrarForm()" class="btn btn-secondary btn-custom">
                Cancelar
            </button>
            <button type="submit" class="btn btn-primary btn-custom px-5">
                <i class="fa-solid fa-floppy-disk me-2"></i>Guardar OT
            </button>
        </div>

        </form>
    </div>
    </div>

    <!-- ═══ FILTROS ══════════════════════════════════════════ -->
    <div class="card-dashboard mb-3" style="padding:16px 24px;">
        <form method="GET" class="d-flex gap-3 flex-wrap align-items-end">
            <div>
                <label class="form-label" style="font-size:12px;">Mes</label>
                <input type="month" name="mes" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filtro_mes) ?>">
            </div>
            <div>
                <label class="form-label" style="font-size:12px;">Técnico</label>
                <select name="tecnico" class="form-select form-select-sm" style="min-width:140px;">
                    <option value="">Todos</option>
                    <?php
                    $tecnicos->data_seek(0);
                    while ($t = $tecnicos->fetch_assoc()):
                    ?>
                    <option value="<?= $t['id'] ?>" <?= $filtro_tecnico==$t['id']?'selected':'' ?>>
                        <?= htmlspecialchars($t['nombre']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:12px;">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (TIPOS_OT as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filtro_tipo===$k?'selected':'' ?>>
                        <?= $k ?> — <?= $v['label'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:12px;">Estado</label>
                <select name="estado_f" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="Programado" <?= $filtro_estado==='Programado'?'selected':'' ?>>Programado</option>
                    <option value="Aprobado"   <?= $filtro_estado==='Aprobado'  ?'selected':'' ?>>Aprobado</option>
                    <option value="Ejecutado"  <?= $filtro_estado==='Ejecutado' ?'selected':'' ?>>Ejecutado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm btn-custom">
                <i class="fa-solid fa-filter me-1"></i>Filtrar
            </button>
            <a href="ordenes_trabajo.php" class="btn btn-secondary btn-sm btn-custom">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </form>
    </div>

    <!-- ═══ TABLA OTs ════════════════════════════════════════ -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold mb-0">
                Órdenes del mes: <span style="color:#60a5fa;"><?= date('F Y', strtotime($filtro_mes.'-01')) ?></span>
            </h5>
            <small class="text-secondary"><?= $lista->num_rows ?> registros</small>
        </div>

        <div class="table-responsive">
            <table id="tablaOT" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>N° OT</th>
                        <th>Tipo</th>
                        <th>Técnico</th>
                        <th>Materiales</th>
                        <th>Serie Medidor</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($ot = $lista->fetch_assoc()):
                    $ti    = TIPOS_OT[$ot['tipo']] ?? ['label'=>$ot['tipo'],'color'=>'secondary'];
                    $ecol  = match($ot['estado']) {
                        'Ejecutado'  => 'success',
                        'Aprobado'   => 'primary',
                        default      => 'warning'
                    };
                ?>
                <tr>
                    <td>
                        <strong style="font-size:14px;"><?= htmlspecialchars($ot['numero_ot']) ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-<?= $ti['color'] ?> p-2">
                            <?= $ot['tipo'] ?>
                        </span>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">
                            <?= $ti['label'] ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($ot['tecnico_nombre']) ?></td>
                    <td>
                        <?php if ($ot['num_mats'] > 0): ?>
                        <button class="btn btn-outline-secondary btn-sm"
                                onclick="verMateriales(<?= $ot['id'] ?>, '<?= htmlspecialchars($ot['numero_ot']) ?>')"
                                title="Ver materiales">
                            <i class="fa-solid fa-box me-1"></i><?= $ot['num_mats'] ?>
                        </button>
                        <?php else: ?>
                        <span class="text-secondary" style="font-size:12px;">Sin materiales</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ot['serie_medidor']): ?>
                        <code style="font-size:12px;color:#60a5fa;"><?= htmlspecialchars($ot['serie_medidor']) ?></code>
                        <?php else: ?>
                        <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($ot['fecha'])) ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="badge bg-<?= $ecol ?> border-0 dropdown-toggle"
                                    data-bs-toggle="dropdown" style="cursor:pointer;padding:6px 10px;">
                                <?= $ot['estado'] ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark">
                                <?php foreach (['Programado'=>'warning','Aprobado'=>'primary','Ejecutado'=>'success'] as $e=>$ec): ?>
                                <?php if ($e !== $ot['estado']): ?>
                                <li>
                                    <a class="dropdown-item" href="ordenes_trabajo.php?id=<?= $ot['id'] ?>&estado=<?= $e ?>">
                                        <span class="badge bg-<?= $ec ?> me-2"><?= $e ?></span>
                                        Marcar como <?= $e ?>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (esAdmin()): ?>
                            <button class="btn btn-danger btn-sm"
                                    onclick="eliminarOT(<?= $ot['id'] ?>, '<?= htmlspecialchars($ot['numero_ot']) ?>')"
                                    title="Eliminar OT">
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

<!-- MODAL: Ver materiales de OT -->
<div class="modal fade" id="modalMats" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">
            <i class="fa-solid fa-boxes-stacked me-2 text-success"></i>
            Materiales — OT <span id="modalOTNum"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="modalMatsBody">
        <div class="text-center py-3">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i>
        </div>
    </div>
</div>
</div>
</div>

<script>
// ── Toggle formulario ────────────────────────────────────────
const panelForm = document.getElementById('panelForm');
const btnToggle = document.getElementById('btnToggleForm');

btnToggle.addEventListener('click', ()=>{
    const visible = panelForm.style.display !== 'none';
    panelForm.style.display = visible ? 'none' : 'block';
    btnToggle.innerHTML = visible
        ? '<i class="fa-solid fa-plus me-2"></i>Registrar OT'
        : '<i class="fa-solid fa-minus me-2"></i>Cerrar';
    if (!visible) document.querySelector('[name=numero_ot]').focus();
});

function cerrarForm(){
    panelForm.style.display = 'none';
    btnToggle.innerHTML = '<i class="fa-solid fa-plus me-2"></i>Registrar OT';
}

// ── Mostrar campo serie según tipo ──────────────────────────
document.getElementById('selTipo').addEventListener('change', function(){
    const tiposConMedidor = ['IN','CM','REUB'];
    const mostrar = tiposConMedidor.includes(this.value);
    document.getElementById('campoSerie').style.display = mostrar ? 'block' : 'none';
    document.getElementById('campoObs').className = mostrar ? 'col-md-4' : 'col-md-8';
});

// ── DataTable ───────────────────────────────────────────────
$(document).ready(()=>{
    $('#tablaOT').DataTable({
        responsive:true, pageLength:25, language:dtLang, order:[[5,'desc']],
        columnDefs:[{orderable:false, targets:[7]}]
    });
});

// ── Eliminar OT ─────────────────────────────────────────────
function eliminarOT(id, num){
    Swal.fire({
        title:`¿Eliminar OT ${num}?`,
        text:'Se restaurará el stock de los materiales usados.',
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#ef4444', cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
    }).then(r=>{ if(r.isConfirmed) window.location.href='ordenes_trabajo.php?eliminar='+id; });
}

// ── Ver materiales de OT (AJAX) ──────────────────────────────
function verMateriales(id, num){
    document.getElementById('modalOTNum').textContent = num;
    document.getElementById('modalMatsBody').innerHTML =
        '<div class="text-center py-3"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i></div>';
    new bootstrap.Modal(document.getElementById('modalMats')).show();

    fetch(BASE_URL + '/api/detalle_ot.php?id=' + id)
    .then(r=>r.json())
    .then(data=>{
        if(!data.length){
            document.getElementById('modalMatsBody').innerHTML =
                '<p class="text-secondary text-center py-3">Sin materiales registrados.</p>';
            return;
        }
        let html = '<table class="table table-sm align-middle mb-0"><thead><tr>' +
            '<th>Material</th><th>Código</th><th class="text-end">Cant.</th><th>Unidad</th>' +
            '</tr></thead><tbody>';
        data.forEach(d=>{
            html += `<tr>
                <td><strong>${d.nombre}</strong></td>
                <td><code style="font-size:11px;color:#60a5fa;">${d.codigo||'—'}</code></td>
                <td class="text-end fw-bold">${d.cantidad}</td>
                <td>${d.unidad}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('modalMatsBody').innerHTML = html;
    });
}

// ── Autocomplete materiales (reutiliza lógica de requerimiento) ──
let filaIdx = 0;

function agregarFila(){
    const tbody = document.getElementById('tbodyMats');
    const idx   = filaIdx++;
    const tr    = document.createElement('tr');
    tr.className = 'fila-mat';
    tr.innerHTML = `
        <td>
            <input type="hidden" name="material_id[]" class="inp-id" value="">
            <div style="position:relative;">
                <input type="text" class="form-control form-control-sm inp-buscar"
                       placeholder="Buscar material..." autocomplete="off" data-idx="${idx}">
                <div class="autocomplete-list" id="ac-${idx}" style="display:none;position:absolute;z-index:9999;background:#1e293b;border:1px solid #334155;border-radius:10px;width:100%;max-height:200px;overflow-y:auto;"></div>
            </div>
        </td>
        <td><span class="inp-cod text-secondary" style="font-size:12px;">—</span></td>
        <td><span class="inp-stk">—</span></td>
        <td>
            <input type="number" name="cantidad[]" class="form-control form-control-sm inp-cant"
                   min="0.01" step="0.01" max="0" placeholder="0" disabled style="max-width:90px;">
        </td>
        <td><span class="inp-und text-secondary" style="font-size:12px;">—</span></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();contarFilas()">
                <i class="fa-solid fa-times"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);
    iniciarAC(tr, idx);
    contarFilas();
}

function contarFilas(){
    const n = document.getElementById('tbodyMats').children.length;
    document.getElementById('sinMats').style.display = n===0?'block':'none';
}

function iniciarAC(tr, idx){
    const inp  = tr.querySelector('.inp-buscar');
    const lista= document.getElementById('ac-'+idx);
    let timer  = null;

    inp.addEventListener('input',function(){
        clearTimeout(timer);
        const q = this.value.trim();
        if(q.length<1){ lista.style.display='none'; return; }
        timer = setTimeout(()=>{
            fetch(BASE_URL+'/api/buscar_material.php?q='+encodeURIComponent(q))
            .then(r=>r.json()).then(data=>{
                lista.innerHTML='';
                if(!data.length){ lista.style.display='none'; return; }
                data.forEach(m=>{
                    const div=document.createElement('div');
                    div.style.cssText='padding:8px 12px;cursor:pointer;font-size:13px;display:flex;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.06);';
                    const sc=m.stock<=0?'#ef4444':(m.stock<=5?'#f59e0b':'#22c55e');
                    div.innerHTML=`<span>${m.nombre}</span><span style="color:${sc};font-size:11px;font-weight:700;">Stock: ${m.stock}</span>`;
                    div.addEventListener('mouseenter',()=>div.style.background='rgba(59,130,246,0.18)');
                    div.addEventListener('mouseleave',()=>div.style.background='');
                    div.addEventListener('click',()=>seleccionar(m,tr,lista));
                    lista.appendChild(div);
                });
                lista.style.display='block';
            });
        },280);
    });
    document.addEventListener('click',e=>{ if(!tr.contains(e.target)) lista.style.display='none'; });
}

function seleccionar(m,tr,lista){
    tr.querySelector('.inp-id').value      = m.id;
    tr.querySelector('.inp-buscar').value  = m.nombre;
    tr.querySelector('.inp-cod').textContent = m.codigo||'—';
    tr.querySelector('.inp-und').textContent = m.unidad;
    const bg = m.stock<=0?'danger':(m.stock<=5?'warning':(m.stock<=10?'info':'success'));
    tr.querySelector('.inp-stk').innerHTML =
        `<span class="badge bg-${bg}">${m.stock}</span>`;
    const c=tr.querySelector('.inp-cant');
    c.disabled=m.stock<=0; c.max=m.stock; c.value=m.stock>0?1:0;
    lista.style.display='none';
}

// ── Validación submit ────────────────────────────────────────
document.getElementById('formOT').addEventListener('submit',function(e){
    e.preventDefault();
    Swal.fire({
        title:'¿Guardar Orden de Trabajo?',
        icon:'question',showCancelButton:true,
        confirmButtonColor:'#3b82f6',cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, guardar',cancelButtonText:'Revisar'
    }).then(r=>{ if(r.isConfirmed) this.submit(); });
});
</script>

<style>
.autocomplete-list div:last-child{ border-bottom:none; }
body.light-mode .autocomplete-list{ background:white!important; border-color:#e2e8f0!important; }
body.light-mode .autocomplete-list div{ color:#0f172a!important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

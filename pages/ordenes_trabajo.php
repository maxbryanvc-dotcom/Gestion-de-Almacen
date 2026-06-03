<?php
// ============================================================
// MÓDULO ÓRDENES DE TRABAJO (OT)
// Tipos: IN=Instalación | CM=Cambio Medidor | MJ=Mejora
//        REUB=Reubicación | REAC=Reactivación
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

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

// ── LAYOUT — después de todas las acciones ────────────────────
require_once __DIR__ . '/../includes/layout.php';
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
        <div class="d-flex gap-2 flex-wrap">
            <!-- Excel con plantilla oficial de la empresa -->
            <a href="<?= BASE_URL ?>/exports/generar_trabajos_template.php?mes=<?= $filtro_mes ?>"
               class="btn btn-success btn-custom" title="Genera el Excel con el formato oficial de la empresa">
                <i class="fa-solid fa-file-excel me-2"></i>Excel Oficial
            </a>
            <!-- Excel genérico dinámico -->
            <a href="<?= BASE_URL ?>/exports/reporte_trabajos.php?mes=<?= $filtro_mes ?>"
               class="btn btn-outline-success btn-custom" title="Excel con columnas dinámicas">
                <i class="fa-solid fa-table me-2"></i>Excel Dinámico
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
            <label class="form-label fw-semibold">
                <i class="fa-solid fa-magnifying-glass me-1 text-success"></i>
                Materiales utilizados
                <small class="text-secondary fw-normal ms-1">(opcional)</small>
            </label>
            <div class="d-flex gap-2 mb-1">
                <div style="flex:1;position:relative;">
                    <input type="text" id="buscadorOT" class="form-control"
                           placeholder="Escribe el nombre del material para agregarlo..."
                           autocomplete="off">
                    <div id="dropdownOT" class="drop-container"
                         style="display:none;position:absolute;top:100%;left:0;right:0;
                                z-index:9999;max-height:220px;overflow-y:auto;margin-top:4px;">
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-custom"
                        onclick="document.getElementById('buscadorOT').value='';
                                 document.getElementById('dropdownOT').style.display='none';">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <small class="text-secondary d-block mb-3" style="font-size:11px;">
                <i class="fa-solid fa-circle-info me-1"></i>
                Si no usas materiales, deja este campo vacío y guarda igual.
            </small>

            <!-- Cards de materiales seleccionados -->
            <div id="listaMatsOT"></div>

            <div id="sinMats" class="text-center py-3"
                 style="border:2px dashed rgba(99,102,241,0.2);border-radius:14px;">
                <i class="fa-solid fa-boxes-stacked fa-2x mb-2"
                   style="color:rgba(99,102,241,0.25);"></i>
                <p class="text-secondary mb-0" style="font-size:12px;">
                    Sin materiales — la OT se registrará sin consumo de stock
                </p>
            </div>

            <!-- inputs ocultos para el POST -->
            <div id="inputsOcultos"></div>
        </div>

        <div class="d-flex justify-content-end gap-3 mt-2">
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

// ── Materiales de OT — sistema de cards ──────────────────────
const matsOT = {}; // {id: {id, nombre, codigo, unidad, stock, cant}}
let timerOT  = null;

// Buscador
document.getElementById('buscadorOT').addEventListener('input', function(){
    clearTimeout(timerOT);
    const q   = this.value.trim();
    const drop= document.getElementById('dropdownOT');
    if(q.length < 1){ drop.style.display='none'; return; }

    timerOT = setTimeout(()=>{
        fetch(BASE_URL+'/api/buscar_material.php?q='+encodeURIComponent(q))
        .then(r=>r.json()).then(data=>{
            drop.innerHTML='';
            if(!data.length){
                drop.innerHTML='<div class="drop-item"><span class="drop-item-nombre" style="opacity:.5;">Sin resultados</span></div>';
                drop.style.display='block'; return;
            }
            data.forEach(m=>{
                const yaEsta = !!matsOT[m.id];
                const sc = m.stock<=0?'rojo':(m.stock<=5?'amarillo':'verde');
                const div= document.createElement('div');
                div.className='drop-item'+(yaEsta?' ac-active':'');
                div.innerHTML=`
                    <div class="di-icon" style="background:rgba(99,102,241,0.12);color:#818cf8;">
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
                        agregarMatOT(m);
                        document.getElementById('buscadorOT').value='';
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
    const inp =document.getElementById('buscadorOT');
    const drop=document.getElementById('dropdownOT');
    if(!inp.contains(e.target)&&!drop.contains(e.target)) drop.style.display='none';
});

function agregarMatOT(m){
    if(matsOT[m.id]) return;
    matsOT[m.id]={...m, cant:1};

    const sc = m.stock<=5?'amarillo':'verde';
    const div= document.createElement('div');
    div.id   = 'otmat-'+m.id;
    div.className='mat-ot-item';
    div.innerHTML=`
        <div class="mat-ot-icon"><i class="fa-solid fa-box"></i></div>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;">${m.nombre}</div>
            <div style="font-size:11px;color:#64748b;">
                ${m.codigo?'<span class="me-2">'+m.codigo+'</span>':''}
                <span>${m.unidad}</span>
                <span class="drop-stock ${sc} ms-2" style="font-size:10px;">Disponible: ${m.stock}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantOT(${m.id},-1,${m.stock})">
                <i class="fa-solid fa-minus" style="font-size:10px;"></i>
            </button>
            <input type="number" class="cant-ot-input" id="cantot-${m.id}"
                   value="1" min="0.01" step="0.01" max="${m.stock}"
                   onchange="validarCantOT(${m.id},${m.stock})"
                   oninput="validarCantOT(${m.id},${m.stock})">
            <button type="button" class="btn btn-sm"
                    style="background:rgba(255,255,255,.06);border:none;color:#64748b;
                           width:30px;height:30px;border-radius:8px;"
                    onclick="cambiarCantOT(${m.id},1,${m.stock})">
                <i class="fa-solid fa-plus" style="font-size:10px;"></i>
            </button>
        </div>
        <button type="button" class="btn btn-sm"
                style="background:rgba(239,68,68,.1);border:none;color:#ef4444;
                       width:34px;height:34px;border-radius:10px;flex-shrink:0;"
                onclick="quitarMatOT(${m.id})">
            <i class="fa-solid fa-xmark"></i>
        </button>`;
    document.getElementById('listaMatsOT').appendChild(div);
    sincronizarInputsOcultos();
    actualizarSinMats();
}

function cambiarCantOT(id, delta, max){
    const inp=document.getElementById('cantot-'+id);
    if(!inp) return;
    let v=parseFloat(inp.value)||0;
    v=Math.max(0.01,Math.min(v+delta,max));
    inp.value=v; if(matsOT[id]) matsOT[id].cant=v;
    sincronizarInputsOcultos();
}

function validarCantOT(id, max){
    const inp=document.getElementById('cantot-'+id);
    if(!inp) return;
    let v=parseFloat(inp.value)||0;
    if(v>max){inp.value=max;v=max;}
    if(v<=0){inp.value=0.01;v=0.01;}
    if(matsOT[id]) matsOT[id].cant=v;
    sincronizarInputsOcultos();
}

function quitarMatOT(id){
    delete matsOT[id];
    const el=document.getElementById('otmat-'+id);
    if(el){el.style.opacity='0';el.style.transform='translateX(8px)';
           setTimeout(()=>el.remove(),180);}
    sincronizarInputsOcultos();
    actualizarSinMats();
}

function actualizarSinMats(){
    const n=Object.keys(matsOT).length;
    document.getElementById('sinMats').style.display=n===0?'block':'none';
}

// Sincroniza los inputs ocultos para el POST
function sincronizarInputsOcultos(){
    const cont=document.getElementById('inputsOcultos');
    cont.innerHTML='';
    Object.values(matsOT).forEach(m=>{
        cont.innerHTML+=`<input type="hidden" name="material_id[]" value="${m.id}">
                         <input type="hidden" name="cantidad[]" value="${m.cant}">`;
    });
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
/* Cards de materiales en OT — estilo índigo */
.mat-ot-item {
    display:flex; align-items:center; gap:12px;
    background:rgba(99,102,241,0.06);
    border:1px solid rgba(99,102,241,0.2);
    border-radius:14px; padding:12px 16px;
    margin-bottom:8px; transition:.2s;
    animation:slideOT .2s ease;
}
.mat-ot-item:hover { background:rgba(99,102,241,0.1); border-color:rgba(99,102,241,0.35); }
@keyframes slideOT {
    from{opacity:0;transform:translateY(-5px);}
    to  {opacity:1;transform:translateY(0);}
}
.mat-ot-icon {
    width:40px;height:40px;border-radius:12px;flex-shrink:0;
    background:rgba(99,102,241,0.15);
    display:flex;align-items:center;justify-content:center;
    color:#818cf8;font-size:15px;
}
.cant-ot-input {
    width:80px;text-align:center;
    background:#0f172a;border:1px solid #334155;
    color:white;border-radius:10px;padding:6px 8px;
    font-size:13px;font-weight:600;
}
.cant-ot-input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.15);}
body.light-mode .mat-ot-item{background:rgba(99,102,241,0.04);}
body.light-mode .cant-ot-input{background:#f8fafc;color:#0f172a;border-color:#cbd5e1;}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

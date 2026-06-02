<?php
// ============================================================
// MÓDULO PLANTILLAS DINÁMICAS
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/variables.php';

$msg = $tipo_msg = '';
$tab = $_GET['tab'] ?? 'word';   // word | excel | pdf | variables

// ── SUBIR PLANTILLA ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir') {
    verificarRol(['admin']);

    $nombre      = trim($_POST['nombre']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo        = $_POST['tipo']             ?? 'word';
    $modulo      = $_POST['modulo']           ?? 'general';
    $file        = $_FILES['archivo']         ?? null;

    $tipos_validos  = ['word','excel','pdf'];
    $modulos_validos= ['general','requerimiento','ot','inventario','kardex','liquidacion'];
    $exts_word      = ['docx'];
    $exts_excel     = ['xlsx','xls'];
    $exts_pdf       = ['html','htm'];  // plantillas PDF son HTML

    if (empty($nombre)) {
        $msg = 'El nombre es obligatorio.'; $tipo_msg = 'error';
    } elseif (!in_array($tipo, $tipos_validos)) {
        $msg = 'Tipo de plantilla inválido.'; $tipo_msg = 'error';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Error al subir el archivo. Verifica el tamaño (máx. 10MB).'; $tipo_msg = 'error';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extsValidas = $tipo === 'word' ? $exts_word : ($tipo === 'excel' ? $exts_excel : $exts_pdf);

        if (!in_array($ext, $extsValidas)) {
            $msg = "Extensión .$ext no válida para plantilla $tipo."; $tipo_msg = 'error';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $msg = 'El archivo supera los 10MB.'; $tipo_msg = 'error';
        } else {
            // Nombre único de archivo
            $nombreArchivo = date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/', '_', strtolower($nombre)) . '.' . $ext;
            $destino       = __DIR__ . '/../uploads/plantillas/' . $tipo . '/' . $nombreArchivo;

            if (move_uploaded_file($file['tmp_name'], $destino)) {
                // Detectar variables en el archivo
                $varsDetectadas = detectarVariables($destino, $tipo);

                $stmt = $conn->prepare(
                    "INSERT INTO plantillas (nombre, descripcion, tipo, modulo, archivo, variables_detectadas, creado_por)
                     VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->bind_param('sssssss',
                    $nombre, $descripcion, $tipo, $modulo,
                    $nombreArchivo, $varsDetectadas, $_SESSION['usuario']
                );
                if ($stmt->execute()) {
                    audit_log($conn, 'SUBIR_PLANTILLA', "Plantilla: $nombre ($tipo)");
                    $msg = "Plantilla \"$nombre\" subida correctamente."; $tipo_msg = 'success';
                } else {
                    unlink($destino);
                    $msg = 'Error al guardar en BD.'; $tipo_msg = 'error';
                }
                $stmt->close();
            } else {
                $msg = 'Error al mover el archivo. Verifica permisos de la carpeta uploads/.'; $tipo_msg = 'error';
            }
        }
    }
    header("Location: " . BASE_URL . "/pages/plantillas.php?tab=$tipo&ok=" . urlencode($msg));
    exit();
}

// ── TOGGLE ACTIVO ────────────────────────────────────────────
if (isset($_GET['toggle']) && esAdmin()) {
    $pid = intval($_GET['toggle']);
    $conn->prepare("UPDATE plantillas SET activo = 1-activo WHERE id=?")->bind_param('i',$pid);
    $st = $conn->prepare("UPDATE plantillas SET activo = 1-activo WHERE id=?");
    $st->bind_param('i', $pid); $st->execute(); $st->close();
    audit_log($conn, 'TOGGLE_PLANTILLA', "ID $pid");
    header("Location: " . BASE_URL . "/pages/plantillas.php?tab=$tab"); exit();
}

// ── ELIMINAR ─────────────────────────────────────────────────
if (isset($_GET['eliminar']) && esAdmin()) {
    $pid = intval($_GET['eliminar']);
    $st  = $conn->prepare("SELECT archivo, tipo FROM plantillas WHERE id=? LIMIT 1");
    $st->bind_param('i', $pid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();

    if ($row) {
        $archivo = __DIR__ . '/../uploads/plantillas/' . $row['tipo'] . '/' . $row['archivo'];
        if (file_exists($archivo)) unlink($archivo);
        $st2 = $conn->prepare("DELETE FROM plantillas WHERE id=?");
        $st2->bind_param('i', $pid); $st2->execute(); $st2->close();
        audit_log($conn, 'ELIMINAR_PLANTILLA', "ID $pid");
    }
    header("Location: " . BASE_URL . "/pages/plantillas.php?tab=$tab&ok=eliminado"); exit();
}

// ── DATOS ────────────────────────────────────────────────────
$plantillas = $conn->query("SELECT * FROM plantillas ORDER BY tipo, nombre ASC");
$todas      = $plantillas->fetch_all(MYSQLI_ASSOC);

$porTipo = ['word'=>[], 'excel'=>[], 'pdf'=>[]];
foreach ($todas as $p) $porTipo[$p['tipo']][] = $p;

$catalogo = catalogoVariables();

if (isset($_GET['ok'])) { $msg = urldecode($_GET['ok']); $tipo_msg = 'success'; }

require_once __DIR__ . '/../includes/layout.php';

// ── Helper detectar variables ─────────────────────────────────
function detectarVariables(string $ruta, string $tipo): string {
    $encontradas = [];
    try {
        if ($tipo === 'word') {
            $zip = new ZipArchive();
            if ($zip->open($ruta) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                preg_match_all('/\{\{([a-zA-Z_#]+)\}\}/', $xml, $m);
                $encontradas = array_unique($m[0] ?? []);
            }
        } elseif ($tipo === 'excel') {
            $content = file_get_contents($ruta);
            preg_match_all('/\{\{([a-zA-Z_#]+)\}\}/', $content, $m);
            $encontradas = array_unique($m[0] ?? []);
        } elseif ($tipo === 'pdf') {
            $content = file_get_contents($ruta);
            preg_match_all('/\{\{([a-zA-Z_]+)\}\}/', $content, $m);
            $encontradas = array_unique($m[0] ?? []);
        }
    } catch (\Exception $e) {}
    return implode(', ', $encontradas);
}
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-file-contract me-2 text-primary"></i>
                Plantillas de Documentos
            </h2>
            <p class="text-secondary mb-0">
                Gestiona las plantillas oficiales de la empresa para generación automática de documentos
            </p>
        </div>
        <?php if (esAdmin()): ?>
        <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#modalSubir">
            <i class="fa-solid fa-upload me-2"></i>Subir Plantilla
        </button>
        <?php endif; ?>
    </div>

    <!-- ALERTAS -->
    <?php if ($msg): ?>
    <script>
    document.addEventListener('DOMContentLoaded',()=>Swal.fire({
        title:'<?= $tipo_msg==="success"?"Correcto":"Error" ?>',
        text:'<?= addslashes($msg) ?>',
        icon:'<?= $tipo_msg ?>',
        timer:4000,timerProgressBar:true,confirmButtonColor:'#3b82f6'
    }));
    </script>
    <?php endif; ?>

    <!-- TARJETAS RESUMEN -->
    <div class="row g-3 mb-4">
        <?php
        $iconos  = ['word'=>'fa-file-word','excel'=>'fa-file-excel','pdf'=>'fa-file-pdf'];
        $colores  = ['word'=>'primary','excel'=>'success','pdf'=>'danger'];
        $etiquetas= ['word'=>'Plantillas Word','excel'=>'Plantillas Excel','pdf'=>'Plantillas PDF'];
        foreach (['word','excel','pdf'] as $t):
            $total   = count($porTipo[$t]);
            $activas = count(array_filter($porTipo[$t], fn($p)=>$p['activo']));
        ?>
        <div class="col-md-4">
            <div class="card-dashboard" style="cursor:pointer;border:1px solid rgba(255,255,255,<?= $tab===$t?'.15':'.04' ?>);"
                 onclick="window.location='plantillas.php?tab=<?= $t ?>'">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;">
                            <?= $etiquetas[$t] ?>
                        </p>
                        <h3 class="fw-bold mb-0"><?= $total ?></h3>
                        <small class="text-secondary"><?= $activas ?> activa(s)</small>
                    </div>
                    <div style="width:52px;height:52px;border-radius:14px;
                         background:rgba(<?= $t==='word'?'59,130,246':($t==='excel'?'34,197,94':'239,68,68') ?>,0.15);
                         display:flex;align-items:center;justify-content:center;font-size:22px;
                         color:<?= $t==='word'?'#3b82f6':($t==='excel'?'#22c55e':'#ef4444') ?>;">
                        <i class="fa-solid <?= $iconos[$t] ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- TABS -->
    <div class="card-dashboard mb-3" style="padding:12px 20px;">
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['word'=>'📄 Word','excel'=>'📊 Excel','pdf'=>'🔴 PDF','variables'=>'📋 Variables'] as $k=>$label): ?>
            <a href="plantillas.php?tab=<?= $k ?>"
               class="btn btn-sm btn-custom <?= $tab===$k?($k==='variables'?'btn-secondary':'btn-'.$colores[$k]??'btn-primary'):'btn-outline-secondary' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB PLANTILLAS (word / excel / pdf)
    ══════════════════════════════════════════════════════════ -->
    <?php if (in_array($tab, ['word','excel','pdf'])): ?>
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">
                <i class="fa-solid <?= $iconos[$tab] ?> me-2" style="color:<?= $tab==='word'?'#3b82f6':($tab==='excel'?'#22c55e':'#ef4444') ?>;"></i>
                Plantillas <?= ucfirst($tab) ?>
            </h5>
            <small class="text-secondary"><?= count($porTipo[$tab]) ?> plantilla(s) registrada(s)</small>
        </div>

        <?php if (empty($porTipo[$tab])): ?>
        <div class="text-center py-5">
            <i class="fa-solid <?= $iconos[$tab] ?> fa-3x mb-3 text-secondary" style="opacity:.3;"></i>
            <p class="text-secondary">No hay plantillas <?= ucfirst($tab) ?> cargadas aún.</p>
            <?php if (esAdmin()): ?>
            <button class="btn btn-primary btn-custom mt-2" data-bs-toggle="modal" data-bs-target="#modalSubir"
                    onclick="document.getElementById('sel_tipo').value='<?= $tab ?>'">
                <i class="fa-solid fa-upload me-2"></i>Subir primera plantilla
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($porTipo[$tab] as $p):
                $varsArr = $p['variables_detectadas'] ? explode(', ', $p['variables_detectadas']) : [];
            ?>
            <div class="col-lg-6">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,<?= $p['activo']?'.08':'.03' ?>);">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold" style="font-size:14px;">
                                <?= htmlspecialchars($p['nombre']) ?>
                                <?php if (!$p['activo']): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:10px;">Inactiva</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary" style="font-size:12px;">
                                <?= htmlspecialchars($p['descripcion'] ?: '—') ?>
                            </div>
                        </div>
                        <span class="badge bg-<?= $colores[$p['tipo']] ?>" style="font-size:10px;">
                            <?= strtoupper($p['tipo']) ?>
                        </span>
                    </div>

                    <!-- Módulo + fecha -->
                    <div class="d-flex gap-3 mb-2" style="font-size:11px;color:#64748b;">
                        <span><i class="fa-solid fa-folder me-1"></i><?= htmlspecialchars($p['modulo']) ?></span>
                        <span><i class="fa-solid fa-clock me-1"></i><?= date('d/m/Y', strtotime($p['created_at'])) ?></span>
                        <span><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($p['creado_por']) ?></span>
                    </div>

                    <!-- Variables detectadas -->
                    <?php if (!empty($varsArr)): ?>
                    <div class="mb-3" style="font-size:11px;">
                        <span class="text-secondary me-1">Variables:</span>
                        <?php foreach (array_slice($varsArr, 0, 5) as $v): ?>
                        <span class="badge" style="background:rgba(59,130,246,0.15);color:#60a5fa;margin-right:3px;">
                            <?= htmlspecialchars($v) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if (count($varsArr) > 5): ?>
                        <span class="text-secondary">+<?= count($varsArr)-5 ?> más</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Acciones -->
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Generar documento -->
                        <button class="btn btn-sm btn-primary btn-custom"
                                onclick="abrirGenerador(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>', '<?= $p['tipo'] ?>', '<?= $p['modulo'] ?>')"
                                <?= !$p['activo']?'disabled':'' ?> title="Generar documento">
                            <i class="fa-solid fa-bolt me-1"></i>Usar plantilla
                        </button>

                        <?php if (esAdmin()): ?>
                        <!-- Toggle activo -->
                        <a href="plantillas.php?toggle=<?= $p['id'] ?>&tab=<?= $tab ?>"
                           class="btn btn-sm btn-<?= $p['activo']?'secondary':'success' ?> btn-custom"
                           title="<?= $p['activo']?'Desactivar':'Activar' ?>">
                            <i class="fa-solid fa-toggle-<?= $p['activo']?'on':'off' ?>"></i>
                        </a>

                        <!-- Descargar plantilla original -->
                        <a href="<?= BASE_URL ?>/uploads/plantillas/<?= $p['tipo'] ?>/<?= $p['archivo'] ?>"
                           class="btn btn-sm btn-outline-secondary btn-custom" title="Descargar plantilla original" download>
                            <i class="fa-solid fa-download"></i>
                        </a>

                        <!-- Eliminar -->
                        <button class="btn btn-sm btn-danger btn-custom"
                                onclick="eliminarPlantilla(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>')"
                                title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         TAB VARIABLES
    ══════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'variables'): ?>
    <div class="card-dashboard">
        <div class="mb-4">
            <h5 class="fw-bold mb-1">
                <i class="fa-solid fa-code me-2 text-warning"></i>
                Catálogo de Variables Disponibles
            </h5>
            <p class="text-secondary mb-0" style="font-size:13px;">
                Copia y pega estas variables en tus plantillas Word o Excel.
                El sistema las reemplazará automáticamente con los datos reales.
            </p>
        </div>

        <div class="alert-card info mb-4">
            <i class="fa-solid fa-circle-info" style="color:#3b82f6;font-size:18px;"></i>
            <div>
                <strong>¿Cómo usar las variables?</strong>
                <p class="mb-0 text-secondary" style="font-size:13px;">
                    En tu plantilla Word, escribe la variable exactamente como aparece aquí (con las llaves dobles).
                    Ejemplo: escribe <code>{{fecha}}</code> en el documento y el sistema lo reemplazará por la fecha real.
                    Para tablas de materiales, usa <code>{{mat_nombre}}</code>, <code>{{mat_cantidad}}</code>, etc. en una fila y el sistema clonará la fila automáticamente.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($catalogo as $grupo => $variables): ?>
            <div class="col-lg-6">
                <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;">
                    <h6 class="fw-bold mb-3" style="color:#60a5fa;">
                        <i class="fa-solid fa-tag me-2"></i><?= $grupo ?>
                    </h6>
                    <?php foreach ($variables as $var => $desc): ?>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <code style="background:rgba(59,130,246,0.12);color:#60a5fa;padding:2px 8px;border-radius:6px;font-size:12px;">
                                <?= htmlspecialchars($var) ?>
                            </code>
                            <span class="text-secondary ms-2" style="font-size:12px;"><?= $desc ?></span>
                        </div>
                        <button class="btn btn-sm" style="background:rgba(255,255,255,.05);border:none;color:#64748b;padding:2px 8px;"
                                onclick="copiarVariable('<?= htmlspecialchars($var) ?>')" title="Copiar">
                            <i class="fa-solid fa-copy" style="font-size:11px;"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Variables para tabla de materiales -->
        <div class="mt-4" style="background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,.15);border-radius:14px;padding:20px;">
            <h6 class="fw-bold mb-3" style="color:#22c55e;">
                <i class="fa-solid fa-table me-2"></i>Variables para Tabla de Materiales (fila clonada en Word)
            </h6>
            <p class="text-secondary mb-3" style="font-size:12px;">
                Crea una fila en tu tabla Word con estas variables. El sistema clonará la fila por cada material del requerimiento:
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <?php foreach (['{{item_num}}'=>'N°','{{mat_nombre}}'=>'Nombre','{{mat_codigo}}'=>'Código','{{mat_unidad}}'=>'Unidad','{{mat_cantidad}}'=>'Cantidad'] as $v=>$l): ?>
                <div class="text-center">
                    <code style="background:rgba(34,197,94,0.15);color:#22c55e;padding:4px 10px;border-radius:8px;font-size:12px;display:block;">
                        <?= $v ?>
                    </code>
                    <small class="text-secondary"><?= $l ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: SUBIR PLANTILLA
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSubir" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-upload me-2 text-primary"></i>Subir Nueva Plantilla</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="accion" value="subir">
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Nombre de la plantilla *</label>
                <input type="text" name="nombre" class="form-control" required maxlength="150"
                       placeholder="Ej: Carta Pedido de Materiales">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo *</label>
                <select name="tipo" id="sel_tipo" class="form-select" required onchange="actualizarExt()">
                    <option value="word">📄 Word (.docx)</option>
                    <option value="excel">📊 Excel (.xlsx)</option>
                    <option value="pdf">🔴 PDF (HTML)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Módulo asociado</label>
                <select name="modulo" class="form-select">
                    <option value="general">General</option>
                    <option value="requerimiento">Requerimientos</option>
                    <option value="ot">Órdenes de Trabajo</option>
                    <option value="inventario">Inventario</option>
                    <option value="kardex">Kardex</option>
                    <option value="liquidacion">Liquidación</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" maxlength="255"
                       placeholder="Descripción breve de la plantilla">
            </div>
            <div class="col-12">
                <label class="form-label">Archivo de plantilla *</label>
                <div id="dropZone" class="p-4 text-center rounded-3"
                     style="border:2px dashed #334155;cursor:pointer;transition:.2s;"
                     onclick="document.getElementById('fileInput').click()"
                     ondragover="event.preventDefault();this.style.borderColor='#3b82f6'"
                     ondragleave="this.style.borderColor='#334155'"
                     ondrop="handleDrop(event)">
                    <i class="fa-solid fa-cloud-upload-alt fa-2x mb-2 text-secondary"></i>
                    <p class="mb-1 text-secondary">Arrastra tu archivo aquí o haz clic</p>
                    <small class="text-secondary" id="extHint">Acepta: .docx (máx. 10MB)</small>
                    <div id="fileSelected" class="mt-2" style="display:none;">
                        <i class="fa-solid fa-check-circle text-success me-1"></i>
                        <span id="fileName" class="fw-semibold"></span>
                    </div>
                </div>
                <input type="file" id="fileInput" name="archivo" class="d-none"
                       accept=".docx,.xlsx,.html,.htm" required
                       onchange="mostrarArchivo(this)">
            </div>

            <!-- Info de variables -->
            <div class="col-12">
                <div class="alert-card info" style="padding:14px;">
                    <i class="fa-solid fa-lightbulb" style="color:#3b82f6;font-size:16px;"></i>
                    <div style="font-size:12px;">
                        <strong>Tip:</strong> En tu plantilla Word, coloca variables como
                        <code>{{fecha}}</code>, <code>{{empresa}}</code>, <code>{{req_codigo}}</code>, etc.
                        El sistema las detectará automáticamente al subir el archivo.
                        <a href="plantillas.php?tab=variables" class="ms-1" style="color:#60a5fa;">Ver todas las variables →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary btn-custom">
            <i class="fa-solid fa-upload me-2"></i>Subir Plantilla
        </button>
    </div>
    </form>
</div></div></div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: GENERAR DOCUMENTO
══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalGenerador" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="genTitle">
            <i class="fa-solid fa-bolt me-2 text-warning"></i>Generar Documento
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

        <!-- Selector de contexto según módulo -->
        <div id="ctxRequerimiento" style="display:none;" class="mb-3">
            <label class="form-label">Requerimiento</label>
            <select id="sel_req" class="form-select">
                <option value="">Seleccionar requerimiento...</option>
                <?php
                $reqs = $conn->query("SELECT id, codigo_req, fecha FROM requerimientos ORDER BY id DESC LIMIT 50");
                while ($r = $reqs->fetch_assoc()):
                ?>
                <option value="<?= $r['id'] ?>">
                    <?= htmlspecialchars($r['codigo_req'] ?? 'REQ-'.$r['id']) ?>
                    — <?= $r['fecha'] ? date('d/m/Y', strtotime($r['fecha'])) : '' ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div id="ctxOT" style="display:none;" class="mb-3">
            <label class="form-label">Orden de Trabajo</label>
            <select id="sel_ot" class="form-select">
                <option value="">Seleccionar OT...</option>
                <?php
                $ots = $conn->query("SELECT id, numero_ot, tipo, fecha FROM ordenes_trabajo ORDER BY id DESC LIMIT 50");
                while ($o = $ots->fetch_assoc()):
                ?>
                <option value="<?= $o['id'] ?>">
                    OT <?= htmlspecialchars($o['numero_ot']) ?> (<?= $o['tipo'] ?>)
                    — <?= $o['fecha'] ? date('d/m/Y', strtotime($o['fecha'])) : '' ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Formato de salida -->
        <div class="mb-3">
            <label class="form-label">Formato de salida</label>
            <div class="d-flex gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gen_formato" id="fmtWord" value="word" checked>
                    <label class="form-check-label" for="fmtWord">
                        <i class="fa-solid fa-file-word me-1 text-primary"></i>Word (.docx)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="gen_formato" id="fmtPDF" value="pdf">
                    <label class="form-check-label" for="fmtPDF">
                        <i class="fa-solid fa-file-pdf me-1 text-danger"></i>PDF
                    </label>
                </div>
            </div>
        </div>

        <input type="hidden" id="gen_plantilla_id">
        <input type="hidden" id="gen_tipo">
        <input type="hidden" id="gen_modulo">

    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-warning btn-custom" onclick="generarDocumento()">
            <i class="fa-solid fa-bolt me-2"></i>Generar y Descargar
        </button>
    </div>
</div></div></div>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════ -->
<script>
// ── Drag & Drop ───────────────────────────────────────────────
function handleDrop(e){
    e.preventDefault();
    document.getElementById('dropZone').style.borderColor='#334155';
    const f = e.dataTransfer.files[0];
    if(f){ document.getElementById('fileInput').files = e.dataTransfer.files; mostrarArchivo({files:[f]}); }
}
function mostrarArchivo(inp){
    const f = inp.files?.[0];
    if(!f) return;
    document.getElementById('fileName').textContent = f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
    document.getElementById('fileSelected').style.display='block';
    document.getElementById('dropZone').style.borderColor='#22c55e';
}
function actualizarExt(){
    const t = document.getElementById('sel_tipo').value;
    const hints = {word:'Acepta: .docx (máx. 10MB)', excel:'Acepta: .xlsx (máx. 10MB)', pdf:'Acepta: .html (plantilla HTML)'};
    document.getElementById('extHint').textContent = hints[t];
}

// ── Copiar variable ───────────────────────────────────────────
function copiarVariable(v){
    navigator.clipboard.writeText(v).then(()=>{
        Swal.fire({title:'Copiado',text:v,icon:'success',timer:1500,showConfirmButton:false});
    });
}

// ── Eliminar plantilla ────────────────────────────────────────
function eliminarPlantilla(id, nombre){
    Swal.fire({
        title:'¿Eliminar plantilla?',
        text:`"${nombre}" será eliminada permanentemente.`,
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#ef4444', cancelButtonColor:'#64748b',
        confirmButtonText:'Sí, eliminar', cancelButtonText:'Cancelar'
    }).then(r=>{ if(r.isConfirmed) window.location.href='plantillas.php?eliminar='+id+'&tab=<?= $tab ?>'; });
}

// ── Abrir modal generador ────────────────────────────────────
function abrirGenerador(id, nombre, tipo, modulo){
    document.getElementById('genTitle').innerHTML =
        '<i class="fa-solid fa-bolt me-2 text-warning"></i>Generar: ' + nombre;
    document.getElementById('gen_plantilla_id').value = id;
    document.getElementById('gen_tipo').value   = tipo;
    document.getElementById('gen_modulo').value = modulo;

    // Mostrar selector según módulo
    document.getElementById('ctxRequerimiento').style.display = 'none';
    document.getElementById('ctxOT').style.display            = 'none';

    if(modulo === 'requerimiento') document.getElementById('ctxRequerimiento').style.display='block';
    else if(modulo === 'ot')       document.getElementById('ctxOT').style.display='block';

    // Ocultar PDF si es Excel
    document.getElementById('fmtPDF').parentElement.parentElement.style.display =
        tipo === 'excel' ? 'none' : 'flex';
    if(tipo === 'excel') document.getElementById('fmtWord').checked = true;

    new bootstrap.Modal(document.getElementById('modalGenerador')).show();
}

// ── Generar documento ─────────────────────────────────────────
function generarDocumento(){
    const pid    = document.getElementById('gen_plantilla_id').value;
    const modulo = document.getElementById('gen_modulo').value;
    const formato= document.querySelector('input[name=gen_formato]:checked').value;

    const contexto = {};
    if(modulo === 'requerimiento'){
        const v = document.getElementById('sel_req').value;
        if(!v){ Swal.fire('Aviso','Selecciona un requerimiento','warning'); return; }
        contexto.req_id = v;
    }
    if(modulo === 'ot'){
        const v = document.getElementById('sel_ot').value;
        if(!v){ Swal.fire('Aviso','Selecciona una OT','warning'); return; }
        contexto.ot_id = v;
    }

    // Crear formulario oculto y enviar
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = BASE_URL + '/api/procesar_plantilla.php';

    [['plantilla_id',pid],['formato',formato],['contexto',JSON.stringify(contexto)]].forEach(([k,v])=>{
        const i = document.createElement('input');
        i.type='hidden'; i.name=k; i.value=v;
        form.appendChild(i);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    bootstrap.Modal.getInstance(document.getElementById('modalGenerador')).hide();
    Swal.fire({title:'Generando...', text:'El documento se está descargando.',
               icon:'success', timer:3000, timerProgressBar:true, showConfirmButton:false});
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

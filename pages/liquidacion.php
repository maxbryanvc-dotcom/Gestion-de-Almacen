<?php
// ============================================================
// MÓDULO LIQUIDACIÓN MENSUAL
// Instalaciones Nuevas y Mantenimiento
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../includes/layout.php';

$mes      = $_GET['mes']  ?? date('Y-m');
$tab      = $_GET['tab']  ?? 'inst';   // inst | mmto
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
$tipo_liq = ($tab === 'mmto') ? 'Mantenimiento' : 'Instalaciones';

// Nombre del mes en español
$meses_es = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
             '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
             '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
[$yy, $mm] = explode('-', $mes);
$mesLabel  = $meses_es[$mm] . ' ' . $yy;

// ── CONTRATO (configurable) ───────────────────────────────────
$contrato = 'N° 051 - 2025';

// ── TIPOS DE OT por liquidación ──────────────────────────────
$tipos_inst = ['IN','CM','MJ','REAC','REUB'];
$tipos_mmto = ['CM','MJ','REAC'];
$tipos_activos = ($tipo_liq === 'Instalaciones') ? $tipos_inst : $tipos_mmto;

// ── REQUERIMIENTOS del mes (hasta 3 pedidos) ─────────────────
$stmt_reqs = $conn->prepare("
    SELECT r.id, r.codigo_req, r.fecha,
           (SELECT SUM(d.cantidad) FROM detalle_requerimiento d WHERE d.requerimiento_id=r.id) AS total_cant
    FROM requerimientos r
    WHERE DATE_FORMAT(r.fecha,'%Y-%m') = ?
      AND r.tipo_liq = ?
    ORDER BY r.fecha ASC, r.id ASC
    LIMIT 3
");
$stmt_reqs->bind_param('ss', $mes, $tipo_liq);
$stmt_reqs->execute();
$pedidos = $stmt_reqs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_reqs->close();

// ── MATERIALES del mes ────────────────────────────────────────
// Todos los materiales que aparecen en requerimientos O en OTs del mes
$stmt_mats = $conn->prepare("
    SELECT DISTINCT m.id, m.nombre, m.codigo, m.codigo_electrosur, m.unidad
    FROM materiales m
    WHERE m.activo = 1
      AND (
          EXISTS (
            SELECT 1 FROM detalle_requerimiento dr
            JOIN requerimientos r ON r.id = dr.requerimiento_id
            WHERE dr.material_id = m.id
              AND DATE_FORMAT(r.fecha,'%Y-%m') = ?
              AND r.tipo_liq = ?
          )
          OR
          EXISTS (
            SELECT 1 FROM detalle_ot dot
            JOIN ordenes_trabajo ot ON ot.id = dot.ot_id
            WHERE dot.material_id = m.id
              AND DATE_FORMAT(ot.fecha,'%Y-%m') = ?
          )
      )
    ORDER BY m.nombre ASC
");
$stmt_mats->bind_param('sss', $mes, $tipo_liq, $mes);
$stmt_mats->execute();
$materiales = $stmt_mats->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_mats->close();

if (empty($materiales)) {
    // Si no hay datos aún, mostrar todos los materiales activos
    $materiales = $conn->query(
        "SELECT id, nombre, codigo, codigo_electrosur, unidad FROM materiales WHERE activo=1 ORDER BY nombre ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

$mat_ids = array_column($materiales, 'id');

// ── CANTIDADES DE PEDIDOS por material ────────────────────────
// pedido_cantidades[req_id][mat_id] = cantidad
$pedido_cantidades = [];
foreach ($pedidos as $p) {
    $rid = $p['id'];
    $stmt_d = $conn->prepare(
        "SELECT material_id, cantidad FROM detalle_requerimiento WHERE requerimiento_id=?"
    );
    $stmt_d->bind_param('i', $rid);
    $stmt_d->execute();
    $rows = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_d->close();
    foreach ($rows as $row) {
        $pedido_cantidades[$rid][$row['material_id']] = $row['cantidad'];
    }
}

// ── MATERIALES USADOS EN OTs por tipo ────────────────────────
// uso_por_tipo[mat_id][tipo] = cantidad
$uso_por_tipo = [];
if (!empty($mat_ids)) {
    $placeholders = implode(',', $mat_ids);
    $tipos_in     = "'" . implode("','", $tipos_activos) . "'";
    $uso = $conn->query("
        SELECT dot.material_id, ot.tipo, SUM(dot.cantidad) AS total,
               ot.estado
        FROM detalle_ot dot
        JOIN ordenes_trabajo ot ON ot.id = dot.ot_id
        WHERE DATE_FORMAT(ot.fecha,'%Y-%m') = '$mes'
          AND dot.material_id IN ($placeholders)
        GROUP BY dot.material_id, ot.tipo, ot.estado
    ");
    while ($row = $uso->fetch_assoc()) {
        $mid   = $row['material_id'];
        $tipo  = $row['tipo'];
        $total = (float)$row['total'];
        $est   = $row['estado'];

        // Acumulados
        if (!isset($uso_por_tipo[$mid])) {
            $uso_por_tipo[$mid] = ['total'=>0, 'aprobados'=>[], 'informados'=>[], 'enviados'=>[]];
        }
        $uso_por_tipo[$mid]['total'] += $total;

        // Aprobados = estado Aprobado + Ejecutado
        if (in_array($est, ['Aprobado','Ejecutado'])) {
            $uso_por_tipo[$mid]['aprobados'][$tipo] = ($uso_por_tipo[$mid]['aprobados'][$tipo] ?? 0) + $total;
        }
        // Informados = solo Ejecutado
        if ($est === 'Ejecutado') {
            $uso_por_tipo[$mid]['informados'][$tipo] = ($uso_por_tipo[$mid]['informados'][$tipo] ?? 0) + $total;
        }
    }
}

// ── Calcular totales por material ────────────────────────────
function totalPedidos(array $pedidos, array $cantidades, int $mat_id): float {
    $t = 0;
    foreach ($pedidos as $p) {
        $t += (float)($cantidades[$p['id']][$mat_id] ?? 0);
    }
    return $t;
}
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-file-invoice me-2 text-primary"></i>
                Liquidación Mensual
            </h2>
            <p class="text-secondary mb-0">
                <?= $mesLabel ?> — <?= $tipo_liq ?> — Contrato <?= $contrato ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/exports/liquidacion_excel.php?mes=<?= $mes ?>&tipo=Instalaciones"
               class="btn btn-success btn-custom">
                <i class="fa-solid fa-file-excel me-2"></i>Excel Instalaciones
            </a>
            <a href="<?= BASE_URL ?>/exports/liquidacion_excel.php?mes=<?= $mes ?>&tipo=Mantenimiento"
               class="btn btn-warning btn-custom">
                <i class="fa-solid fa-file-excel me-2"></i>Excel Mantenimiento
            </a>
        </div>
    </div>

    <!-- FILTROS MES + TABS -->
    <div class="card-dashboard mb-3" style="padding:16px 20px;">
        <div class="d-flex gap-4 align-items-center flex-wrap">
            <form method="GET" class="d-flex align-items-center gap-3">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <label class="form-label mb-0 fw-semibold">Mes:</label>
                <input type="month" name="mes" class="form-control form-control-sm"
                       value="<?= $mes ?>" style="max-width:160px;"
                       onchange="this.form.submit()">
            </form>
            <div class="d-flex gap-2">
                <a href="?mes=<?= $mes ?>&tab=inst"
                   class="btn btn-sm <?= $tab==='inst'?'btn-primary':'btn-outline-secondary' ?> btn-custom">
                    <i class="fa-solid fa-bolt me-1"></i>Instalaciones
                </a>
                <a href="?mes=<?= $mes ?>&tab=mmto"
                   class="btn btn-sm <?= $tab==='mmto'?'btn-warning':'btn-outline-secondary' ?> btn-custom">
                    <i class="fa-solid fa-wrench me-1"></i>Mantenimiento
                </a>
            </div>
        </div>
    </div>

    <!-- INFO PEDIDOS DEL MES -->
    <div class="row g-3 mb-4">
        <?php
        $labels_p = ['1er Pedido','2do Pedido','3ro Pedido'];
        for ($pi = 0; $pi < 3; $pi++):
            $p = $pedidos[$pi] ?? null;
        ?>
        <div class="col-md-4">
            <div class="card-dashboard" style="border:1px solid rgba(<?= $p?'59,130,246':'255,255,255' ?>,0.2);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;">
                            <?= $labels_p[$pi] ?>
                        </p>
                        <?php if ($p): ?>
                        <strong style="color:#60a5fa;"><?= htmlspecialchars($p['codigo_req']) ?></strong>
                        <div style="font-size:12px;color:#64748b;">
                            <?= date('d/m/Y', strtotime($p['fecha'])) ?>
                        </div>
                        <?php else: ?>
                        <span class="text-secondary" style="font-size:13px;">Sin pedido registrado</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:28px;opacity:0.2;">
                        <i class="fa-solid fa-file-arrow-up"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- TABLA DE LIQUIDACIÓN -->
    <div class="card-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <?= $tipo_liq === 'Instalaciones'
                    ? '<i class="fa-solid fa-bolt me-2 text-primary"></i>Instalaciones Nuevas'
                    : '<i class="fa-solid fa-wrench me-2 text-warning"></i>Mantenimiento y Reposición' ?>
            </h5>
            <small class="text-secondary"><?= count($materiales) ?> materiales</small>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" id="tablaLiq"
                   style="font-size:12px;min-width:1200px;">
                <thead>
                    <!-- Fila cabecera agrupada -->
                    <tr style="background:#1e3a5f;color:white;">
                        <th rowspan="2" style="width:30px;">N°</th>
                        <th rowspan="2">Código ES</th>
                        <th rowspan="2" style="min-width:200px;">Material</th>
                        <th rowspan="2">Unidad</th>
                        <?php foreach ($pedidos as $pi => $p): ?>
                        <th style="background:#2563eb;text-align:center;">
                            <?= $labels_p[$pi] ?><br>
                            <small style="font-weight:400;"><?= date('d/m/Y', strtotime($p['fecha'])) ?></small>
                        </th>
                        <?php endforeach; ?>
                        <?php for ($x = count($pedidos); $x < 3; $x++): ?>
                        <th style="background:#334155;text-align:center;opacity:0.5;"><?= $labels_p[$x] ?></th>
                        <?php endfor; ?>
                        <th style="background:#1f3864;text-align:center;">TOTAL</th>
                        <!-- APROBADOS -->
                        <?php foreach ($tipos_activos as $t): ?>
                        <th style="background:#0f2e57;text-align:center;"><?= $t ?></th>
                        <?php endforeach; ?>
                        <th style="background:#1e3a5f;text-align:center;border-left:2px solid #3b82f6;">T.APROBADOS</th>
                        <!-- INFORMADOS -->
                        <?php foreach ($tipos_activos as $t): ?>
                        <th style="background:#0e2840;text-align:center;"><?= $t ?></th>
                        <?php endforeach; ?>
                        <th style="background:#1e3a5f;text-align:center;border-left:2px solid #22c55e;">T.INFORMADOS</th>
                        <!-- TOTALES FINALES -->
                        <th style="background:#1a2e40;text-align:center;">ENVIADOS</th>
                        <th style="background:#16a34a;text-align:center;">USADOS</th>
                        <th style="background:#dc2626;text-align:center;">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                <?php $n = 1; foreach ($materiales as $mat):
                    $mid = $mat['id'];
                    $uso = $uso_por_tipo[$mid] ?? ['total'=>0,'aprobados'=>[],'informados'=>[]];

                    // Total de pedidos
                    $total_ped = totalPedidos($pedidos, $pedido_cantidades, $mid);
                    $total_aprob = array_sum($uso['aprobados']);
                    $total_inf   = array_sum($uso['informados']);
                    $total_env   = $total_aprob; // enviados = aprobados (simplificado)
                    $usados      = $uso['total'];
                    $saldo       = $total_ped - $usados;

                    $rowBg = ($n % 2 === 0) ? 'rgba(255,255,255,0.03)' : '';
                ?>
                <tr style="background:<?= $rowBg ?>;">
                    <td><?= $n++ ?></td>
                    <td>
                        <code style="font-size:11px;color:#60a5fa;">
                            <?= htmlspecialchars($mat['codigo_electrosur'] ?: $mat['codigo']) ?>
                        </code>
                    </td>
                    <td><strong><?= htmlspecialchars($mat['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($mat['unidad']) ?></td>

                    <?php foreach ($pedidos as $p): ?>
                    <td style="text-align:center;">
                        <?= ($pedido_cantidades[$p['id']][$mid] ?? 0) ?: '' ?>
                    </td>
                    <?php endforeach; ?>
                    <?php for ($x = count($pedidos); $x < 3; $x++): ?>
                    <td style="text-align:center;opacity:0.3;">—</td>
                    <?php endfor; ?>

                    <td style="text-align:center;font-weight:700;color:#60a5fa;">
                        <?= $total_ped ?: '' ?>
                    </td>

                    <!-- APROBADOS por tipo -->
                    <?php foreach ($tipos_activos as $t): ?>
                    <td style="text-align:center;">
                        <?= ($uso['aprobados'][$t] ?? 0) ?: '' ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="text-align:center;font-weight:700;border-left:2px solid #3b82f6;color:#93c5fd;">
                        <?= $total_aprob ?: '' ?>
                    </td>

                    <!-- INFORMADOS por tipo -->
                    <?php foreach ($tipos_activos as $t): ?>
                    <td style="text-align:center;">
                        <?= ($uso['informados'][$t] ?? 0) ?: '' ?>
                    </td>
                    <?php endforeach; ?>
                    <td style="text-align:center;font-weight:700;border-left:2px solid #22c55e;color:#86efac;">
                        <?= $total_inf ?: '' ?>
                    </td>

                    <td style="text-align:center;"><?= $total_env ?: '' ?></td>
                    <td style="text-align:center;font-weight:700;color:#4ade80;">
                        <?= $usados ?: '' ?>
                    </td>
                    <td style="text-align:center;font-weight:700;
                        color:<?= $saldo < 0 ? '#f87171' : ($saldo === 0.0 ? '#94a3b8' : '#fbbf24') ?>;">
                        <?= $saldo !== 0.0 ? $saldo : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- LEYENDA -->
        <div class="d-flex gap-4 flex-wrap mt-3" style="font-size:12px;">
            <span><i class="fa-solid fa-circle text-primary me-1"></i>APROBADOS = OTs aprobadas + ejecutadas</span>
            <span><i class="fa-solid fa-circle text-success me-1"></i>INFORMADOS = OTs ejecutadas</span>
            <span><i class="fa-solid fa-circle text-warning me-1"></i>USADOS = total materiales en todas las OTs del mes</span>
        </div>
    </div>

</div>

<script>
$(document).ready(function(){
    // No usar DataTable aquí para preservar el formato de cabecera agrupada
    // El scroll horizontal está incluido en table-responsive
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

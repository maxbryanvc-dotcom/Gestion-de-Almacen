<?php
// ============================================================
// DASHBOARD — Panel general del sistema
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

// KPIs
$kpis = [];
$kpis['materiales'] = $conn->query("SELECT COUNT(*) c FROM materiales WHERE activo = 1 ")->fetch_assoc()['c'];
$kpis['entradas']   = $conn->query("SELECT COUNT(*) c FROM entradas")->fetch_assoc()['c'];
$kpis['salidas']    = $conn->query("SELECT COUNT(*) c FROM salidas")->fetch_assoc()['c'];
$kpis['reingresos'] = $conn->query("SELECT COUNT(*) c FROM reingresos")->fetch_assoc()['c'];
$kpis['reqs']       = $conn->query("SELECT COUNT(*) c FROM requerimientos")->fetch_assoc()['c'];
$kpis['stock_crit'] = $conn->query("SELECT COUNT(*) c FROM materiales WHERE activo = 1 AND stock <= " . STOCK_CRITICO)->fetch_assoc()['c'];
$kpis['stock_bajo'] = $conn->query("SELECT COUNT(*) c FROM materiales WHERE activo = 1 AND stock > " . STOCK_CRITICO . " AND stock <= " . STOCK_BAJO)->fetch_assoc()['c'];
$kpis['agotados']   = $conn->query("SELECT COUNT(*) c FROM materiales WHERE activo = 1 AND stock <= 0")->fetch_assoc()['c'];

// Entradas y salidas de los últimos 7 días (para gráfico diario)
$datosGraf = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $ent = $conn->query("SELECT COUNT(*) c FROM entradas WHERE DATE(fecha)='$dia'")->fetch_assoc()['c'];
    $sal = $conn->query("SELECT COUNT(*) c FROM salidas  WHERE DATE(fecha)='$dia'")->fetch_assoc()['c'];
    $rei = $conn->query("SELECT COUNT(*) c FROM reingresos WHERE DATE(fecha)='$dia'")->fetch_assoc()['c'];
    $datosGraf[] = ['dia' => date('d/m', strtotime($dia)), 'ent' => $ent, 'sal' => $sal, 'rei' => $rei];
}

// Top 5 materiales más salidas
$topMateriales = $conn->query("
    SELECT m.nombre, SUM(s.cantidad) total
    FROM salidas s
    JOIN materiales m ON m.id = s.material_id
    GROUP BY m.nombre
    ORDER BY total DESC LIMIT 5
");

// Top 5 técnicos con más salidas
$topTecnicos = $conn->query("
    SELECT t.nombre, COUNT(s.id) total
    FROM salidas s
    JOIN tecnicos t ON t.id = s.tecnico_id
    GROUP BY t.nombre
    ORDER BY total DESC LIMIT 5
");

// Materiales con stock crítico
$criticos = $conn->query("
    SELECT id, codigo, nombre, stock
    FROM materiales WHERE activo = 1 AND stock <= " . STOCK_BAJO . "
    ORDER BY stock ASC LIMIT 8
");

// Actividad reciente (últimos 6 movimientos de salidas)
$actividad = $conn->query("
    SELECT s.fecha, m.nombre AS material, s.cantidad, t.nombre AS tecnico
    FROM salidas s
    JOIN materiales m ON m.id = s.material_id
    LEFT JOIN tecnicos t ON t.id = s.tecnico_id
    ORDER BY s.fecha DESC LIMIT 6
");

// Reingresos recientes
$reingresos_rec = $conn->query("
    SELECT r.fecha, m.nombre AS material, r.cantidad, r.motivo
    FROM reingresos r
    JOIN materiales m ON m.id = r.material_id
    ORDER BY r.fecha DESC LIMIT 4
");
?>

<div class="container-fluid">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Dashboard</h2>
            <p class="text-secondary mb-0">
                <?= date('l, d \d\e F Y') ?> &nbsp;|&nbsp;
                Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario']) ?></strong>
            </p>
        </div>
        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
        <button class="btn btn-warning btn-custom" onclick="mostrarGeneradorCodigo()" title="Generar código de permiso temporal">
            <i class="fa-solid fa-key me-2"></i>Generar Permiso
        </button>
        <?php endif; ?>
    </div>

    <!-- ALERTAS CRÍTICAS -->
    <?php if ($kpis['agotados'] > 0 || $kpis['stock_crit'] > 0): ?>
    <div class="alert-card danger mb-4">
        <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i>
        <div>
            <strong>Atención requerida: </strong>
            <?php if ($kpis['agotados'] > 0): ?>
            <span><?= $kpis['agotados'] ?> material(es) agotado(s). </span>
            <?php endif; ?>
            <?php if ($kpis['stock_crit'] > 0): ?>
            <span><?= $kpis['stock_crit'] ?> material(es) en stock crítico (≤<?= STOCK_CRITICO ?>).</span>
            <?php endif; ?>
            <a href="materiales.php" class="ms-2" style="color:#fca5a5;">Ver inventario →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI CARDS — fila 1 -->
    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">
            <div class="card-dashboard">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Total Materiales</p>
                        <h2 class="fw-bold mb-0"><?= $kpis['materiales'] ?></h2>
                        <small class="text-secondary">en inventario</small>
                    </div>
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;color:#3b82f6;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card-dashboard">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Entradas</p>
                        <h2 class="fw-bold mb-0"><?= $kpis['entradas'] ?></h2>
                        <small class="text-secondary">ingresos totales</small>
                    </div>
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(34,197,94,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;color:#22c55e;">
                        <i class="fa-solid fa-arrow-down-to-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card-dashboard">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Salidas</p>
                        <h2 class="fw-bold mb-0"><?= $kpis['salidas'] ?></h2>
                        <small class="text-secondary">despachos totales</small>
                    </div>
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;color:#ef4444;">
                        <i class="fa-solid fa-arrow-up-from-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card-dashboard">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Reingresos</p>
                        <h2 class="fw-bold mb-0"><?= $kpis['reingresos'] ?></h2>
                        <small class="text-secondary">devoluciones</small>
                    </div>
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(139,92,246,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;color:#8b5cf6;">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- KPI CARDS — fila 2 (stock alertas) -->
    <div class="row g-3 mb-4">

        <div class="col-xl-4 col-md-6">
            <div class="card-dashboard" style="border:1px solid rgba(239,68,68,0.2);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:46px;height:46px;border-radius:14px;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;font-size:18px;color:#ef4444;flex-shrink:0;">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-secondary" style="font-size:12px;">Agotados</p>
                        <h4 class="fw-bold mb-0" style="color:#ef4444;"><?= $kpis['agotados'] ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card-dashboard" style="border:1px solid rgba(245,158,11,0.2);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:46px;height:46px;border-radius:14px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:18px;color:#f59e0b;flex-shrink:0;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-secondary" style="font-size:12px;">Stock Crítico (≤<?= STOCK_CRITICO ?>)</p>
                        <h4 class="fw-bold mb-0" style="color:#f59e0b;"><?= $kpis['stock_crit'] ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card-dashboard" style="border:1px solid rgba(59,130,246,0.2);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:46px;height:46px;border-radius:14px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;font-size:18px;color:#3b82f6;flex-shrink:0;">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-secondary" style="font-size:12px;">Requerimientos</p>
                        <h4 class="fw-bold mb-0"><?= $kpis['reqs'] ?></h4>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- GRÁFICO MOVIMIENTOS 7 DÍAS + ALERTAS -->
    <div class="row g-4 mb-4">

        <div class="col-lg-8">
            <div class="card-dashboard h-100">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1">Movimientos — Últimos 7 días</h5>
                    <small class="text-secondary">Entradas, salidas y reingresos diarios</small>
                </div>
                <div id="graficoMovimientos"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-dashboard h-100">
                <div class="mb-3">
                    <h5 class="fw-bold mb-1">Estado del Almacén</h5>
                    <small class="text-secondary">Resumen rápido</small>
                </div>

                <div class="alert-card danger">
                    <i class="fa-solid fa-ban" style="color:#ef4444;"></i>
                    <div>
                        <strong>Agotados</strong>
                        <p class="mb-0 text-secondary" style="font-size:12px;"><?= $kpis['agotados'] ?> producto(s) sin stock</p>
                    </div>
                </div>
                <div class="alert-card warning">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
                    <div>
                        <strong>Stock crítico</strong>
                        <p class="mb-0 text-secondary" style="font-size:12px;"><?= $kpis['stock_crit'] ?> producto(s) ≤ <?= STOCK_CRITICO ?> unidades</p>
                    </div>
                </div>
                <div class="alert-card info">
                    <i class="fa-solid fa-box-open" style="color:#3b82f6;"></i>
                    <div>
                        <strong>Stock bajo</strong>
                        <p class="mb-0 text-secondary" style="font-size:12px;"><?= $kpis['stock_bajo'] ?> producto(s) ≤ <?= STOCK_BAJO ?> unidades</p>
                    </div>
                </div>
                <div class="alert-card success">
                    <i class="fa-solid fa-rotate-left" style="color:#22c55e;"></i>
                    <div>
                        <strong>Reingresos</strong>
                        <p class="mb-0 text-secondary" style="font-size:12px;"><?= $kpis['reingresos'] ?> devoluciones registradas</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- TOP MATERIALES + TOP TÉCNICOS -->
    <div class="row g-4 mb-4">

        <div class="col-lg-6">
            <div class="card-dashboard h-100">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1">Top Materiales más despachados</h5>
                    <small class="text-secondary">Por volumen de salidas</small>
                </div>
                <div id="graficoTopMateriales"></div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-dashboard h-100">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1">Top Técnicos con más salidas</h5>
                    <small class="text-secondary">Por número de pedidos</small>
                </div>
                <div id="graficoTopTecnicos"></div>
            </div>
        </div>

    </div>

    <!-- STOCK CRÍTICO + ACTIVIDAD RECIENTE + REINGRESOS -->
    <div class="row g-4">

        <!-- Stock crítico -->
        <div class="col-lg-4">
            <div class="card-dashboard h-100">
                <div class="mb-3">
                    <h5 class="fw-bold mb-1">
                        <i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>
                        Stock Crítico
                    </h5>
                    <small class="text-secondary">Materiales que necesitan reposición</small>
                </div>
                <?php while ($c = $criticos->fetch_assoc()): ?>
                <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <div>
                        <strong style="font-size:13px;"><?= htmlspecialchars($c['nombre']) ?></strong>
                        <br><small class="text-secondary"><?= htmlspecialchars($c['codigo'] ?? '') ?></small>
                    </div>
                    <?= stock_badge((int)$c['stock']) ?>
                </div>
                <?php endwhile; ?>
                <div class="mt-3">
                    <a href="materiales.php" class="btn btn-sm btn-outline-warning btn-custom w-100">
                        Ver inventario completo →
                    </a>
                </div>
            </div>
        </div>

        <!-- Actividad reciente -->
        <div class="col-lg-4">
            <div class="card-dashboard h-100">
                <div class="mb-3">
                    <h5 class="fw-bold mb-1">
                        <i class="fa-solid fa-clock me-2 text-primary"></i>
                        Salidas Recientes
                    </h5>
                    <small class="text-secondary">Últimos despachos</small>
                </div>
                <?php while ($a = $actividad->fetch_assoc()): ?>
                <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,0.12);display:flex;align-items:center;justify-content:center;color:#ef4444;flex-shrink:0;font-size:14px;">
                        <i class="fa-solid fa-arrow-up"></i>
                    </div>
                    <div style="min-width:0;">
                        <strong style="font-size:13px;"><?= htmlspecialchars($a['material']) ?></strong>
                        <br><small class="text-secondary"><?= htmlspecialchars($a['tecnico'] ?? '—') ?> | <?= $a['cantidad'] ?> und.</small>
                    </div>
                    <small class="text-secondary ms-auto flex-shrink-0"><?= date('d/m', strtotime($a['fecha'])) ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Reingresos recientes -->
        <div class="col-lg-4">
            <div class="card-dashboard h-100">
                <div class="mb-3">
                    <h5 class="fw-bold mb-1">
                        <i class="fa-solid fa-rotate-left me-2 text-success"></i>
                        Reingresos Recientes
                    </h5>
                    <small class="text-secondary">Últimas devoluciones</small>
                </div>
                <?php while ($rr = $reingresos_rec->fetch_assoc()): ?>
                <div class="d-flex align-items-center gap-3 py-2" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(34,197,94,0.12);display:flex;align-items:center;justify-content:center;color:#22c55e;flex-shrink:0;font-size:14px;">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>
                    <div style="min-width:0;">
                        <strong style="font-size:13px;"><?= htmlspecialchars($rr['material']) ?></strong>
                        <br><small class="text-secondary"><?= htmlspecialchars($rr['motivo']) ?></small>
                    </div>
                    <span class="badge bg-success ms-auto flex-shrink-0">+<?= $rr['cantidad'] ?></span>
                </div>
                <?php endwhile; ?>
                <div class="mt-3">
                    <a href="reingreso_material.php" class="btn btn-sm btn-outline-success btn-custom w-100">
                        Ver reingresos →
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- MODAL GENERADOR DE CÓDIGO (admin) -->
<?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
<div class="modal fade" id="modalGenerarCodigo" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-key me-2 text-warning"></i>Generar Código de Permiso</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <p class="text-secondary mb-3" style="font-size:13px;">
            Genera un código temporal para que un almacenero pueda realizar
            acciones restringidas. Válido por <?= PERMISO_MINUTOS ?> minutos.
        </p>
        <div class="mb-3">
            <label class="form-label">Usuario solicitante</label>
            <input type="text" id="gen_solicitante" class="form-control" placeholder="Nombre del almacenero">
        </div>
        <div class="mb-3">
            <label class="form-label">Descripción de la acción</label>
            <input type="text" id="gen_descripcion" class="form-control" value="Edición/eliminación de material">
        </div>
        <button class="btn btn-warning btn-custom w-100" onclick="generarCodigo()">
            <i class="fa-solid fa-dice me-2"></i>Generar Código Seguro
        </button>
        <div id="codigoGenerado" class="text-center mt-4 p-3" style="display:none;background:rgba(34,197,94,0.08);border-radius:16px;border:1px solid rgba(34,197,94,0.2);">
            <p class="text-secondary mb-2" style="font-size:12px;">Código generado (válido <?= PERMISO_MINUTOS ?> min):</p>
            <div style="font-size:44px;font-weight:800;letter-spacing:12px;color:#22c55e;" id="codigoTexto"></div>
            <small class="text-secondary">Entrega este código solo al almacenero autorizado</small>
        </div>
    </div>
</div>
</div>
</div>
<?php endif; ?>

<script>
<?php
// Datos para gráficos
$dias   = array_column($datosGraf, 'dia');
$ents   = array_column($datosGraf, 'ent');
$sals   = array_column($datosGraf, 'sal');
$reis   = array_column($datosGraf, 'rei');

$topMatNombres = $topMatCantidades = $topTecNombres = $topTecCantidades = [];
while ($tm = $topMateriales->fetch_assoc())  { $topMatNombres[] = $tm['nombre']; $topMatCantidades[] = (int)$tm['total']; }
while ($tt = $topTecnicos->fetch_assoc())     { $topTecNombres[] = $tt['nombre']; $topTecCantidades[] = (int)$tt['total']; }
?>

/* ============ GRÁFICO MOVIMIENTOS 7 DÍAS ============ */
new ApexCharts(document.querySelector('#graficoMovimientos'), {
    chart:  { type:'bar', height:280, toolbar:{show:false}, background:'transparent' },
    series: [
        { name:'Entradas',  data: <?= json_encode($ents) ?> },
        { name:'Salidas',   data: <?= json_encode($sals) ?> },
        { name:'Reingresos',data: <?= json_encode($reis) ?> },
    ],
    xaxis:      { categories: <?= json_encode($dias) ?>, labels:{style:{colors:'#64748b'}} },
    yaxis:      { labels:{style:{colors:'#64748b'}} },
    colors:     ['#22c55e','#ef4444','#8b5cf6'],
    plotOptions:{ bar:{ borderRadius:6, columnWidth:'55%' } },
    dataLabels: { enabled:false },
    grid:       { borderColor:'#1e293b' },
    legend:     { labels:{colors:'#94a3b8'} },
    tooltip:    { theme:'dark' },
}).render();

/* ============ TOP MATERIALES ============ */
new ApexCharts(document.querySelector('#graficoTopMateriales'), {
    chart:  { type:'bar', height:260, toolbar:{show:false}, background:'transparent' },
    series: [{ name:'Unidades despachadas', data: <?= json_encode($topMatCantidades) ?> }],
    xaxis:  { categories: <?= json_encode($topMatNombres) ?>, labels:{style:{colors:'#64748b'}, maxLength:14} },
    yaxis:  { labels:{style:{colors:'#64748b'}} },
    colors: ['#3b82f6'],
    plotOptions:{ bar:{ borderRadius:6, columnWidth:'50%', horizontal:false } },
    dataLabels:{ enabled:true, style:{colors:['#94a3b8']} },
    grid:   { borderColor:'#1e293b' },
    tooltip:{ theme:'dark' },
}).render();

/* ============ TOP TÉCNICOS ============ */
new ApexCharts(document.querySelector('#graficoTopTecnicos'), {
    chart:  { type:'donut', height:260, background:'transparent' },
    series: <?= json_encode($topTecCantidades) ?>,
    labels: <?= json_encode($topTecNombres) ?>,
    colors: ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6'],
    legend: { position:'bottom', labels:{colors:'#94a3b8'} },
    dataLabels:{ style:{colors:['#fff']} },
    tooltip:{ theme:'dark' },
    plotOptions:{ pie:{ donut:{ size:'60%' } } },
}).render();

<?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
function mostrarGeneradorCodigo(){
    new bootstrap.Modal(document.getElementById('modalGenerarCodigo')).show();
}
function generarCodigo(){
    const sol  = document.getElementById('gen_solicitante').value;
    const desc = document.getElementById('gen_descripcion').value;
    fetch(BASE_URL + '/api/permiso_temporal.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`accion=generar&solicitante=${encodeURIComponent(sol)}&descripcion=${encodeURIComponent(desc)}`
    }).then(r=>r.json()).then(data=>{
        if(data.ok){
            document.getElementById('codigoTexto').textContent = data.codigo;
            document.getElementById('codigoGenerado').style.display = 'block';
        }
    });
}
<?php endif; ?>
</script>

<script>
// ============================================================
// ALERTAS DE SOLICITUDES DE PERMISO (solo Administrador)
// Consulta cada 8 segundos si hay almaceneros esperando código
// ============================================================
<?php if(($_SESSION['rol']??'') === 'admin'): ?>
(function(){
    let solicitudesVistas = new Set();
    let intervalSolicitudes = null;

    function tonoSolicitud(){
        try{
            const ctx=new(window.AudioContext||window.webkitAudioContext)();
            const osc=ctx.createOscillator(); const gain=ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type='sine';
            osc.frequency.setValueAtTime(1047,ctx.currentTime);
            osc.frequency.setValueAtTime(784,ctx.currentTime+0.15);
            osc.frequency.setValueAtTime(1047,ctx.currentTime+0.3);
            gain.gain.setValueAtTime(0.2,ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.5);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime+0.5);
        }catch(e){}
    }

    function mostrarAlertaSolicitud(sol){
        // Evitar mostrar la misma alerta dos veces
        if(solicitudesVistas.has(sol.id)) return;
        solicitudesVistas.add(sol.id);

        const hace = sol.segundos < 60
            ? 'hace ' + sol.segundos + ' seg'
            : 'hace ' + Math.floor(sol.segundos/60) + ' min';

        const toast = document.createElement('div');
        toast.id = 'solToast_' + sol.id;
        toast.style.cssText=`
            position:fixed; top:80px; right:24px; z-index:99999;
            background:linear-gradient(135deg,#1e3a5f,#1e293b);
            border:1px solid rgba(245,158,11,0.4);
            color:white; padding:16px 20px; border-radius:16px;
            box-shadow:0 12px 36px rgba(0,0,0,0.4);
            max-width:360px; font-family:Inter,Arial,sans-serif;
            animation:slideInToast .4s ease;
            border-left:4px solid #f59e0b;
        `;
        toast.innerHTML=`
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="width:42px;height:42px;border-radius:12px;
                     background:rgba(245,158,11,0.15);display:flex;
                     align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-bell" style="color:#f59e0b;font-size:18px;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;margin-bottom:4px;">
                        Solicitud de Permiso
                    </div>
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:10px;">
                        <strong style="color:#fbbf24;">${sol.solicitante}</strong>
                        necesita autorización para:
                        <em>${sol.accion}</em>
                        <span style="color:#64748b;margin-left:6px;">(${hace})</span>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn-gen-sol" data-id="${sol.id}"
                                style="background:#f59e0b;border:none;color:white;
                                       padding:7px 14px;border-radius:9px;font-size:12px;
                                       font-weight:600;cursor:pointer;flex:1;">
                            <i class="fa-solid fa-key me-1"></i>Generar Código
                        </button>
                        <button class="btn-des-sol" data-id="${sol.id}"
                                style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
                                       color:#94a3b8;padding:7px 12px;border-radius:9px;
                                       font-size:12px;cursor:pointer;">
                            Descartar
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Usar addEventListener en lugar de onclick (evita problemas con caracteres especiales)
        toast.querySelector('.btn-gen-sol').addEventListener('click', function(){
            generarCodigoParaSolicitud(sol.id, sol.solicitante, sol.accion);
        });
        toast.querySelector('.btn-des-sol').addEventListener('click', function(){
            descartarSolicitud(sol.id);
        });

        document.body.appendChild(toast);
        tonoSolicitud();

        // Auto-cerrar en 30 segundos
        setTimeout(()=>{
            if(toast.parentNode){
                toast.style.animation='slideOutToast .3s ease forwards';
                setTimeout(()=>toast.remove(),300);
            }
        }, 30000);
    }

    window.generarCodigoParaSolicitud = function(solId, solicitante, accion){
        // Marcar como atendida
        fetch(BASE_URL+'/api/solicitar_permiso.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`accion=atender&id=${solId}`
        });
        // Eliminar toast
        const t=document.getElementById('solToast_'+solId);
        if(t){t.style.animation='slideOutToast .3s ease forwards';setTimeout(()=>t.remove(),300);}
        // Abrir generador de código
        document.getElementById('gen_solicitante').value=solicitante;
        document.getElementById('gen_descripcion').value=accion;
        document.getElementById('codigoGenerado').style.display='none';
        new bootstrap.Modal(document.getElementById('modalGenerarCodigo')).show();
    };

    window.descartarSolicitud = function(solId){
        fetch(BASE_URL+'/api/solicitar_permiso.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`accion=atender&id=${solId}`
        });
        const t=document.getElementById('solToast_'+solId);
        if(t){t.style.animation='slideOutToast .3s ease forwards';setTimeout(()=>t.remove(),300);}
    };

    // Consultar solicitudes pendientes cada 8 segundos
    function consultarSolicitudes(){
        fetch(BASE_URL+'/api/solicitar_permiso.php?accion=pendientes')
        .then(r=>r.json())
        .then(data=>{
            if(data.pendientes && data.pendientes.length>0){
                data.pendientes.forEach(sol => mostrarAlertaSolicitud(sol));
            }
        })
        .catch(()=>{});
    }

    // Iniciar polling cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded',()=>{
        setTimeout(consultarSolicitudes, 2000);
        intervalSolicitudes = setInterval(consultarSolicitudes, 8000);
    });
})();
<?php endif; ?>

// ============================================================
// NOTIFICACIONES DE STOCK CON SONIDO
// Se activan al cargar el dashboard si hay alertas
// ============================================================

// Datos del servidor
const AGOTADOS   = <?= (int)$kpis['agotados'] ?>;
const CRITICOS   = <?= (int)$kpis['stock_crit'] ?>;
const STOCK_BAJO = <?= (int)$kpis['stock_bajo'] ?>;

// Genera un sonido usando Web Audio API (sin archivos externos)
function tonoAlerta(tipo) {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (tipo === 'agotado') {
            // Tono urgente: dos pitidos cortos descendentes
            osc.type     = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);

            // Segundo pitido
            const osc2  = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.connect(gain2); gain2.connect(ctx.destination);
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, ctx.currentTime + 0.4);
            osc2.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.55);
            gain2.gain.setValueAtTime(0.25, ctx.currentTime + 0.4);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.7);
            osc2.start(ctx.currentTime + 0.4);
            osc2.stop(ctx.currentTime + 0.7);

        } else if (tipo === 'critico') {
            // Tono de advertencia: un pitido suave
            osc.type = 'sine';
            osc.frequency.setValueAtTime(660, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(520, ctx.currentTime + 0.25);
            gain.gain.setValueAtTime(0.18, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);

        } else if (tipo === 'bajo') {
            // Tono informativo: pitido suave breve
            osc.type = 'sine';
            osc.frequency.setValueAtTime(520, ctx.currentTime);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        }
    } catch(e) {
        // Web Audio API no disponible, silencioso
    }
}

// Mostrar notificación toast con sonido
function mostrarNotificacion(tipo, mensaje, color, icono) {
    // Crear toast
    const toast = document.createElement('div');
    toast.style.cssText = `
        position:fixed; bottom:24px; right:24px; z-index:99999;
        background:${color}; color:white;
        padding:14px 20px; border-radius:14px;
        box-shadow:0 8px 28px rgba(0,0,0,0.35);
        display:flex; align-items:center; gap:12px;
        font-size:13px; font-weight:600; font-family:Inter,Arial,sans-serif;
        max-width:340px; cursor:pointer;
        animation: slideInToast 0.4s ease;
        border-left: 4px solid rgba(255,255,255,0.4);
    `;
    toast.innerHTML = `
        <i class="fa-solid ${icono}" style="font-size:18px;flex-shrink:0;"></i>
        <div>
            <div style="font-weight:700;margin-bottom:2px;">Alerta de Stock</div>
            <div style="font-weight:400;opacity:.9;font-size:12px;">${mensaje}</div>
        </div>
        <div style="margin-left:auto;opacity:.7;font-size:11px;">clic para cerrar</div>
    `;
    toast.onclick = () => {
        toast.style.animation = 'slideOutToast 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    };

    // Auto-cerrar después de 6 segundos
    setTimeout(() => {
        if(toast.parentNode) {
            toast.style.animation = 'slideOutToast 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);

    document.body.appendChild(toast);

    // Reproducir sonido
    tonoAlerta(tipo);
}

// Animaciones CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInToast {
        from { transform:translateX(120%); opacity:0; }
        to   { transform:translateX(0);   opacity:1; }
    }
    @keyframes slideOutToast {
        from { transform:translateX(0);   opacity:1; }
        to   { transform:translateX(120%); opacity:0; }
    }
`;
document.head.appendChild(style);

// Disparar notificaciones al cargar (con pequeños retrasos para no solapar)
document.addEventListener('DOMContentLoaded', () => {
    // Solo mostrar si el usuario no las ha silenciado hoy
    const hoy = new Date().toDateString();
    const silenciado = localStorage.getItem('alertas_silenciadas');

    if (silenciado === hoy) return; // Ya las vio hoy

    let delay = 800;

    if (AGOTADOS > 0) {
        setTimeout(() => {
            mostrarNotificacion(
                'agotado',
                `${AGOTADOS} material(es) SIN STOCK — requiere reposición urgente`,
                'linear-gradient(135deg,#dc2626,#b91c1c)',
                'fa-ban'
            );
        }, delay);
        delay += 800;
    }

    if (CRITICOS > 0) {
        setTimeout(() => {
            mostrarNotificacion(
                'critico',
                `${CRITICOS} material(es) en stock CRÍTICO (≤5 unidades)`,
                'linear-gradient(135deg,#d97706,#b45309)',
                'fa-triangle-exclamation'
            );
        }, delay);
        delay += 800;
    }

    if (STOCK_BAJO > 0 && (AGOTADOS > 0 || CRITICOS > 0)) {
        setTimeout(() => {
            mostrarNotificacion(
                'bajo',
                `${STOCK_BAJO} material(es) con stock bajo (≤10 unidades)`,
                'linear-gradient(135deg,#0369a1,#0284c7)',
                'fa-box-open'
            );
        }, delay);
    }

    // Botón para silenciar el resto del día
    if (AGOTADOS > 0 || CRITICOS > 0) {
        setTimeout(() => {
            const btnSilenciar = document.createElement('div');
            btnSilenciar.style.cssText = `
                position:fixed; bottom:24px; left:24px; z-index:99999;
                background:rgba(30,41,59,0.95); color:#94a3b8;
                padding:10px 16px; border-radius:12px; cursor:pointer;
                font-size:11px; font-family:Inter,Arial,sans-serif;
                border:1px solid rgba(255,255,255,0.08);
                box-shadow:0 4px 16px rgba(0,0,0,0.3);
                animation: slideInToast 0.4s ease;
                display:flex; align-items:center; gap:8px;
            `;
            btnSilenciar.innerHTML = `<i class="fa-solid fa-bell-slash"></i> Silenciar alertas de hoy`;
            btnSilenciar.onclick = () => {
                localStorage.setItem('alertas_silenciadas', new Date().toDateString());
                btnSilenciar.style.animation = 'slideOutToast 0.3s ease forwards';
                setTimeout(() => btnSilenciar.remove(), 300);
                Swal.fire({
                    title:'Alertas silenciadas',
                    text:'No recibirás más notificaciones de stock por hoy.',
                    icon:'info', timer:2500, showConfirmButton:false
                });
            };
            document.body.appendChild(btnSilenciar);
            setTimeout(() => {
                if(btnSilenciar.parentNode){
                    btnSilenciar.style.animation='slideOutToast 0.3s ease forwards';
                    setTimeout(()=>btnSilenciar.remove(),300);
                }
            }, 8000);
        }, delay + 400);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

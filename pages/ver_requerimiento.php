<?php
// ============================================================
// VER DETALLE DE REQUERIMIENTO — vista imprimible
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . BASE_URL . "/pages/requerimiento.php"); exit(); }

$stmt = $conn->prepare("
    SELECT r.*, COALESCE(t.nombre,'—') AS tecnico_nombre,
           COALESCE(t.dni,'') AS tecnico_dni,
           COALESCE(t.celular,'') AS tecnico_celular,
           COALESCE(t.cargo,'') AS tecnico_cargo,
           COALESCE(t.area,'') AS tecnico_area
    FROM requerimientos r
    LEFT JOIN tecnicos t ON t.id = r.tecnico_id
    WHERE r.id = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$req) { header("Location: " . BASE_URL . "/pages/requerimiento.php"); exit(); }

$detalles = $conn->query("
    SELECT d.cantidad, m.nombre, m.codigo, m.unidad
    FROM detalle_requerimiento d
    JOIN materiales m ON m.id = d.material_id
    WHERE d.requerimiento_id = $id
    ORDER BY m.nombre ASC
");

$est   = $req['estado'] ?? 'Pendiente';
$color = match($est) { 'Aprobado' => '#16a34a', 'Anulado' => '#dc2626', default => '#d97706' };
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REQ #<?= $id ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family:'Tahoma',sans-serif; background:#f8fafc; color:#0f172a; }
        .doc-wrap { max-width:800px; margin:30px auto; background:white;
                    border-radius:16px; box-shadow:0 4px 30px rgba(0,0,0,0.1); overflow:hidden; }
        .doc-header { background:#1e3a5f; color:white; padding:24px 32px; }
        .doc-header h1 { font-size:20px; font-weight:700; margin:0; }
        .doc-header p  { margin:0; opacity:.75; font-size:13px; }
        .doc-body  { padding:32px; }
        .badge-estado { display:inline-block; padding:4px 14px; border-radius:20px;
                        font-size:12px; font-weight:700; color:white; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
        .info-block { background:#f1f5f9; border-radius:12px; padding:16px; }
        .info-block label { font-size:11px; color:#64748b; text-transform:uppercase;
                            letter-spacing:.05em; display:block; margin-bottom:4px; }
        .info-block span  { font-weight:600; font-size:14px; }
        table.req-table { width:100%; border-collapse:collapse; margin-top:8px; }
        table.req-table thead tr { background:#1e3a5f; color:white; }
        table.req-table th { padding:10px 12px; font-size:12px; text-align:left; font-weight:600; }
        table.req-table td { padding:10px 12px; font-size:13px; border-bottom:1px solid #e2e8f0; }
        table.req-table tbody tr:nth-child(even) { background:#f8fafc; }
        table.req-table tfoot td { background:#e9eff7; font-weight:700; }
        .firma-area { margin-top:48px; display:grid; grid-template-columns:1fr 1fr; gap:32px; }
        .firma-box { text-align:center; }
        .firma-line { border-top:2px solid #334155; margin-bottom:8px; padding-top:8px; }
        .firma-box p { font-size:12px; color:#64748b; margin:0; }
        .acciones-barra { background:#f1f5f9; padding:12px 32px;
                          display:flex; gap:10px; justify-content:flex-end; }
        @media print {
            .acciones-barra { display:none; }
            .doc-wrap { box-shadow:none; margin:0; border-radius:0; }
            body { background:white; }
        }
    </style>
</head>
<body>

<div class="doc-wrap">

    <!-- BARRA ACCIONES -->
    <div class="acciones-barra">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-print me-1"></i>Imprimir
        </button>
        <a href="<?= BASE_URL ?>/exports/generar_word.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-file-word me-1"></i>Word
        </a>
        <a href="<?= BASE_URL ?>/exports/generar_pdf.php?id=<?= $id ?>" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-file-pdf me-1"></i>PDF
        </a>
        <a href="<?= BASE_URL ?>/pages/requerimiento.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Volver
        </a>
    </div>

    <!-- ENCABEZADO DOCUMENTO -->
    <div class="doc-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><?= APP_EMPRESA ?></h1>
                <p>Requerimiento de Materiales</p>
            </div>
            <div style="text-align:right;">
                <div style="font-size:22px;font-weight:800;letter-spacing:1px;">
                    <?= htmlspecialchars($req['codigo_req'] ?? 'REQ-'.$id) ?>
                </div>
                <span class="badge-estado" style="background:<?= $color ?>;"><?= $est ?></span>
            </div>
        </div>
    </div>

    <div class="doc-body">

        <!-- INFO GRID -->
        <div class="info-grid">
            <div class="info-block">
                <label>Fecha de solicitud</label>
                <span><?= $req['fecha'] ? date('d/m/Y', strtotime($req['fecha'])) : '—' ?></span>
            </div>
            <div class="info-block">
                <label>Registrado por</label>
                <span><?= htmlspecialchars($req['aprobado_por'] ?? '—') ?></span>
            </div>
            <?php if ($req['tecnico_nombre'] !== '—'): ?>
            <div class="info-block">
                <label>Técnico responsable</label>
                <span><?= htmlspecialchars($req['tecnico_nombre']) ?></span>
                <?php if ($req['tecnico_cargo']): ?>
                <br><small style="color:#64748b;"><?= htmlspecialchars($req['tecnico_cargo']) ?></small>
                <?php endif; ?>
            </div>
            <div class="info-block">
                <label>Contacto técnico</label>
                <span><?= htmlspecialchars($req['tecnico_celular'] ?: '—') ?></span>
                <?php if ($req['tecnico_area']): ?>
                <br><small style="color:#64748b;">Área: <?= htmlspecialchars($req['tecnico_area']) ?></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($req['observacion'])): ?>
            <div class="info-block" style="grid-column:span 2;">
                <label>Observación</label>
                <span><?= htmlspecialchars($req['observacion']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- TABLA DE MATERIALES -->
        <h6 style="font-weight:700;margin-bottom:12px;color:#1e3a5f;">
            <i class="fa-solid fa-boxes-stacked me-2"></i>Relación de Materiales
        </h6>
        <table class="req-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Descripción del Material</th>
                    <th>Código</th>
                    <th>Unidad</th>
                    <th style="text-align:right;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
            <?php $n=1; $total=0; while ($d = $detalles->fetch_assoc()): $total+=$d['cantidad']; ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><strong><?= htmlspecialchars($d['nombre']) ?></strong></td>
                <td><code><?= htmlspecialchars($d['codigo'] ?? '—') ?></code></td>
                <td><?= htmlspecialchars($d['unidad']) ?></td>
                <td style="text-align:right;font-weight:700;"><?= $d['cantidad'] ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;">Total ítems: <?= $n-1 ?></td>
                    <td style="text-align:right;"><?= $total ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- FIRMAS -->
        <div class="firma-area">
            <div class="firma-box">
                <div style="height:50px;"></div>
                <div class="firma-line"></div>
                <p><strong>ALMACENERO</strong></p>
                <p><?= htmlspecialchars($req['aprobado_por'] ?? '') ?></p>
            </div>
            <div class="firma-box">
                <div style="height:50px;"></div>
                <div class="firma-line"></div>
                <p><strong>TÉCNICO RESPONSABLE</strong></p>
                <p><?= htmlspecialchars($req['tecnico_nombre']) ?></p>
            </div>
        </div>

        <p style="text-align:center;font-size:11px;color:#94a3b8;margin-top:24px;">
            Generado: <?= date('d/m/Y H:i') ?> — <?= APP_NAME ?> v<?= APP_VERSION ?>
        </p>
    </div>
</div>
</body>
</html>

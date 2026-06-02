<?php
// ============================================================
// API — Genera documento desde plantilla + variables dinámicas
// POST: plantilla_id, formato (word|pdf), contexto (JSON)
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/variables.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Solo autenticados
if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    http_response_code(401);
    die('No autenticado.');
}

$plantilla_id = intval($_POST['plantilla_id'] ?? 0);
$formato      = $_POST['formato'] ?? 'word';  // word | pdf
$contextoRaw  = $_POST['contexto'] ?? '{}';
$contexto     = json_decode($contextoRaw, true) ?? [];

if ($plantilla_id <= 0) {
    ob_end_clean(); die('ID de plantilla inválido.');
}

// Cargar plantilla de BD
$stmt = $conn->prepare("SELECT * FROM plantillas WHERE id=? AND activo=1 LIMIT 1");
$stmt->bind_param('i', $plantilla_id);
$stmt->execute();
$plantilla = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plantilla) {
    ob_end_clean(); die('Plantilla no encontrada o inactiva.');
}

$archivoOriginal = __DIR__ . '/../uploads/plantillas/' . $plantilla['tipo'] . '/' . $plantilla['archivo'];
if (!file_exists($archivoOriginal)) {
    ob_end_clean(); die('Archivo de plantilla no encontrado en el servidor.');
}

// Obtener variables reales
$vars = obtenerVariables($conn, $contexto);
audit_log($conn, 'GENERAR_PLANTILLA', "Plantilla ID $plantilla_id: {$plantilla['nombre']}");

// ============================================================
// GENERAR WORD
// ============================================================
if ($plantilla['tipo'] === 'word') {
    $processor = new \PhpOffice\PhpWord\TemplateProcessor($archivoOriginal);

    // Reemplazar variables simples
    foreach ($vars as $key => $value) {
        if (str_starts_with($key, '__')) continue; // internos
        $placeholder = ltrim(rtrim($key, '}}'), '{{');
        try {
            $processor->setValue($placeholder, htmlspecialchars((string)$value));
        } catch (\Exception $e) { /* variable no encontrada en plantilla, ignorar */ }
    }

    // Clonar filas de tabla si hay materiales ({{mat_nombre#}}, etc.)
    $materiales = $vars['__materiales__'] ?? [];
    if (!empty($materiales)) {
        try {
            $processor->cloneRow('mat_nombre', count($materiales));
            foreach ($materiales as $i => $m) {
                $n = $i + 1;
                $processor->setValue("mat_nombre#$n",   htmlspecialchars($m['nombre']));
                $processor->setValue("mat_codigo#$n",   htmlspecialchars($m['codigo'] ?? ''));
                $processor->setValue("mat_unidad#$n",   htmlspecialchars($m['unidad']));
                $processor->setValue("mat_cantidad#$n", $m['cantidad']);
                $processor->setValue("item_num#$n",     $n);
            }
        } catch (\Exception $e) { /* la plantilla no tiene tabla clonada */ }
    }

    // Generar archivo temporal
    $tmpFile = tempnam(sys_get_temp_dir(), 'ptpl_') . '.docx';
    $processor->saveAs($tmpFile);

    if ($formato === 'pdf') {
        // Convertir Word a PDF con DomPDF (via HTML intermedio)
        generarPdfDesdeWord($tmpFile, $plantilla['nombre'], $vars, $materiales);
        unlink($tmpFile);
    } else {
        $filename = slugify($plantilla['nombre']) . '_' . date('Ymd_His') . '.docx';
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: max-age=0');
        readfile($tmpFile);
        unlink($tmpFile);
    }
    exit();
}

// ============================================================
// GENERAR EXCEL
// ============================================================
if ($plantilla['tipo'] === 'excel') {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivoOriginal);

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $val = (string)$cell->getValue();
                if (strpos($val, '{{') !== false) {
                    foreach ($vars as $key => $value) {
                        if (str_starts_with($key, '__')) continue;
                        $val = str_replace($key, (string)$value, $val);
                    }
                    $cell->setValue($val);
                }
            }
        }
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'pxls_') . '.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpFile);

    $filename = slugify($plantilla['nombre']) . '_' . date('Ymd_His') . '.xlsx';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit();
}

// ============================================================
// GENERAR PDF (desde HTML)
// ============================================================
if ($plantilla['tipo'] === 'pdf') {
    $htmlTemplate = file_get_contents($archivoOriginal);
    foreach ($vars as $key => $value) {
        if (str_starts_with($key, '__')) continue;
        $htmlTemplate = str_replace($key, htmlspecialchars((string)$value), $htmlTemplate);
    }
    generarPdfDesdeHtml($htmlTemplate, $plantilla['nombre']);
    exit();
}

// ── Helpers ──────────────────────────────────────────────────

function generarPdfDesdeWord(string $tmpDocx, string $nombre, array $vars, array $mats): void {
    // Genera PDF via DomPDF con HTML intermedio (sin LibreOffice)
    $html = construirHtmlRequerimiento($vars, $mats);
    generarPdfDesdeHtml($html, $nombre);
}

function generarPdfDesdeHtml(string $html, string $nombre): void {
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Arial');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml('<meta charset="UTF-8">' . $html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = slugify($nombre) . '_' . date('Ymd_His') . '.pdf';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $dompdf->output();
}

function construirHtmlRequerimiento(array $vars, array $mats): string {
    $empresa  = $vars['{{empresa}}']   ?? APP_EMPRESA;
    $codigo   = $vars['{{req_codigo}}'] ?? '';
    $fecha    = $vars['{{req_fecha_larga}}'] ?? $vars['{{fecha_larga}}'] ?? '';
    $tipo     = $vars['{{req_tipo}}']   ?? '';
    $usuario  = $vars['{{usuario_nombre}}'] ?? '';

    $filasMats = '';
    foreach ($mats as $i => $m) {
        $bg = $i % 2 === 0 ? '#f8fafc' : '#ffffff';
        $filasMats .= "<tr style='background:$bg;'>
            <td style='padding:8px;border:1px solid #e2e8f0;text-align:center;'>" . ($i+1) . "</td>
            <td style='padding:8px;border:1px solid #e2e8f0;font-weight:600;'>" . htmlspecialchars($m['nombre']) . "</td>
            <td style='padding:8px;border:1px solid #e2e8f0;'>" . htmlspecialchars($m['codigo'] ?? '') . "</td>
            <td style='padding:8px;border:1px solid #e2e8f0;text-align:center;'>" . htmlspecialchars($m['unidad']) . "</td>
            <td style='padding:8px;border:1px solid #e2e8f0;text-align:center;font-weight:700;'>" . $m['cantidad'] . "</td>
        </tr>";
    }

    return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <style>
        body{font-family:Arial,sans-serif;font-size:11pt;color:#1a1a1a;margin:0;padding:20px;}
        .header{background:#1e3a5f;color:white;padding:16px 24px;border-radius:0;}
        .header h1{margin:0;font-size:16pt;} .header p{margin:4px 0 0;font-size:9pt;opacity:.8;}
        .meta{display:flex;gap:24px;margin:20px 0;} .meta-item{flex:1;background:#f8fafc;padding:12px;border-left:3px solid #2563eb;}
        .meta-item label{font-size:8pt;color:#64748b;text-transform:uppercase;} .meta-item strong{display:block;font-size:11pt;}
        table{width:100%;border-collapse:collapse;margin-top:16px;}
        thead tr{background:#1e3a5f;color:white;}
        thead th{padding:10px 8px;text-align:left;font-size:9pt;}
        .footer{margin-top:32px;border-top:1px solid #e2e8f0;padding-top:16px;font-size:8pt;color:#94a3b8;text-align:center;}
    </style></head><body>
    <div class='header'><h1>$empresa</h1><p>Requerimiento de Materiales &mdash; $tipo</p></div>
    <div class='meta'>
        <div class='meta-item'><label>Código</label><strong>$codigo</strong></div>
        <div class='meta-item'><label>Fecha</label><strong>$fecha</strong></div>
        <div class='meta-item'><label>Registrado por</label><strong>$usuario</strong></div>
    </div>
    <table><thead><tr><th>#</th><th>Material</th><th>Código</th><th>Unidad</th><th>Cantidad</th></tr></thead>
    <tbody>$filasMats</tbody></table>
    <div class='footer'>Generado el " . date('d/m/Y H:i') . " &mdash; " . APP_NAME . " v" . APP_VERSION . "</div>
    </body></html>";
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}

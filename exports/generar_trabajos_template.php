<?php
// ============================================================
// GENERAR TRABAJOS EJECUTADOS — usa la plantilla oficial exacta
// Preserva formato, colores, columnas y estructura original
// GET ?mes=YYYY-MM
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mapeo_materiales.php';
require_once __DIR__ . '/../config/app.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

[$yy, $mm] = explode('-', $mes);
$meses_es = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL',
             '05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO',
             '09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
$mesLabel = $meses_es[$mm] . ' ' . $yy;

// ── Ruta de la plantilla ─────────────────────────────────────
$templatePath = __DIR__ . '/../uploads/plantillas/excel/trabajos_ejecutados_template.xlsx';
if (!file_exists($templatePath)) {
    while (ob_get_level()) ob_end_clean();
    die('Plantilla no encontrada. Sube el archivo trabajos_ejecutados_template.xlsx en Plantillas → Excel.');
}

// ── Cargar OTs del mes ────────────────────────────────────────
$stmtOTs = $conn->prepare("
    SELECT ot.id, ot.numero_ot, ot.tipo, ot.estado, ot.serie_medidor,
           ot.fecha, ot.observacion,
           t.nombre AS tecnico
    FROM ordenes_trabajo ot
    JOIN tecnicos t ON t.id = ot.tecnico_id
    WHERE DATE_FORMAT(ot.fecha, '%Y-%m') = ?
    ORDER BY t.nombre ASC, ot.fecha ASC, ot.numero_ot ASC
");
$stmtOTs->bind_param('s', $mes);
$stmtOTs->execute();
$ots = $stmtOTs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOTs->close();

if (empty($ots)) {
    while (ob_get_level()) ob_end_clean();
    die("No hay Órdenes de Trabajo registradas para $mesLabel.");
}

// ── Cargar detalle de materiales de todas las OTs ────────────
$ids = implode(',', array_column($ots, 'id'));
$detMap = [];
$det = $conn->query("
    SELECT d.ot_id, d.material_id, d.cantidad, m.nombre AS mat_nombre,
           COALESCE(m.codigo_electrosur, m.codigo) AS mat_codigo
    FROM detalle_ot d
    JOIN materiales m ON m.id = d.material_id
    WHERE d.ot_id IN ($ids)
");
while ($row = $det->fetch_assoc()) {
    $detMap[$row['ot_id']][] = $row;
}

// ── Cargar la plantilla real ──────────────────────────────────
$spreadsheet = IOFactory::load($templatePath);

// ── Helper: limpiar datos de una hoja (preservar solo header) ─
function limpiarHoja($sheet): void {
    $maxRow = $sheet->getHighestRow();
    if ($maxRow > 1) {
        $sheet->removeRow(2, $maxRow - 1);
    }
}

// ── Helper: aplicar estilo de fila alternada ─────────────────
function estiloFila($sheet, int $fila, bool $par, string $estado): void {
    $maxCol = 29; // hasta col AE
    $bg = $par ? 'F0F4FF' : 'FFFFFF';

    // Color por estado
    $badgeBg = match($estado) {
        'Aprobado'   => 'D1FAE5',
        'Ejecutado'  => 'DBEAFE',
        'Programado' => 'FEF9C3',
        default      => 'F3F4F6',
    };

    for ($c = 1; $c <= $maxCol; $c++) {
        $cell = $sheet->getCellByColumnAndRow($c, $fila);
        $fillBg = ($c === 2) ? $badgeBg : $bg; // col estado con color
        $cell->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF' . $fillBg);

        $cell->getStyle()->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FFCBD5E1');

        $cell->getStyle()->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
}

// ── Helper: llenar una fila de OT en la hoja ─────────────────
function llenarFilaOT($sheet, int $fila, array $ot, array $mats, bool $par): void {
    $sheet->getCellByColumnAndRow(COL_PERSONAL + 1, $fila)->setValue($ot['tecnico']);
    $sheet->getCellByColumnAndRow(COL_ESTADO   + 1, $fila)->setValue(strtoupper($ot['estado']));
    $sheet->getCellByColumnAndRow(COL_TIPO_OT  + 1, $fila)->setValue($ot['tipo']);
    $sheet->getCellByColumnAndRow(COL_OT_BLANK + 1, $fila)->setValue($ot['numero_ot']);

    // Serie del medidor
    if ($ot['serie_medidor']) {
        $sheet->getCellByColumnAndRow(COL_SERIE + 1, $fila)->setValue($ot['serie_medidor']);
    }

    // Fecha
    $sheet->getCellByColumnAndRow(COL_FECHA + 1, $fila)
          ->setValue($ot['fecha'] ? date('d/m/Y', strtotime($ot['fecha'])) : '');

    // Observación
    if ($ot['observacion']) {
        $sheet->getCellByColumnAndRow(COL_OBSERVACIONES + 1, $fila)->setValue($ot['observacion']);
    }

    // Materiales
    foreach ($mats as $m) {
        $col = buscarColumna($m['mat_nombre']);
        if ($col !== null) {
            $sheet->getCellByColumnAndRow($col + 1, $fila)->setValue($m['cantidad']);
        }
    }

    estiloFila($sheet, $fila, $par, $ot['estado']);
}

// ── Actualizar título del mes en la hoja ──────────────────────
function actualizarTitulo($sheet, string $mesLabel): void {
    // Buscar celda con el título en las primeras 3 filas
    foreach (range(1, 3) as $r) {
        foreach (range(1, 10) as $c) {
            $v = (string)$sheet->getCellByColumnAndRow($c, $r)->getValue();
            if (str_contains(strtoupper($v), 'MES DE') || str_contains(strtoupper($v), 'ABRIL')) {
                $sheet->getCellByColumnAndRow($c, $r)
                      ->setValue(str_ireplace(
                          ['ABRIL 2026','MAYO 2026','ENERO 2026','FEBRERO 2026',
                           'MARZO 2026','JUNIO 2026','JULIO 2026'],
                          'MES DE ' . $mesLabel,
                          $v
                      ));
                return;
            }
        }
    }
}

// ════════════════════════════════════════════════════════════
// PROCESAR CADA HOJA
// ════════════════════════════════════════════════════════════

// Agrupar OTs por estado y por técnico
$porEstado  = ['Aprobado'=>[],'Ejecutado'=>[],'Programado'=>[]];
$porTecnico = [];

foreach ($ots as $ot) {
    $porEstado[$ot['estado']][]        = $ot;
    $porTecnico[$ot['tecnico']][]      = $ot;
}

// ── Hoja APROBADOS ──────────────────────────────────────────
$procesarHoja = function(string $nombreHoja, array $listaOTs) use ($spreadsheet, $detMap, $mesLabel) {
    $sheet = null;

    // Buscar hoja existente (nombre exacto o aproximado)
    foreach ($spreadsheet->getAllSheets() as $sh) {
        if (strtoupper(trim($sh->getTitle())) === strtoupper(trim($nombreHoja))) {
            $sheet = $sh;
            break;
        }
    }

    // Si no existe, crear nueva copiando el estilo de APROBADOS
    if (!$sheet) {
        $base  = $spreadsheet->getSheetByName('APROBADOS') ?? $spreadsheet->getActiveSheet();
        $sheet = clone $base;
        $sheet->setTitle(mb_substr($nombreHoja, 0, 31));
        $spreadsheet->addSheet($sheet);
    }

    limpiarHoja($sheet);
    actualizarTitulo($sheet, $mesLabel);

    $fila = 2;
    foreach ($listaOTs as $k => $ot) {
        $mats = $detMap[$ot['id']] ?? [];
        llenarFilaOT($sheet, $fila, $ot, $mats, $k % 2 === 0);
        $fila++;
    }

    // Agregar fila de totales si hay datos
    if ($fila > 2) {
        agregarTotales($sheet, $fila, 2, $fila - 1);
    }
};

// Hojas consolidadas por estado
$procesarHoja('APROBADOS', $porEstado['Aprobado']);
$procesarHoja('EJECUTADOS', $porEstado['Ejecutado']);
$procesarHoja('enviados', $porEstado['Programado']);

// Hojas por técnico
foreach ($porTecnico as $tecnico => $otsTec) {
    // Normalizar nombre para comparar con hoja
    $nombreHoja = mb_substr(strtoupper($tecnico), 0, 31);

    // Buscar hoja existente del técnico
    $found = false;
    foreach ($spreadsheet->getAllSheets() as $sh) {
        $shTitle = strtoupper(trim($sh->getTitle()));
        // Busca coincidencia por primer nombre del técnico
        $primerNombre = strtoupper(explode(' ', trim($tecnico))[0]);
        if (str_contains($shTitle, $primerNombre) || str_contains($primerNombre, $shTitle)) {
            limpiarHoja($sh);
            actualizarTitulo($sh, $mesLabel);
            $fila = 2;
            foreach ($otsTec as $k => $ot) {
                $mats = $detMap[$ot['id']] ?? [];
                llenarFilaOT($sh, $fila, $ot, $mats, $k % 2 === 0);
                $fila++;
            }
            if ($fila > 2) agregarTotales($sh, $fila, 2, $fila - 1);
            $found = true;
            break;
        }
    }

    // Si no encontró hoja del técnico, crear nueva
    if (!$found) {
        $procesarHoja($primerNombre ?? $nombreHoja, $otsTec);
    }
}

// ── Función totales por columna ──────────────────────────────
function agregarTotales($sheet, int $filaTotal, int $filaInicio, int $filaFin): void {
    $sheet->getCellByColumnAndRow(1, $filaTotal)->setValue('TOTAL');
    $sheet->getStyleByColumnAndRow(1, $filaTotal)->getFont()->setBold(true);

    // Sumar columnas de materiales (cols 6 a 26 = índice 5 a 25)
    for ($c = 6; $c <= 27; $c++) {
        $col   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $sheet->getCellByColumnAndRow($c, $filaTotal)
              ->setValue("=SUM({$col}{$filaInicio}:{$col}{$filaFin})");
        $sheet->getStyleByColumnAndRow($c, $filaTotal)->getFont()->setBold(true);
    }

    // Estilo fila total
    for ($c = 1; $c <= 30; $c++) {
        $sheet->getStyleByColumnAndRow($c, $filaTotal)
              ->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FF1E3A5F');
        $sheet->getStyleByColumnAndRow($c, $filaTotal)
              ->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}

// ── Activar primera hoja y enviar ────────────────────────────
$spreadsheet->setActiveSheetIndex(0);

$tmpFile  = tempnam(sys_get_temp_dir(), 'trab_') . '.xlsx';
(new Xlsx($spreadsheet))->save($tmpFile);

audit_log($conn, 'EXPORT_TRABAJOS_TEMPLATE', "Trabajos ejecutados $mes desde plantilla");

$filename = 'Trabajos_Ejecutados_' . $mesLabel . '.xlsx';
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit();

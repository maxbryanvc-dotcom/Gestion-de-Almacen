<?php
// ============================================================
// REPORTE: TRABAJOS EJECUTADOS — formato igual al Excel original
// GET ?mes=YYYY-MM
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$mesLabel = date('F Y', strtotime($mes . '-01'));
$mesLabel_es = strtr(strtoupper($mesLabel), [
    'JANUARY'=>'ENERO','FEBRUARY'=>'FEBRERO','MARCH'=>'MARZO',
    'APRIL'=>'ABRIL','MAY'=>'MAYO','JUNE'=>'JUNIO',
    'JULY'=>'JULIO','AUGUST'=>'AGOSTO','SEPTEMBER'=>'SEPTIEMBRE',
    'OCTOBER'=>'OCTUBRE','NOVEMBER'=>'NOVIEMBRE','DECEMBER'=>'DICIEMBRE',
]);

// ── Obtener todos los materiales que aparecen en el mes ──────
$mats_stmt = $conn->prepare("
    SELECT DISTINCT m.id, m.nombre, m.codigo_electrosur, m.unidad
    FROM detalle_ot d
    JOIN materiales m ON m.id = d.material_id
    JOIN ordenes_trabajo ot ON ot.id = d.ot_id
    WHERE DATE_FORMAT(ot.fecha,'%Y-%m') = ?
    ORDER BY m.nombre ASC
");
$mats_stmt->bind_param('s', $mes);
$mats_stmt->execute();
$materiales_mes = $mats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$mats_stmt->close();

// ── Obtener técnicos con OTs en el mes ──────────────────────
$tec_stmt = $conn->prepare("
    SELECT DISTINCT t.id, t.nombre
    FROM ordenes_trabajo ot
    JOIN tecnicos t ON t.id = ot.tecnico_id
    WHERE DATE_FORMAT(ot.fecha,'%Y-%m') = ?
    ORDER BY t.nombre ASC
");
$tec_stmt->bind_param('s', $mes);
$tec_stmt->execute();
$tecnicos_mes = $tec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tec_stmt->close();

// ── Colores corporativos ─────────────────────────────────────
$C_HEADER_BG  = '1E3A5F';
$C_HEADER_FG  = 'FFFFFF';
$C_SUB_BG     = '2563EB';
$C_TH_BG      = '334155';
$C_ROW_ALT    = 'EBF0FA';
$C_TOTAL_BG   = 'D6E4F0';

$tipos_col    = ['IN'=>'primary','CM'=>'info','MJ'=>'success','REUB'=>'warning','REAC'=>'secondary'];

// ── Helpers de estilo ─────────────────────────────────────────
function styleHeader($bg, $fg='FFFFFF', $bold=true, $size=11, $center=true): array {
    return [
        'font'      => ['bold'=>$bold,'color'=>['rgb'=>$fg],'size'=>$size,'name'=>'Arial'],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment' => ['horizontal'=>$center?Alignment::HORIZONTAL_CENTER:Alignment::HORIZONTAL_LEFT,
                        'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    ];
}

function borderThin($color='CCCCCC'): array {
    $b = ['style'=>Border::BORDER_THIN,'color'=>['rgb'=>$color]];
    return ['borders'=>['allBorders'=>$b]];
}

// ════════════════════════════════════════════════════════════
// HOJA 1: RESUMEN GENERAL (todas las OTs)
// ════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$ws = $spreadsheet->getActiveSheet()->setTitle('RESUMEN');

// Columnas fijas
$cols_fijas = ['PERSONAL','ESTADO','OT','TIPO'];
$cols_mats  = array_column($materiales_mes, 'nombre');
$cols_extra = ['SERIE MEDIDOR','FECHA','OBSERVACIÓN'];
$todas_cols = array_merge($cols_fijas, $cols_mats, $cols_extra);

$nCols = count($todas_cols);
$letters = range('A','Z');
// Para más de 26 columnas, generamos AA, AB...
function colLetter(int $n): string {
    $n--; // 0-based
    if ($n < 26) return chr(65+$n);
    return chr(65 + intdiv($n,26) - 1) . chr(65 + ($n % 26));
}

// Fila 1: título
$ws->mergeCells('A1:' . colLetter($nCols) . '1');
$ws->setCellValue('A1', APP_EMPRESA . ' — TRABAJOS EJECUTADOS ' . $mesLabel_es);
$ws->getStyle('A1:' . colLetter($nCols) . '1')->applyFromArray(styleHeader($C_HEADER_BG,'FFFFFF',true,13));
$ws->getRowDimension(1)->setRowHeight(28);

// Fila 2: cabeceras
for ($i=0; $i<$nCols; $i++) {
    $cell = colLetter($i+1).'2';
    $ws->setCellValue($cell, $todas_cols[$i]);
    $ws->getStyle($cell)->applyFromArray(styleHeader($C_TH_BG));
}
$ws->getRowDimension(2)->setRowHeight(30);

// Datos
$ot_stmt = $conn->prepare("
    SELECT ot.*, t.nombre AS tecnico, t.cargo
    FROM ordenes_trabajo ot
    JOIN tecnicos t ON t.id = ot.tecnico_id
    WHERE DATE_FORMAT(ot.fecha,'%Y-%m') = ?
    ORDER BY t.nombre ASC, ot.fecha ASC, ot.numero_ot ASC
");
$ot_stmt->bind_param('s', $mes);
$ot_stmt->execute();
$ots = $ot_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ot_stmt->close();

// Cargar detalle de todas las OTs de una vez
$ids = array_column($ots, 'id');
$detalle_map = [];
if (!empty($ids)) {
    $placeholders = implode(',', $ids);
    $det = $conn->query("
        SELECT d.ot_id, d.material_id, d.cantidad
        FROM detalle_ot d
        WHERE d.ot_id IN ($placeholders)
    ");
    while ($row = $det->fetch_assoc()) {
        $detalle_map[$row['ot_id']][$row['material_id']] = $row['cantidad'];
    }
}

$matIds = array_column($materiales_mes, 'id');

$fila = 3;
foreach ($ots as $k => $ot) {
    $bg = ($k % 2 === 0) ? 'FFFFFF' : $C_ROW_ALT;

    $ws->setCellValue(colLetter(1).$fila, $ot['tecnico']);
    $ws->setCellValue(colLetter(2).$fila, $ot['estado']);
    $ws->setCellValue(colLetter(3).$fila, $ot['numero_ot']);
    $ws->setCellValue(colLetter(4).$fila, $ot['tipo']);

    foreach ($matIds as $mi => $matId) {
        $cant = $detalle_map[$ot['id']][$matId] ?? null;
        if ($cant !== null) {
            $ws->setCellValue(colLetter($mi+5).$fila, $cant);
        }
    }

    $extraBase = count($matIds) + 5;
    $ws->setCellValue(colLetter($extraBase).$fila,   $ot['serie_medidor']  ?? '');
    $ws->setCellValue(colLetter($extraBase+1).$fila, $ot['fecha']);
    $ws->setCellValue(colLetter($extraBase+2).$fila, $ot['observacion']    ?? '');

    $rangoFila = 'A'.$fila.':'.colLetter($nCols).$fila;
    $ws->getStyle($rangoFila)->applyFromArray([
        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
    ]);
    $ws->getStyle($rangoFila)->applyFromArray(borderThin());
    $fila++;
}

// Ancho de columnas
$ws->getColumnDimension('A')->setWidth(14);
$ws->getColumnDimension('B')->setWidth(12);
$ws->getColumnDimension('C')->setWidth(10);
$ws->getColumnDimension('D')->setWidth(6);
for ($i=5; $i<=$nCols-3; $i++) $ws->getColumnDimension(colLetter($i))->setWidth(9);
$ws->getColumnDimension(colLetter($nCols-2))->setWidth(14);
$ws->getColumnDimension(colLetter($nCols-1))->setWidth(11);
$ws->getColumnDimension(colLetter($nCols))->setWidth(18);
$ws->freezePane('E3');

// ════════════════════════════════════════════════════════════
// HOJAS POR TÉCNICO
// ════════════════════════════════════════════════════════════
foreach ($tecnicos_mes as $tec) {
    $sheetName = mb_substr(preg_replace('/[^\w\s\-]/', '', $tec['nombre']), 0, 31);
    $ws2 = $spreadsheet->createSheet()->setTitle($sheetName);

    // Filtrar OTs de este técnico
    $ots_tec = array_filter($ots, fn($o)=>$o['tecnico_id']==$tec['id']);

    // Título
    $ws2->mergeCells('A1:'.colLetter($nCols).'1');
    $ws2->setCellValue('A1', strtoupper($tec['nombre']).' — TRABAJOS '.$mesLabel_es);
    $ws2->getStyle('A1:'.colLetter($nCols).'1')->applyFromArray(styleHeader($C_SUB_BG,'FFFFFF',true,12));
    $ws2->getRowDimension(1)->setRowHeight(26);

    // Cabeceras
    for ($i=0; $i<$nCols; $i++) {
        $cell = colLetter($i+1).'2';
        $ws2->setCellValue($cell, $todas_cols[$i]);
        $ws2->getStyle($cell)->applyFromArray(styleHeader($C_TH_BG));
    }
    $ws2->getRowDimension(2)->setRowHeight(28);

    $f = 3;
    foreach ($ots_tec as $k => $ot) {
        $bg = ($k % 2 === 0) ? 'FFFFFF' : $C_ROW_ALT;
        $ws2->setCellValue('A'.$f, $ot['tecnico']);
        $ws2->setCellValue('B'.$f, $ot['estado']);
        $ws2->setCellValue('C'.$f, $ot['numero_ot']);
        $ws2->setCellValue('D'.$f, $ot['tipo']);

        foreach ($matIds as $mi => $matId) {
            $cant = $detalle_map[$ot['id']][$matId] ?? null;
            if ($cant !== null) $ws2->setCellValue(colLetter($mi+5).$f, $cant);
        }

        $eb = count($matIds)+5;
        $ws2->setCellValue(colLetter($eb).$f,    $ot['serie_medidor']  ?? '');
        $ws2->setCellValue(colLetter($eb+1).$f,  $ot['fecha']);
        $ws2->setCellValue(colLetter($eb+2).$f,  $ot['observacion']    ?? '');

        $rng = 'A'.$f.':'.colLetter($nCols).$f;
        $ws2->getStyle($rng)->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]]]);
        $ws2->getStyle($rng)->applyFromArray(borderThin());
        $f++;
    }

    // Totales por material
    if ($f > 3) {
        $ws2->setCellValue('A'.$f, 'TOTAL');
        $ws2->getStyle('A'.$f)->getFont()->setBold(true);
        for ($mi=0; $mi<count($matIds); $mi++) {
            $col = colLetter($mi+5);
            if ($f > 3) {
                $ws2->setCellValue($col.$f, '=SUM('.$col.'3:'.$col.($f-1).')');
            }
        }
        $ws2->getStyle('A'.$f.':'.colLetter($nCols).$f)
            ->applyFromArray(styleHeader($C_TOTAL_BG,'1F3864',true,10));
    }

    // Anchos
    $ws2->getColumnDimension('A')->setWidth(14);
    $ws2->getColumnDimension('B')->setWidth(12);
    $ws2->getColumnDimension('C')->setWidth(10);
    $ws2->getColumnDimension('D')->setWidth(6);
    for ($i=5; $i<=$nCols-3; $i++) $ws2->getColumnDimension(colLetter($i))->setWidth(8);
    $ws2->freezePane('E3');
}

$spreadsheet->setActiveSheetIndex(0);

// ── Enviar ─────────────────────────────────────────────────────
$filename = 'Trabajos_Ejecutados_' . str_replace('-','_',$mes) . '.xlsx';
audit_log($conn, 'EXPORT_TRABAJOS', "Trabajos ejecutados $mes");

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit();

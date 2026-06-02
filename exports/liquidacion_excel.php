<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
// ============================================================
// EXPORTAR LIQUIDACIÓN MENSUAL — Instalaciones o Mantenimiento
// Formato idéntico al Excel original de ElectroSur Este
// GET ?mes=YYYY-MM&tipo=Instalaciones|Mantenimiento
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$mes      = $_GET['mes']  ?? date('Y-m');
$tipo_liq = ($_GET['tipo'] ?? 'Instalaciones') === 'Mantenimiento' ? 'Mantenimiento' : 'Instalaciones';
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

[$yy,$mm] = explode('-', $mes);
$meses_es = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL',
             '05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO',
             '09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
$mesLabel = 'MES DE ' . $meses_es[$mm] . ' ' . $yy;
$contrato = 'N° 051 - 2025';

// ── Tipos de OT según liquidación ────────────────────────────
// INSTALACIONES: APROBADOS → IN,CM,MJ,REAC,REUB  | INFORMADOS → IN,MEJ,REAC,REUB | ENVIADOS → IN,REAC,REUB
// MANTENIMIENTO: APROBADOS → CM,MEJ,REAC          | INFORMADOS → CM,MEJ,REUB      | ENVIADOS → CM,MEJ,REUB
if ($tipo_liq === 'Instalaciones') {
    $tit_pedido  = 'PEDIDO DE ALMACEN INSTALACIONES NUEVAS';
    $tipos_aprob = ['IN','CM','MJ','REAC','REUB'];
    $tipos_inf   = ['IN','MEJ','REAC','REUB'];
    $tipos_env   = ['IN','REAC','REUB'];
    $sheetName   = 'LIQ INSTALACIONES';
} else {
    $tit_pedido  = 'PEDIDO DE ALMACEN MANTENIMIENTO Y REPOSICION';
    $tipos_aprob = ['CM','MEJ','REAC'];
    $tipos_inf   = ['CM','MEJ','REUB'];
    $tipos_env   = ['CM','MEJ','REUB'];
    $sheetName   = 'PRELIQUIDACION';
}

// ── HELPERS ───────────────────────────────────────────────────
function cl(int $n): string {
    $n--;
    if ($n < 26) return chr(65+$n);
    return chr(65 + intdiv($n,26) - 1) . chr(65 + ($n % 26));
}

function applyStyle($ws, string $range, array $style): void {
    $ws->getStyle($range)->applyFromArray($style);
}

function hdrStyle(string $bg, string $fg='FFFFFF', bool $bold=true, bool $center=true): array {
    return [
        'font'      => ['bold'=>$bold,'color'=>['rgb'=>$fg],'name'=>'Arial','size'=>9],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment' => [
            'horizontal' => $center ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'borders'   => ['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'C0C8D4']]],
    ];
}

function dataStyle(string $bg='FFFFFF'): array {
    return [
        'font'      => ['name'=>'Arial','size'=>9],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E4']]],
    ];
}

// ── REQUERIMIENTOS del mes ────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, codigo_req, fecha FROM requerimientos
     WHERE DATE_FORMAT(fecha,'%Y-%m')=? AND tipo_liq=?
     ORDER BY fecha ASC LIMIT 3"
);
$stmt->bind_param('ss', $mes, $tipo_liq);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cantidades de pedidos por material
$pedido_cantidades = [];
foreach ($pedidos as $p) {
    $s = $conn->prepare(
        "SELECT material_id,cantidad FROM detalle_requerimiento WHERE requerimiento_id=?"
    );
    $s->bind_param('i',$p['id']);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $pedido_cantidades[$p['id']][$r['material_id']] = $r['cantidad'];
    }
    $s->close();
}

// ── MATERIALES activos ────────────────────────────────────────
$materiales = $conn->query(
    "SELECT id, nombre, codigo_electrosur, unidad FROM materiales WHERE activo=1 ORDER BY nombre ASC"
)->fetch_all(MYSQLI_ASSOC);

if (empty($materiales)) { die('Sin materiales registrados.'); }
$mat_ids = array_column($materiales, 'id');

// ── USO EN OTs ────────────────────────────────────────────────
$uso_por_tipo = [];
$placeholders = implode(',', $mat_ids);
$uso = $conn->query("
    SELECT dot.material_id, ot.tipo, ot.estado, SUM(dot.cantidad) AS total
    FROM detalle_ot dot
    JOIN ordenes_trabajo ot ON ot.id = dot.ot_id
    WHERE DATE_FORMAT(ot.fecha,'%Y-%m') = '$mes'
      AND dot.material_id IN ($placeholders)
    GROUP BY dot.material_id, ot.tipo, ot.estado
");
while ($row = $uso->fetch_assoc()) {
    $mid  = $row['material_id'];
    $tipo = $row['tipo'];
    // Normalizar MJ → MEJ para comparación
    $tipoN = ($tipo === 'MJ') ? 'MEJ' : $tipo;
    $total = (float)$row['total'];
    $est   = $row['estado'];

    if (!isset($uso_por_tipo[$mid])) {
        $uso_por_tipo[$mid] = ['total'=>0,'aprob'=>[],'inf'=>[],'env'=>[]];
    }
    $uso_por_tipo[$mid]['total'] += $total;

    if (in_array($est, ['Aprobado','Ejecutado'])) {
        foreach ([$tipoN, $tipo] as $tc) {
            $uso_por_tipo[$mid]['aprob'][$tc] = ($uso_por_tipo[$mid]['aprob'][$tc] ?? 0) + $total;
        }
    }
    if ($est === 'Ejecutado') {
        foreach ([$tipoN, $tipo] as $tc) {
            $uso_por_tipo[$mid]['inf'][$tc] = ($uso_por_tipo[$mid]['inf'][$tc] ?? 0) + $total;
        }
    }
}

// ── LAYOUT DE COLUMNAS ────────────────────────────────────────
// Cols fijas: N°(1) + CodES(2) + Nombre(3) + Unidad(4) + 3 pedidos(5,6,7) + blank(8) + TOTAL(9)
// Aprobados: len(tipos_aprob) cols + 1 total
// Informados: len(tipos_inf)  cols + 1 total
// Enviados:  len(tipos_env)   cols + 1 total
// Usados(1) + Saldo(1)

$col_inicio_aprob = 10;  // columna J
$col_total_aprob  = $col_inicio_aprob + count($tipos_aprob);
$col_inicio_inf   = $col_total_aprob + 1;
$col_total_inf    = $col_inicio_inf + count($tipos_inf);
$col_inicio_env   = $col_total_inf + 1;
$col_total_env    = $col_inicio_env + count($tipos_env);
$col_usados       = $col_total_env + 1;
$col_saldo        = $col_usados + 1;
$totalCols        = $col_saldo;

// ── SPREADSHEET ───────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$ws = $spreadsheet->getActiveSheet()->setTitle($sheetName);

// ════════════════════════════════════════════════════════════
// FILA 1 — Título principal
// ════════════════════════════════════════════════════════════
$ws->mergeCells('A1:'.cl($totalCols).'1');
$ws->setCellValue('A1', $mesLabel . ' (CONTRATO ' . $contrato . ')');
applyStyle($ws, 'A1:'.cl($totalCols).'1', hdrStyle('1E3A5F','FFFFFF',true,true));
$ws->getRowDimension(1)->setRowHeight(22);

// ════════════════════════════════════════════════════════════
// FILA 2 — Subtítulo pedido
// ════════════════════════════════════════════════════════════
$ws->mergeCells('A2:'.cl($totalCols).'2');
$ws->setCellValue('A2', $tit_pedido);
applyStyle($ws, 'A2:'.cl($totalCols).'2', hdrStyle('2563EB','FFFFFF',true,false));
$ws->getRowDimension(2)->setRowHeight(18);

// ════════════════════════════════════════════════════════════
// FILA 3 — Cabeceras de grupos
// ════════════════════════════════════════════════════════════
// Pedidos individuales
for ($pi=0; $pi<3; $pi++) {
    $c = cl(5+$pi).'3';
    $p = $pedidos[$pi] ?? null;
    if ($pi < 2) {
        $ws->setCellValue($c, ($pi+1).'er PEDIDO');
    } else {
        $ws->setCellValue($c, '3ro PEDIDO');
    }
    applyStyle($ws, $c, hdrStyle('334155'));
}

// TOTAL pedidos
$ws->setCellValue(cl(9).'3', 'TOTAL');
applyStyle($ws, cl(9).'3', hdrStyle('1F3864'));

// TOTAL APROBADOS (merged sobre subcolumnas)
$rngAprob = cl($col_inicio_aprob).'3:'.cl($col_total_aprob).'3';
$ws->mergeCells($rngAprob);
$ws->setCellValue(cl($col_inicio_aprob).'3', 'TOTAL APROBADOS');
applyStyle($ws, $rngAprob, hdrStyle('1E4D8C'));

// TOTAL INFORMADOS (merged)
$rngInf = cl($col_inicio_inf).'3:'.cl($col_total_inf).'3';
$ws->mergeCells($rngInf);
$ws->setCellValue(cl($col_inicio_inf).'3', 'TOTAL INFORMADOS');
applyStyle($ws, $rngInf, hdrStyle('0F3460'));

// TOTAL ENVIADOS (merged)
$rngEnv = cl($col_inicio_env).'3:'.cl($col_total_env).'3';
$ws->mergeCells($rngEnv);
$ws->setCellValue(cl($col_inicio_env).'3', 'TOTAL ENVIADOS');
applyStyle($ws, $rngEnv, hdrStyle('163B60'));

$ws->setCellValue(cl($col_usados).'3', 'MATERIALES USADOS');
applyStyle($ws, cl($col_usados).'3', hdrStyle('166534','FFFFFF'));

$ws->setCellValue(cl($col_saldo).'3', 'SALDO');
applyStyle($ws, cl($col_saldo).'3', hdrStyle('991B1B','FFFFFF'));

$ws->getRowDimension(3)->setRowHeight(28);

// ════════════════════════════════════════════════════════════
// FILA 4 — Fechas de pedidos
// ════════════════════════════════════════════════════════════
$ws->setCellValue('D4', 'fecha');
applyStyle($ws, 'D4', hdrStyle('334155','94A3B8',false,false));

for ($pi=0; $pi<3; $pi++) {
    $c = cl(5+$pi).'4';
    $p = $pedidos[$pi] ?? null;
    $ws->setCellValue($c, $p ? date('d/m/Y', strtotime($p['fecha'])) : '');
    applyStyle($ws, $c, hdrStyle('334155','CBD5E1',false));
}
// Llenar fila 4 del resto con vacío del mismo color
for ($ci = 9; $ci <= $totalCols; $ci++) {
    applyStyle($ws, cl($ci).'4', hdrStyle('1A2B3C','FFFFFF',false));
}
$ws->getRowDimension(4)->setRowHeight(16);

// ════════════════════════════════════════════════════════════
// FILA 5 — Subencabezados de tipos
// ════════════════════════════════════════════════════════════
$ws->setCellValue('D5', 'Movimiento de M.');
applyStyle($ws, 'D5', hdrStyle('334155','94A3B8',false,false));

// Cabeceras fijas A-I
for ($ci=1; $ci<=9; $ci++) {
    applyStyle($ws, cl($ci).'5', hdrStyle('1E293B','64748B',false));
}

// Sub-cols APROBADOS
foreach ($tipos_aprob as $i => $t) {
    $c = cl($col_inicio_aprob + $i).'5';
    $ws->setCellValue($c, $t);
    applyStyle($ws, $c, hdrStyle('1E4D8C'));
}
applyStyle($ws, cl($col_total_aprob).'5', hdrStyle('1E3A5F'));

// Sub-cols INFORMADOS
foreach ($tipos_inf as $i => $t) {
    $c = cl($col_inicio_inf + $i).'5';
    $ws->setCellValue($c, $t);
    applyStyle($ws, $c, hdrStyle('0F3460'));
}
applyStyle($ws, cl($col_total_inf).'5', hdrStyle('0A2040'));

// Sub-cols ENVIADOS
foreach ($tipos_env as $i => $t) {
    $c = cl($col_inicio_env + $i).'5';
    $ws->setCellValue($c, $t);
    applyStyle($ws, $c, hdrStyle('163B60'));
}
applyStyle($ws, cl($col_total_env).'5', hdrStyle('0D2840'));

applyStyle($ws, cl($col_usados).'5', hdrStyle('166534'));
applyStyle($ws, cl($col_saldo).'5',  hdrStyle('991B1B'));
$ws->getRowDimension(5)->setRowHeight(22);

// ════════════════════════════════════════════════════════════
// FILAS DE DATOS
// ════════════════════════════════════════════════════════════
$fila = 6;
$nItem = 1;

foreach ($materiales as $mat) {
    $mid = $mat['id'];
    $uso = $uso_por_tipo[$mid] ?? ['total'=>0,'aprob'=>[],'inf'=>[],'env'=>[]];

    // Totales de pedidos
    $cant_pedidos = [];
    $total_ped = 0;
    foreach ($pedidos as $pi => $p) {
        $c = (float)($pedido_cantidades[$p['id']][$mid] ?? 0);
        $cant_pedidos[$pi] = $c;
        $total_ped += $c;
    }

    $total_aprob = 0;
    foreach ($tipos_aprob as $t) {
        $total_aprob += ($uso['aprob'][$t] ?? 0);
    }
    $total_inf = 0;
    foreach ($tipos_inf as $t) {
        $total_inf += ($uso['inf'][$t] ?? 0);
    }
    $total_env = $total_aprob; // Simplificado
    $usados    = $uso['total'];
    $saldo     = $total_ped - $usados;

    // Saltar si todo cero y sin pedido
    // (mantener para mostrar todos los materiales del catálogo)

    $bg = ($nItem % 2 === 0) ? 'EBF0FA' : 'FFFFFF';

    $ws->setCellValue('A'.$fila, $nItem);
    $ws->setCellValue('B'.$fila, $mat['codigo_electrosur'] ?? '');
    $ws->setCellValue('C'.$fila, $mat['nombre']);
    $ws->setCellValue('D'.$fila, $mat['unidad']);

    applyStyle($ws, 'A'.$fila, dataStyle($bg));
    applyStyle($ws, 'B'.$fila, dataStyle($bg));
    $ws->getStyle('C'.$fila)->applyFromArray([
        'font'      => ['bold'=>true,'name'=>'Arial','size'=>9],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E4']]],
    ]);
    applyStyle($ws, 'D'.$fila, dataStyle($bg));

    // Pedidos
    for ($pi=0; $pi<3; $pi++) {
        $c = cl(5+$pi).$fila;
        $v = $cant_pedidos[$pi] ?? 0;
        if ($v > 0) $ws->setCellValue($c, $v);
        applyStyle($ws, $c, dataStyle($bg));
    }

    // TOTAL pedidos
    $c9 = cl(9).$fila;
    if ($total_ped > 0) $ws->setCellValue($c9, $total_ped);
    $ws->getStyle($c9)->applyFromArray([
        'font'      => ['bold'=>true,'name'=>'Arial','size'=>9,'color'=>['rgb'=>'1F3864']],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E4']]],
    ]);

    // APROBADOS por tipo
    foreach ($tipos_aprob as $i => $t) {
        $c = cl($col_inicio_aprob + $i).$fila;
        $v = $uso['aprob'][$t] ?? 0;
        if ($v > 0) $ws->setCellValue($c, $v);
        applyStyle($ws, $c, dataStyle($bg));
    }
    $cta = cl($col_total_aprob).$fila;
    if ($total_aprob > 0) $ws->setCellValue($cta, $total_aprob);
    $ws->getStyle($cta)->applyFromArray([
        'font'=>['bold'=>true,'name'=>'Arial','size'=>9,'color'=>['rgb'=>'1F3864']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B8C8']]],
    ]);

    // INFORMADOS por tipo
    foreach ($tipos_inf as $i => $t) {
        $c = cl($col_inicio_inf + $i).$fila;
        $v = $uso['inf'][$t] ?? 0;
        if ($v > 0) $ws->setCellValue($c, $v);
        applyStyle($ws, $c, dataStyle($bg));
    }
    $cti = cl($col_total_inf).$fila;
    if ($total_inf > 0) $ws->setCellValue($cti, $total_inf);
    $ws->getStyle($cti)->applyFromArray([
        'font'=>['bold'=>true,'name'=>'Arial','size'=>9],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'B0B8C8']]],
    ]);

    // ENVIADOS
    $cenv = cl($col_inicio_env).$fila;
    if ($total_env > 0) $ws->setCellValue($cenv, $total_env);
    applyStyle($ws, $cenv, dataStyle($bg));
    for ($ei=1; $ei<count($tipos_env); $ei++) {
        applyStyle($ws, cl($col_inicio_env+$ei).$fila, dataStyle($bg));
    }
    $ctenv = cl($col_total_env).$fila;
    if ($total_env > 0) $ws->setCellValue($ctenv, $total_env);
    applyStyle($ws, $ctenv, dataStyle($bg));

    // USADOS
    $cus = cl($col_usados).$fila;
    if ($usados > 0) $ws->setCellValue($cus, $usados);
    $ws->getStyle($cus)->applyFromArray([
        'font'=>['bold'=>true,'name'=>'Arial','size'=>9,'color'=>['rgb'=>'15803D']],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E4']]],
    ]);

    // SALDO
    $csal = cl($col_saldo).$fila;
    $ws->setCellValue($csal, '='.cl(9).$fila.'-'.cl($col_usados).$fila);
    $ws->getStyle($csal)->applyFromArray([
        'font'=>['bold'=>true,'name'=>'Arial','size'=>9,
                 'color'=>['rgb'=>$saldo<0?'DC2626':($saldo==0?'94A3B8':'B45309')]],
        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$bg]],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'=>['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'D0D8E4']]],
    ]);

    $ws->getRowDimension($fila)->setRowHeight(16);
    $fila++;
    $nItem++;
}

// ════════════════════════════════════════════════════════════
// FILA TOTAL
// ════════════════════════════════════════════════════════════
$dataStart = 6;
$dataEnd   = $fila - 1;

$ws->setCellValue('A'.$fila, 'TOTAL GENERAL');
$ws->mergeCells('A'.$fila.':D'.$fila);
$ws->getStyle('A'.$fila)->applyFromArray(hdrStyle('1E3A5F'));

for ($ci = 5; $ci <= $totalCols; $ci++) {
    $c  = cl($ci).$fila;
    $cR = cl($ci);
    $ws->setCellValue($c, "=SUM({$cR}{$dataStart}:{$cR}{$dataEnd})");
    $ws->getStyle($c)->applyFromArray([
        'font'      => ['bold'=>true,'name'=>'Arial','size'=>9,'color'=>['rgb'=>'FFFFFF']],
        'fill'      => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1E3A5F']],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders'=>['style'=>Border::BORDER_THIN,'color'=>['rgb'=>'334155']]],
    ]);
}
$ws->getRowDimension($fila)->setRowHeight(20);

// ════════════════════════════════════════════════════════════
// ANCHOS DE COLUMNAS
// ════════════════════════════════════════════════════════════
$ws->getColumnDimension('A')->setWidth(5);
$ws->getColumnDimension('B')->setWidth(10);
$ws->getColumnDimension('C')->setWidth(38);
$ws->getColumnDimension('D')->setWidth(10);
for ($pi=0; $pi<3; $pi++) $ws->getColumnDimension(cl(5+$pi))->setWidth(9);
$ws->getColumnDimension(cl(8))->setWidth(2);   // blank
$ws->getColumnDimension(cl(9))->setWidth(8);   // TOTAL

for ($ci = $col_inicio_aprob; $ci <= $totalCols; $ci++) {
    $w = ($ci === $col_total_aprob || $ci === $col_total_inf ||
          $ci === $col_total_env  || $ci === $col_usados || $ci === $col_saldo) ? 10 : 7;
    $ws->getColumnDimension(cl($ci))->setWidth($w);
}

$ws->freezePane('E6');

// ── Enviar archivo limpio via temp file ───────────────────────
$filename = 'Liquidacion_' . str_replace(' ','_',$tipo_liq) . '_' . str_replace('-','_',$mes) . '.xlsx';
$tmpFile  = tempnam(sys_get_temp_dir(), 'liq_') . '.xlsx';
(new Xlsx($spreadsheet))->save($tmpFile);

audit_log($conn, 'EXPORT_LIQUIDACION', "$tipo_liq $mes");

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit();

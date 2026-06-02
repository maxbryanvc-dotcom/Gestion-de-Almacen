<?php
// ============================================================
// EXPORTAR MATERIALES A EXCEL (.xlsx) via PhpSpreadsheet
// ============================================================
ob_start();                    // captura cualquier salida accidental
error_reporting(0);            // silencia warnings que corrompen el archivo
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

// Cargar autoload de Composer (vendor está en la raíz del proyecto)
$spreadsheetPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($spreadsheetPath)) {
    die('PhpSpreadsheet no instalado. Ejecuta: composer require phpoffice/phpspreadsheet');
}
require_once $spreadsheetPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Inventario');

// ============================================================
// ESTILOS
// ============================================================
$azulOscuro = '1E3A5F';
$azulClaro  = '2563EB';
$grisClaro  = 'F1F5F9';

// Ajuste de ancho de columnas
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(40);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(16);

// ============================================================
// ENCABEZADO CORPORATIVO
// ============================================================
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', APP_EMPRESA . ' — Reporte de Inventario');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $azulOscuro]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

$sheet->mergeCells('A2:F2');
$sheet->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i') . '  |  Usuario: ' . ($_SESSION['usuario'] ?? ''));
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['size' => 10, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $azulClaro]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(2)->setRowHeight(20);

// ============================================================
// CABECERA DE TABLA
// ============================================================
$headers = ['#', 'Código', 'Material', 'Unidad', 'Stock', 'Estado'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '3', $h);
    $col++;
}
$sheet->getStyle('A3:F3')->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '475569']]],
]);
$sheet->getRowDimension(3)->setRowHeight(22);

// ============================================================
// DATOS
// ============================================================
$materiales = $conn->query("SELECT * FROM materiales WHERE activo = 1 ORDER BY nombre ASC");
$row = 4;

while ($m = $materiales->fetch_assoc()) {

    $stock  = (int)$m['stock'];
    if ($stock <= 0)        { $estado = 'AGOTADO';    $colorFondo = 'FEE2E2'; $colorTexto = 'DC2626'; }
    elseif ($stock <= 5)    { $estado = 'CRÍTICO';    $colorFondo = 'FEF9C3'; $colorTexto = 'B45309'; }
    elseif ($stock <= 10)   { $estado = 'BAJO';       $colorFondo = 'DBEAFE'; $colorTexto = '1D4ED8'; }
    else                    { $estado = 'DISPONIBLE'; $colorFondo = 'DCFCE7'; $colorTexto = '15803D'; }

    $sheet->setCellValue('A' . $row, $m['id']);
    $sheet->setCellValue('B' . $row, $m['codigo']);
    $sheet->setCellValue('C' . $row, $m['nombre']);
    $sheet->setCellValue('D' . $row, $m['unidad']);
    $sheet->setCellValue('E' . $row, $stock);
    $sheet->setCellValue('F' . $row, $estado);

    // Fondo alternado
    $bgFila = ($row % 2 === 0) ? $grisClaro : 'FFFFFF';
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgFila]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    ]);

    // Color de estado
    $sheet->getStyle("F{$row}")->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorFondo]],
        'font'      => ['bold' => true, 'color' => ['rgb' => $colorTexto]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    ]);

    $sheet->getRowDimension($row)->setRowHeight(18);
    $row++;
}

// ============================================================
// PIE DE PÁGINA
// ============================================================
$sheet->mergeCells("A{$row}:F{$row}");
$sheet->setCellValue("A{$row}", 'Total: ' . ($row - 4) . ' materiales registrados');
$sheet->getStyle("A{$row}")->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
]);

// ============================================================
// HOJA 2: MOVIMIENTOS RECIENTES
// ============================================================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Movimientos');
$sheet2->getColumnDimension('A')->setWidth(18);
$sheet2->getColumnDimension('B')->setWidth(8);
$sheet2->getColumnDimension('C')->setWidth(35);
$sheet2->getColumnDimension('D')->setWidth(12);
$sheet2->getColumnDimension('E')->setWidth(20);
$sheet2->getColumnDimension('F')->setWidth(18);

$sheet2->mergeCells('A1:F1');
$sheet2->setCellValue('A1', 'Últimos Movimientos del Almacén');
$sheet2->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $azulOscuro]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$h2 = ['Fecha', 'Tipo', 'Material', 'Cantidad', 'Técnico', 'Usuario'];
$c2 = 'A';
foreach ($h2 as $h) { $sheet2->setCellValue($c2.'2', $h); $c2++; }
$sheet2->getStyle('A2:F2')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
]);

// Detectar columnas opcionales
$colE = array_column($conn->query("SHOW COLUMNS FROM entradas")->fetch_all(MYSQLI_ASSOC),'Field');
$colS = array_column($conn->query("SHOW COLUMNS FROM salidas")->fetch_all(MYSQLI_ASSOC),'Field');
$colR = array_column($conn->query("SHOW COLUMNS FROM reingresos")->fetch_all(MYSQLI_ASSOC),'Field');
$uE = in_array('usuario',$colE) ? "COALESCE(e.usuario,'')" : "''";
$uS = in_array('usuario',$colS) ? "COALESCE(s.usuario,'')" : "''";
$uR = in_array('registrado_por',$colR) ? "r.registrado_por" : "''";

$movs = $conn->query("
    SELECT e.fecha, 'ENTRADA' tipo, m.nombre material, e.cantidad, '—' tecnico, $uE usu
    FROM entradas e JOIN materiales m ON m.id=e.material_id
    UNION ALL
    SELECT s.fecha, 'SALIDA', m.nombre, s.cantidad, COALESCE(t.nombre,'—'), $uS
    FROM salidas s JOIN materiales m ON m.id=s.material_id LEFT JOIN tecnicos t ON t.id=s.tecnico_id
    UNION ALL
    SELECT r.fecha, 'REINGRESO', m.nombre, r.cantidad, COALESCE(t.nombre,'—'), $uR
    FROM reingresos r JOIN materiales m ON m.id=r.material_id LEFT JOIN tecnicos t ON t.id=r.tecnico_id
    ORDER BY fecha DESC LIMIT 100
");
$r2 = 3;
while ($mv = $movs->fetch_assoc()) {
    $color = match($mv['tipo']) { 'ENTRADA' => 'DCFCE7', 'SALIDA' => 'FEE2E2', default => 'EDE9FE' };
    $sheet2->setCellValue('A'.$r2, date('d/m/Y H:i', strtotime($mv['fecha'])));
    $sheet2->setCellValue('B'.$r2, $mv['tipo']);
    $sheet2->setCellValue('C'.$r2, $mv['material']);
    $sheet2->setCellValue('D'.$r2, $mv['cantidad']);
    $sheet2->setCellValue('E'.$r2, $mv['tecnico']);
    $sheet2->setCellValue('F'.$r2, $mv['usu']);
    $sheet2->getStyle("B{$r2}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        'font' => ['bold' => true],
    ]);
    $r2++;
}

$spreadsheet->setActiveSheetIndex(0);

// ============================================================
// ENVIAR ARCHIVO — guardar en temp y enviar limpio
// ============================================================
$filename = 'Inventario_' . date('Ymd_His') . '.xlsx';
$tmpFile  = tempnam(sys_get_temp_dir(), 'excel_') . '.xlsx';

// Guardar en archivo temporal (evita contaminación con warnings PHP)
(new Xlsx($spreadsheet))->save($tmpFile);

// Limpiar cualquier salida acumulada y enviar el archivo limpio
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);   // borrar archivo temporal

audit_log($conn, 'EXPORT_EXCEL', "Exportacion de inventario a Excel");
exit();

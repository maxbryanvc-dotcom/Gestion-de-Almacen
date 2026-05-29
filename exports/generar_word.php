<?php
// ============================================================
// GENERAR CARTA PEDIDO DE MATERIALES — formato CONSORCIO SAR
// Reproduce exactamente el documento original en Tahoma 10pt
// ============================================================
ob_start();

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table   as TableStyle;
use PhpOffice\PhpWord\SimpleType\JcTable;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID de requerimiento no válido.');

// ============================================================
// DATOS DEL REQUERIMIENTO
// ============================================================
$stmt = $conn->prepare("SELECT * FROM requerimientos WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) die('Requerimiento no encontrado.');

// Detalle de materiales
$stmt2 = $conn->prepare("
    SELECT m.codigo, m.nombre, m.unidad, d.cantidad
    FROM detalle_requerimiento d
    JOIN materiales m ON m.id = d.material_id
    WHERE d.requerimiento_id = ?
    ORDER BY m.nombre ASC
");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$detalles = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// ============================================================
// DATOS CONFIGURABLES DEL DOCUMENTO
// ============================================================
$numeroCarta   = $req['codigo_req'] ?? ('REQ-' . str_pad($id, 3, '0', STR_PAD_LEFT));
$fechaDoc      = isset($req['fecha']) ? fecha_larga($req['fecha']) : fecha_larga(date('Y-m-d'));
$supervisor    = 'SUPERVISOR COMERCIAL SERVICIO ELECTRICO LA CONVENCION';
$nombreSuperv  = 'Ing. POLICARPIO DELGADO TITO';
$contrato      = 'Contrato Nº 051-2025';
$servicio      = '"SERVICIOS COMERCIALES LA CONVENCION"';
$asunto        = 'PEDIDO DE MATERIALES ' . strtoupper(strftime('%B %Y', strtotime($req['fecha'] ?? 'now')));
$controlling   = '21042121';
$firmante      = strtoupper($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'SUPERVISOR COMERCIAL');
$cargo         = 'SUPERVISOR COMERCIAL — CONSORCIO SAR';

// ============================================================
// HELPER: fecha en español
// ============================================================
function fecha_larga(string $fecha): string {
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $t = strtotime($fecha);
    return date('j', $t) . ' de ' . $meses[(int)date('n', $t)] . ' del ' . date('Y', $t);
}

// ============================================================
// ESTILOS GLOBALES
// ============================================================
$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Tahoma');
$phpWord->setDefaultFontSize(10);

// Estilos de párrafo reutilizables
$pNormal = [
    'spaceAfter'  => 0,
    'lineHeight'  => 1.0,
    'alignment'   => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
];
$pRight = array_merge($pNormal, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);
$pLeft  = array_merge($pNormal, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::START]);
$pCenter= array_merge($pNormal, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

// Estilos de fuente
$fNormal = ['name' => 'Tahoma', 'size' => 10];
$fBold   = ['name' => 'Tahoma', 'size' => 10, 'bold' => true];
$fUnd    = ['name' => 'Tahoma', 'size' => 10, 'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE];
$fBoldUnd= ['name' => 'Tahoma', 'size' => 10, 'bold' => true,
             'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE];

// ============================================================
// SECCIÓN — márgenes igual al documento original
// ============================================================
$section = $phpWord->addSection([
    'marginTop'    => 1134,   // ~2 cm
    'marginBottom' => 1134,
    'marginLeft'   => 1701,   // ~3 cm
    'marginRight'  => 1134,   // ~2 cm
]);

// ============================================================
// 1. FECHA (alineada a la derecha)
// ============================================================
$pFecha = $section->addTextRun($pRight);
$pFecha->addText('Quillabamba, ' . $fechaDoc . '.', $fNormal);

$section->addTextBreak(1);

// ============================================================
// 2. NÚMERO DE CARTA (negrita + subrayado, justificado)
// ============================================================
$pCarta = $section->addTextRun($pNormal);
$pCarta->addText('CARTA N°  ' . $numeroCarta . ' -2026-CONSORCIOSAR', $fBoldUnd);

$section->addTextBreak(1);

// ============================================================
// 3. DESTINATARIO
// ============================================================
$section->addText('Señor(a):', $fNormal, $pLeft);
$section->addText($supervisor,  $fNormal, array_merge($pLeft, ['spaceAfter' => 0]));
$section->addText($nombreSuperv, $fBold, $pLeft);
$pPres = $section->addTextRun($pLeft);
$pPres->addText('Presente. -', $fUnd);

$section->addTextBreak(1);

// ============================================================
// 4. ASUNTO (negrita con tab)
// ============================================================
$pAsunto = $section->addTextRun(array_merge($pNormal, [
    'tabs' => [new \PhpOffice\PhpWord\Style\Tab('left', 1985)],
    'indentation' => ['left' => 1985, 'hanging' => 1277],
]));
$pAsunto->addText('Asunto:', $fBold);
$pAsunto->addText("\t" . $asunto, $fBold);

// ============================================================
// 5. REFERENCIA (con tab)
// ============================================================
$tabRef = [
    'tabs' => [new \PhpOffice\PhpWord\Style\Tab('left', 1985)],
    'indentation' => ['left' => 2268, 'hanging' => 1559],
    'spaceAfter'  => 0,
    'lineHeight'  => 1.0,
];
$pRef = $section->addTextRun($tabRef);
$pRef->addText('Referencia:', $fNormal);
$pRef->addText("\t" . $contrato, $fNormal);

$pRef2 = $section->addTextRun($tabRef);
$pRef2->addText("\t" . $servicio, $fNormal);

$section->addTextBreak(1);

// ============================================================
// 6. SALUDO
// ============================================================
$section->addText('De nuestra consideración:', $fNormal, $pNormal);
$section->addTextBreak(1);

// ============================================================
// 7. CUERPO
// ============================================================
$pCuerpo = $section->addTextRun($pNormal);
$pCuerpo->addText('Mediante la presente me dirijo a usted, en calidad de Supervisor Comercial del ', $fNormal);
$pCuerpo->addText('CONSORCIO SAR', $fBold);
$pCuerpo->addText(', conformado por las empresas ', $fNormal);
$pCuerpo->addText('ATL MAQUINARIAS Y SERVICIOS S.A.C. CON RUC 20604670145 Y LA EMPRESA ELECSERT S.R.L. CON RUC 20532402167', $fBold);
$pCuerpo->addText(', mediante la presente le hacemos llegar la solicitud del pedido de materiales para la atención de ', $fNormal);
$pCuerpo->addText('INSTALACIONES NUEVAS.', $fBold);

$section->addTextBreak(1);

// ============================================================
// 8. FRASE INTRO TABLA
// ============================================================
$section->addText('De acuerdo al detalle adjunto los movimientos de mercancías como:', $fNormal, $pNormal);
$section->addTextBreak(1);

// ============================================================
// 9. MINI TABLA: Controlling | código
// ============================================================
$miniTable = $section->addTable([
    'borderSize'  => 6,
    'borderColor' => '000000',
    'cellMarginTop'    => 80,
    'cellMarginBottom' => 80,
    'cellMarginLeft'   => 120,
    'cellMarginRight'  => 120,
]);
$miniTable->addRow();
$miniTable->addCell(1900)->addText('Controlling', $fBold);
$miniTable->addCell(2400)->addText($controlling,  $fBold);

$section->addTextBreak(1);

// ============================================================
// 10. TABLA PRINCIPAL DE MATERIALES
// ============================================================
$section->addText('Relación de materiales solicitados:', $fBold, array_merge($pNormal, ['spaceAfter' => 100]));

// Anchos de columna (total ~9360 = A4 con márgenes)
$colWidths = [600, 4500, 1200, 1200, 1860];

$tableMain = $section->addTable([
    'borderSize'       => 6,
    'borderColor'      => '4472C4',
    'cellMarginTop'    => 80,
    'cellMarginBottom' => 80,
    'cellMarginLeft'   => 120,
    'cellMarginRight'  => 120,
]);

// Cabecera de tabla
$headerBg   = 'E9EFF7';  // azul claro
$fHeader    = ['name' => 'Tahoma', 'size' => 9, 'bold' => true, 'color' => '1F3864'];
$pHeaderCell= ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'spaceAfter' => 0];

$tableMain->addRow(400);
$tableMain->addCell($colWidths[0], cellStyle('1F3864', 'FFFFFF'))->addText('N°',          ['name'=>'Tahoma','size'=>9,'bold'=>true,'color'=>'FFFFFF'], $pHeaderCell);
$tableMain->addCell($colWidths[1], cellStyle('1F3864', 'FFFFFF'))->addText('Descripción del Material', ['name'=>'Tahoma','size'=>9,'bold'=>true,'color'=>'FFFFFF'], $pHeaderCell);
$tableMain->addCell($colWidths[2], cellStyle('1F3864', 'FFFFFF'))->addText('Unidad',       ['name'=>'Tahoma','size'=>9,'bold'=>true,'color'=>'FFFFFF'], $pHeaderCell);
$tableMain->addCell($colWidths[3], cellStyle('1F3864', 'FFFFFF'))->addText('Cantidad',     ['name'=>'Tahoma','size'=>9,'bold'=>true,'color'=>'FFFFFF'], $pHeaderCell);
$tableMain->addCell($colWidths[4], cellStyle('1F3864', 'FFFFFF'))->addText('Código',       ['name'=>'Tahoma','size'=>9,'bold'=>true,'color'=>'FFFFFF'], $pHeaderCell);

// Filas de materiales
$n = 1;
foreach ($detalles as $d) {
    $bg = ($n % 2 === 0) ? 'EBF0FA' : 'FFFFFF';
    $fCelda = ['name' => 'Tahoma', 'size' => 9];
    $pCell  = ['spaceAfter' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH];
    $pCellC = ['spaceAfter' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];

    $tableMain->addRow(350);
    $tableMain->addCell($colWidths[0], cellStyle($bg))->addText($n,               $fCelda, $pCellC);
    $tableMain->addCell($colWidths[1], cellStyle($bg))->addText($d['nombre'],     $fCelda, $pCell);
    $tableMain->addCell($colWidths[2], cellStyle($bg))->addText($d['unidad'],     $fCelda, $pCellC);
    $tableMain->addCell($colWidths[3], cellStyle($bg))->addText($d['cantidad'],   $fCelda, $pCellC);
    $tableMain->addCell($colWidths[4], cellStyle($bg))->addText($d['codigo'] ?? '', $fCelda, $pCellC);
    $n++;
}

// Fila de total
$tableMain->addRow(350);
$totalCell = $tableMain->addCell(array_sum($colWidths) - $colWidths[3] - $colWidths[4],
    ['gridSpan' => 3, 'bgColor' => 'D6E4F0']);
$totalCell->addText('TOTAL DE ÍTEMS:', ['name'=>'Tahoma','size'=>9,'bold'=>true],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT, 'spaceAfter' => 0]);
$tableMain->addCell($colWidths[3], cellStyle('D6E4F0'))
    ->addText(count($detalles), ['name'=>'Tahoma','size'=>9,'bold'=>true],
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]);
$tableMain->addCell($colWidths[4], cellStyle('D6E4F0'))->addText('', []);

$section->addTextBreak(1);

// ============================================================
// 11. CIERRE
// ============================================================
$section->addText(
    'Agradezco anticipadamente la atención que merezca la presente y nos encomendamos a su disposición.',
    array_merge($fNormal, ['italic' => true]),
    $pNormal
);

$section->addTextBreak(1);
$section->addText('Atentamente,', $fNormal, $pCenter);

$section->addTextBreak(4);  // espacio para firma

// ============================================================
// 12. FIRMA
// ============================================================
$pFirma = ['spaceAfter' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
$section->addText('_______________________________', $fNormal, $pFirma);
$section->addText($firmante, $fBold, $pFirma);
$section->addText($cargo,    $fNormal, $pFirma);
$section->addText('CONSORCIO SAR', $fBold, $pFirma);

// ============================================================
// HELPER: estilo de celda con color de fondo
// ============================================================
function cellStyle(string $bg = 'FFFFFF', string $border = '4472C4'): array {
    return [
        'bgColor'      => $bg,
        'borderColor'  => $border,
        'borderSize'   => 6,
    ];
}

// ============================================================
// GENERAR Y ENVIAR ARCHIVO
// ============================================================
ob_end_clean();

$filename = 'CartaPedido_' . preg_replace('/[^A-Za-z0-9\-]/', '', $numeroCarta) . '_' . date('Ymd') . '.docx';

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');

audit_log($conn, 'GENERAR_WORD', "Carta pedido requerimiento ID $id");
exit;

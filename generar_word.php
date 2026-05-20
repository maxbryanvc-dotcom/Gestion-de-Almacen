<?php
ob_start();

require_once(__DIR__ . '/vendor/autoload.php');
include("conexion.php");

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

if (!isset($_GET['id'])) {
    die("ID no especificado");
}

$id = intval($_GET['id']);

// obtener requerimiento
$req = $conn->query("SELECT * FROM requerimientos WHERE id=$id")->fetch_assoc();

// obtener detalle
$detalle = $conn->query("
SELECT m.nombre, d.cantidad 
FROM detalle_requerimiento d
JOIN materiales m ON d.material_id = m.id
WHERE d.requerimiento_id = $id
");

// crear documento
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// contenido
$section->addText('REQUERIMIENTO DE MATERIALES', ['bold'=>true], ['alignment'=>'center']);
$section->addTextBreak(1);

$section->addText("Código: REQ-" . $req['id']);
$section->addText("Fecha: " . $req['fecha']);

$section->addTextBreak(1);

// tabla
$table = $section->addTable();

$table->addRow();
$table->addCell(5000)->addText('Material');
$table->addCell(2000)->addText('Cantidad');

while ($row = $detalle->fetch_assoc()) {
    $table->addRow();
    $table->addCell(5000)->addText($row['nombre']);
    $table->addCell(2000)->addText($row['cantidad']);
}

// limpiar salida
ob_end_clean();

// headers correctos
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=requerimiento.docx");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");

// generar archivo
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");

exit;
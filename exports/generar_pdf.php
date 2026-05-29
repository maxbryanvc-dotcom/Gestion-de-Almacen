<?php

// ===============================
// LIMPIAR BUFFER
// ===============================
ob_start();

// ===============================
// FPDF
// ===============================
require('fpdf/fpdf.php');

// ===============================
// CONEXIÓN
// ===============================
require_once __DIR__ . '/../includes/Conexion.php';

// ===============================
// VALIDAR ID
// ===============================
if(!isset($_GET['id'])){

    die("ID no especificado");
}

$id = intval($_GET['id']);

// ===============================
// OBTENER REQUERIMIENTO
// ===============================
$req = $conn->query("
SELECT *
FROM requerimientos
WHERE id = $id
")->fetch_assoc();

// ===============================
// VALIDAR EXISTENCIA
// ===============================
if(!$req){

    die("Requerimiento no encontrado");
}

// ===============================
// DETALLE DE MATERIALES
// ===============================
$detalle = $conn->query("

SELECT 

    m.nombre,

    d.cantidad

FROM detalle_requerimiento d

INNER JOIN materiales m
ON d.material_id = m.id

WHERE d.requerimiento_id = $id

");

// ===============================
// CREAR PDF
// ===============================
$pdf = new FPDF();

$pdf->AddPage();

$pdf->SetAutoPageBreak(true,20);

// ===============================
// ENCABEZADO
// ===============================
$pdf->SetFont('Arial','B',18);

$pdf->SetTextColor(30,41,59);

$pdf->Cell(0,10,'ERP ALMACEN',0,1,'C');

$pdf->SetFont('Arial','',11);

$pdf->SetTextColor(100,100,100);

$pdf->Cell(0,6,'Sistema Profesional de Gestion de Inventario',0,1,'C');

$pdf->Cell(0,6,'Quillabamba - Cusco - Peru',0,1,'C');

$pdf->Ln(10);

// ===============================
// TITULO
// ===============================
$pdf->SetFont('Arial','B',16);

$pdf->SetTextColor(0,0,0);

$pdf->Cell(0,10,'REPORTE DE REQUERIMIENTO',0,1,'C');

$pdf->Ln(5);

// ===============================
// INFORMACIÓN GENERAL
// ===============================
$pdf->SetFont('Arial','',12);

$pdf->Cell(50,8,'Codigo:',0,0);

$pdf->Cell(100,8,'REQ-'.$req['id'],0,1);

$pdf->Cell(50,8,'Fecha:',0,0);

$pdf->Cell(
    100,
    8,
    date('d/m/Y', strtotime($req['fecha'])),
    0,
    1
);

$pdf->Ln(8);

// ===============================
// DESCRIPCIÓN
// ===============================
$pdf->MultiCell(

    0,

    8,

    utf8_decode(
        "El presente documento detalla los materiales solicitados para el desarrollo de actividades operativas."
    )

);

$pdf->Ln(8);

// ===============================
// CABECERA TABLA
// ===============================
$pdf->SetFillColor(59,130,246);

$pdf->SetTextColor(255,255,255);

$pdf->SetFont('Arial','B',12);

$pdf->Cell(140,10,'Material',1,0,'C',true);

$pdf->Cell(40,10,'Cantidad',1,1,'C',true);

// ===============================
// FILAS
// ===============================
$pdf->SetTextColor(0,0,0);

$pdf->SetFont('Arial','',11);

while($row = $detalle->fetch_assoc()){

    $pdf->Cell(

        140,

        10,

        utf8_decode($row['nombre']),

        1

    );

    $pdf->Cell(

        40,

        10,

        $row['cantidad'],

        1,

        1,

        'C'

    );
}

$pdf->Ln(20);

// ===============================
// FIRMA
// ===============================
$pdf->Cell(0,8,'_________________________________',0,1,'C');

$pdf->Cell(0,6,'Firma del Responsable',0,1,'C');

$pdf->Ln(15);

// ===============================
// PIE DE PÁGINA
// ===============================
$pdf->SetFont('Arial','I',9);

$pdf->SetTextColor(120,120,120);

$pdf->Cell(

    0,

    10,

    utf8_decode('Documento generado automáticamente por el ERP'),

    0,

    0,

    'C'

);

// ===============================
// LIMPIAR BUFFER
// ===============================
ob_end_clean();

// ===============================
// GENERAR PDF
// ===============================
$pdf->Output();

exit();

?>
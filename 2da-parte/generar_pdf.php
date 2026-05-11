<?php
ob_start();

require('fpdf/fpdf.php');
include("conexion.php");

if (!isset($_GET['id'])) {
    die("ID no especificado");
}

$id = $_GET['id'];

// DATOS DEL REQUERIMIENTO
$req = $conn->query("SELECT * FROM requerimientos WHERE id=$id")->fetch_assoc();

// DETALLE DE MATERIALES
$detalle = $conn->query("
SELECT m.nombre, d.cantidad 
FROM detalle_requerimiento d
JOIN materiales m ON d.material_id = m.id
WHERE d.requerimiento_id = $id
");

$pdf = new FPDF();
$pdf->AddPage();

// ENCABEZADO 
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'EMPRESA ELECTRICA',0,1,'C');

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,5,'Direccion de la empresa',0,1,'C');
$pdf->Cell(0,5,'Telefono: 999999999',0,1,'C');

$pdf->Ln(10);

// TITULO 
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'REQUERIMIENTO DE MATERIALES',0,1,'C');

$pdf->Ln(5);

// DATOS 
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Codigo: REQ-'.$req['id'],0,1);
$pdf->Cell(0,8,'Fecha: '.$req['fecha'],0,1);

$pdf->Ln(5);

// TEXTO 
$pdf->MultiCell(0,8,
"Por medio del presente documento se solicita la entrega de los siguientes materiales para el desarrollo de las actividades correspondientes:"
);

$pdf->Ln(5);

// TABLA 
$pdf->SetFont('Arial','B',12);
$pdf->Cell(120,10,'Material',1);
$pdf->Cell(40,10,'Cantidad',1);
$pdf->Ln();

$pdf->SetFont('Arial','',12);

while($row = $detalle->fetch_assoc()) {
    $pdf->Cell(120,10,$row['nombre'],1);
    $pdf->Cell(40,10,$row['cantidad'],1);
    $pdf->Ln();
}

$pdf->Ln(15);

// FIRMA 
$pdf->Cell(0,10,'_________________________',0,1,'C');
$pdf->Cell(0,5,'Firma del responsable',0,1,'C');

// LIMPIAR BUFFER
ob_end_clean();

$pdf->Output();
exit();
?>
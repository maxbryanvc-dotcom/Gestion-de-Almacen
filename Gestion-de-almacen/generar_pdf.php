<?php
ob_start(); // 🔥 evitar errores de salida

require('fpdf/fpdf.php');
include("conexion.php");

// 🔥 validar ID
if (!isset($_GET['id'])) {
    die("❌ ID no especificado");
}

$id = $_GET['id'];

//  obtener requerimiento
$req = $conn->query("SELECT * FROM requerimientos WHERE id=$id")->fetch_assoc();

//  obtener detalle
$detalle = $conn->query("
SELECT m.nombre, d.cantidad 
FROM detalle_requerimiento d
JOIN materiales m ON d.material_id = m.id
WHERE d.requerimiento_id = $id
");

$pdf = new FPDF();
$pdf->AddPage();

//  título
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'REQUERIMIENTO DE MATERIALES',0,1,'C');

//  datos
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Codigo: '.$req['codigo_req'],0,1);
$pdf->Cell(0,10,'Fecha: '.$req['fecha'],0,1);

$pdf->Ln(5);

//  tabla
$pdf->SetFont('Arial','B',12);
$pdf->Cell(100,10,'Material',1);
$pdf->Cell(40,10,'Cantidad',1);
$pdf->Ln();

$pdf->SetFont('Arial','',12);

while($row = $detalle->fetch_assoc()) {
    $pdf->Cell(100,10,$row['nombre'],1);
    $pdf->Cell(40,10,$row['cantidad'],1);
    $pdf->Ln();
}

//  limpiar buffer (CLAVE)
ob_end_clean();

$pdf->Output();
exit();
?>
<?php
// ============================================================
// REPORTE KARDEX EN PDF — usa FPDF (ya instalado)
// ============================================================
require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
verificarRol(['admin', 'almacen']);

// Buscar FPDF
$fpdfPaths = [
    __DIR__ . '/../fpdf/fpdf.php',
    __DIR__ . '/vendor/setasign/fpdf/fpdf.php',
];
$fpdfLoaded = false;
foreach ($fpdfPaths as $p) {
    if (file_exists($p)) { require_once $p; $fpdfLoaded = true; break; }
}
if (!$fpdfLoaded) die('FPDF no encontrado. Verifica la carpeta /fpdf o ejecuta composer.');

// Parámetros opcionales de filtro por fecha
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Obtener movimientos del Kardex
$stmtK = $conn->prepare("
    SELECT 'ENTRADA' tipo, e.fecha, m.nombre material, m.codigo, e.cantidad,
           COALESCE(e.observacion,'') obs, COALESCE(e.usuario,'—') usuario
    FROM entradas e JOIN materiales m ON m.id=e.material_id
    WHERE DATE(e.fecha) BETWEEN ? AND ?
    UNION ALL
    SELECT 'SALIDA', s.fecha, m.nombre, m.codigo, s.cantidad,
           COALESCE(s.observacion,''), COALESCE(s.usuario,'—')
    FROM salidas s JOIN materiales m ON m.id=s.material_id
    WHERE DATE(s.fecha) BETWEEN ? AND ?
    UNION ALL
    SELECT 'REINGRESO', r.fecha, m.nombre, m.codigo, r.cantidad,
           r.motivo, r.registrado_por
    FROM reingresos r JOIN materiales m ON m.id=r.material_id
    WHERE DATE(r.fecha) BETWEEN ? AND ?
    ORDER BY fecha ASC
");
$stmtK->bind_param('ssssss', $desde, $hasta, $desde, $hasta, $desde, $hasta);
$stmtK->execute();
$movimientos = $stmtK->get_result();
$stmtK->close();

// ============================================================
// CLASE PDF EXTENDIDA
// ============================================================
class KardexPDF extends FPDF {

    private $empresa;
    private $desde;
    private $hasta;

    public function __construct(string $empresa, string $desde, string $hasta) {
        parent::__construct('L', 'mm', 'A4');
        $this->empresa = $empresa;
        $this->desde   = $desde;
        $this->hasta   = $hasta;
    }

    public function Header() {
        // Franja superior
        $this->SetFillColor(30, 58, 95);
        $this->Rect(0, 0, 297, 28, 'F');

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255, 255, 255);
        $this->SetY(6);
        $this->Cell(0, 8, $this->empresa, 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(180, 200, 230);
        $this->Cell(0, 5, 'KARDEX DE MOVIMIENTOS  |  Del ' .
            date('d/m/Y', strtotime($this->desde)) . ' al ' .
            date('d/m/Y', strtotime($this->hasta)), 0, 1, 'C');

        // Cabecera tabla
        $this->SetY(32);
        $this->SetFillColor(51, 65, 85);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->SetDrawColor(71, 85, 105);
        $this->Cell(30, 8, 'Fecha',     1, 0, 'C', true);
        $this->Cell(14, 8, 'Tipo',      1, 0, 'C', true);
        $this->Cell(20, 8, 'Código',    1, 0, 'C', true);
        $this->Cell(70, 8, 'Material',  1, 0, 'C', true);
        $this->Cell(18, 8, 'Cantidad',  1, 0, 'C', true);
        $this->Cell(70, 8, 'Obs./Motivo', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Usuario',   1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i') . '   |   Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// ============================================================
// GENERAR PDF
// ============================================================
$pdf = new KardexPDF(APP_EMPRESA, $desde, $hasta);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);
$pdf->SetDrawColor(226, 232, 240);

$fila = 0;
while ($mv = $movimientos->fetch_assoc()) {

    // Color de tipo
    switch ($mv['tipo']) {
        case 'ENTRADA':   $pdf->SetFillColor(220, 252, 231); $textTipo = [21, 128, 61]; break;
        case 'SALIDA':    $pdf->SetFillColor(254, 226, 226); $textTipo = [220, 38, 38]; break;
        default:          $pdf->SetFillColor(237, 233, 254); $textTipo = [124, 58, 237]; break;
    }

    $bgFila = ($fila % 2 === 0);
    if ($bgFila) $pdf->SetFillColor(248, 250, 252);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(30, 7, date('d/m/Y H:i', strtotime($mv['fecha'])), 1, 0, 'C', $bgFila);

    // Celda tipo con color
    $pdf->SetFillColor($textTipo[0] === 21 ? 220 : ($textTipo[0] === 220 ? 254 : 237),
                       $textTipo[0] === 21 ? 252 : ($textTipo[0] === 220 ? 226 : 233),
                       $textTipo[0] === 21 ? 231 : ($textTipo[0] === 220 ? 226 : 254));
    $pdf->SetTextColor($textTipo[0], $textTipo[1], $textTipo[2]);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(14, 7, $mv['tipo'], 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    if ($bgFila) $pdf->SetFillColor(248, 250, 252); else $pdf->SetFillColor(255, 255, 255);

    $pdf->Cell(20, 7, mb_strimwidth($mv['codigo'] ?? '', 0, 16, '..'), 1, 0, 'C', $bgFila);
    $pdf->Cell(70, 7, mb_strimwidth($mv['material'], 0, 45, '..'), 1, 0, 'L', $bgFila);
    $pdf->Cell(18, 7, $mv['cantidad'], 1, 0, 'C', $bgFila);
    $pdf->Cell(70, 7, mb_strimwidth($mv['obs'], 0, 48, '..'), 1, 0, 'L', $bgFila);
    $pdf->Cell(35, 7, mb_strimwidth($mv['usuario'], 0, 22, '..'), 1, 1, 'C', $bgFila);

    $fila++;
}

// Resumen al final
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(30, 58, 95);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(257, 8, "Total de movimientos en el período: $fila", 1, 1, 'R', true);

$filename = 'Kardex_' . date('Ymd') . '.pdf';
audit_log($conn, 'EXPORT_KARDEX_PDF', "Kardex del $desde al $hasta");

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
$pdf->Output('I', $filename);
exit();

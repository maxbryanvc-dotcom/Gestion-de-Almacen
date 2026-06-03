<?php
// ============================================================
// GENERAR CARTA PEDIDO — plantilla oficial CONSORCIO SAR
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID de requerimiento no válido.');

// ── Datos del requerimiento ───────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM requerimientos WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id); $stmt->execute();
$req = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$req) die('Requerimiento no encontrado.');

// ── Materiales solicitados ────────────────────────────────────
$stmt2 = $conn->prepare("
    SELECT m.codigo, m.nombre, m.unidad, d.cantidad
    FROM detalle_requerimiento d
    JOIN materiales m ON m.id = d.material_id
    WHERE d.requerimiento_id = ?
    ORDER BY m.nombre ASC
");
$stmt2->bind_param('i', $id); $stmt2->execute();
$detalles = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();

// ── Plantilla ─────────────────────────────────────────────────
$templatePath = __DIR__ . '/../uploads/plantillas/word/carta_pedido_template.docx';
if (!file_exists($templatePath)) die('Plantilla no encontrada.');

// ── Helpers ───────────────────────────────────────────────────
function fechaLarga(string $fecha): string {
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $t = strtotime($fecha);
    return date('j',$t).' de '.$meses[(int)date('n',$t)].' del '.date('Y',$t);
}
function mesAnio(string $fecha): string {
    $m = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL',
          '05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO',
          '09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
    $t = strtotime($fecha);
    return 'PEDIDO DE MATERIALES '.$m[date('m',$t)].' '.date('Y',$t);
}
function xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1|ENT_COMPAT, 'UTF-8');
}

// ── Valores de texto ──────────────────────────────────────────
$fechaDoc    = fechaLarga($req['fecha'] ?? date('Y-m-d'));
$numeroCarta = $req['codigo_req'] ?? 'REQ-'.$id;
$asunto      = mesAnio($req['fecha'] ?? date('Y-m-d'));

// ── Leer template como ZIP ────────────────────────────────────
$zip = new ZipArchive();
$zip->open($templatePath);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// ── PASO 1: Reemplazar marcadores de texto ─────────────────────
// Los marcadores pueden quedar fragmentados en múltiples <w:r>, así que
// primero limpiamos los runs para que queden juntos
function reemplazarMarcador(string $xml, string $marcador, string $valor): string {
    // Primero intentar reemplazo directo
    $reemplazado = str_replace('${' . $marcador . '}', xe($valor), $xml);
    if ($reemplazado !== $xml) return $reemplazado;

    // Si está fragmentado entre runs, usar regex
    $pattern = '/\$\{' . preg_quote($marcador, '/') . '\}/';
    return preg_replace($pattern, xe($valor), $xml);
}

$xml = reemplazarMarcador($xml, 'fecha_larga',  $fechaDoc);
$xml = reemplazarMarcador($xml, 'numero_carta', $numeroCarta);
$xml = reemplazarMarcador($xml, 'asunto',       $asunto);

// ── PASO 2: Generar filas de materiales ───────────────────────
$f = '<w:rFonts w:ascii="Tahoma" w:hAnsi="Tahoma" w:cs="Tahoma"/><w:sz w:val="18"/><w:szCs w:val="18"/>';

function filaXML(int $n, string $nombre, string $codigo, string $unidad, $cant, string $f): string {
    return '
    <w:tr>
      <w:tc><w:tcPr><w:tcW w:w="600" w:type="dxa"/></w:tcPr>
        <w:p><w:pPr><w:jc w:val="center"/></w:pPr>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xe((string)$n).'</w:t></w:r>
        </w:p></w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1500" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xe($codigo).'</w:t></w:r>
        </w:p></w:tc>
      <w:tc><w:tcPr><w:tcW w:w="4326" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xe($nombre).'</w:t></w:r>
        </w:p></w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1300" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xe($unidad).'</w:t></w:r>
        </w:p></w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1300" w:type="dxa"/></w:tcPr>
        <w:p><w:pPr><w:jc w:val="right"/></w:pPr>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xe((string)$cant).'</w:t></w:r>
        </w:p></w:tc>
    </w:tr>';
}

$filasXML = '';
foreach ($detalles as $i => $d) {
    $filasXML .= filaXML($i+1, $d['nombre'], $d['codigo']??'', $d['unidad'], $d['cantidad'], $f);
}

// ── PASO 3: Reemplazar la fila placeholder con las filas reales ─
// La fila placeholder tiene los marcadores ${item_num}, ${mat_codigo}, etc.
// Después del PASO 1 puede que queden como texto vacío o con el marcador literal.
// Buscamos la <w:tr> que contiene alguno de estos marcadores y la reemplazamos.

// Patrón: <w:tr>...</w:tr> que contiene cualquier marcador de material
$patronFila = '/<w:tr>(?:(?!<w:tr>).)*?\$\{(?:item_num|mat_nombre|mat_codigo|mat_unidad|mat_cantidad)[^}]*\}(?:(?!<w:tr>).)*?<\/w:tr>/s';

$xmlNuevo = preg_replace($patronFila, $filasXML, $xml, 1);

if ($xmlNuevo && $xmlNuevo !== $xml) {
    $xml = $xmlNuevo;
} else {
    // Fallback: buscar la fila por la estructura de anchos conocida (600+1500+4326+1300+1300)
    // y que venga después de la fila de cabecera ITEM|CODIGO|ARTICULO
    $patronFallback = '/(ITEM<\/w:t>.*?ARTICULO<\/w:t>.*?)(<w:tr>(?:(?!ITEM).)*?<\/w:tr>)/s';
    $xml = preg_replace_callback($patronFallback, function($m) use ($filasXML) {
        return $m[1] . $filasXML;
    }, $xml, 1);
}

// ── PASO 4: Empaquetar y enviar ───────────────────────────────
$tmpFile = tempnam(sys_get_temp_dir(), 'carta_') . '.docx';
$zipOut  = new ZipArchive();
$zipOut->open($tmpFile, ZipArchive::CREATE);
$zipIn = new ZipArchive();
$zipIn->open($templatePath);
for ($i = 0; $i < $zipIn->numFiles; $i++) {
    $name    = $zipIn->getNameIndex($i);
    $content = ($name === 'word/document.xml') ? $xml : $zipIn->getFromIndex($i);
    $zipOut->addFromString($name, $content);
}
$zipIn->close();
$zipOut->close();

audit_log($conn, 'GENERAR_WORD', "Carta pedido REQ ID $id: $numeroCarta");

$filename = 'CartaPedido_'.preg_replace('/[^A-Za-z0-9\-]/','',$numeroCarta).'_'.date('Ymd').'.docx';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmpFile));
header('Cache-Control: max-age=0');
readfile($tmpFile);
unlink($tmpFile);
exit;

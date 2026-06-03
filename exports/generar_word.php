<?php
// ============================================================
// GENERAR CARTA PEDIDO — usa la plantilla oficial CONSORCIO SAR
// Reemplaza marcadores de texto + genera tabla de materiales
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/Conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID de requerimiento no válido.');

// ── Datos del requerimiento ───────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM requerimientos WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$req) die('Requerimiento no encontrado.');

// ── Materiales solicitados ────────────────────────────────────
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

// ── Plantilla oficial ─────────────────────────────────────────
$templatePath = __DIR__ . '/../uploads/plantillas/word/carta_pedido_template.docx';
if (!file_exists($templatePath)) {
    die('Plantilla no encontrada en: uploads/plantillas/word/carta_pedido_template.docx');
}

// ── Helpers ───────────────────────────────────────────────────
function fechaLarga(string $fecha): string {
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $t = strtotime($fecha);
    return date('j', $t) . ' de ' . $meses[(int)date('n', $t)] . ' del ' . date('Y', $t);
}
function mesAnio(string $fecha): string {
    $meses = ['01'=>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL',
              '05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO',
              '09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=>'NOVIEMBRE','12'=>'DICIEMBRE'];
    $t = strtotime($fecha);
    return 'PEDIDO DE MATERIALES ' . $meses[date('m',$t)] . ' ' . date('Y',$t);
}
function xmlEsc(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

$fechaDoc    = fechaLarga($req['fecha'] ?? date('Y-m-d'));
$numeroCarta = $req['codigo_req'] ?? ('REQ-' . str_pad($id, 3, '0', STR_PAD_LEFT));
$asunto      = mesAnio($req['fecha'] ?? date('Y-m-d'));

// ── PASO 1: Reemplazar textos con TemplateProcessor ───────────
$tmpFile1 = tempnam(sys_get_temp_dir(), 'carta1_') . '.docx';
$processor = new TemplateProcessor($templatePath);
$processor->setValue('fecha_larga',  $fechaDoc);
$processor->setValue('numero_carta', $numeroCarta);
$processor->setValue('asunto',       $asunto);
// Limpiar marcadores de la tabla (serán reemplazados en el paso 2)
$processor->setValue('item_num',     '');
$processor->setValue('mat_nombre',   '');
$processor->setValue('mat_codigo',   '');
$processor->setValue('mat_unidad',   '');
$processor->setValue('mat_cantidad', '');
$processor->saveAs($tmpFile1);

// ── PASO 2: Inyectar tabla de materiales directamente en el XML ─
// Abre el docx como ZIP, lee document.xml, reemplaza la fila
// placeholder con TODAS las filas de materiales reales

// XML de una fila de datos con la fuente Tahoma
// Columnas: ITEM | CODIGO | ARTICULO | UNIDAD | CANTIDAD
// Sin colores — fondo blanco, bordes negros simples, igual al original
function filaXML(int $n, string $nombre, string $codigo, string $unidad, $cantidad): string {
    $f = '<w:rFonts w:ascii="Tahoma" w:hAnsi="Tahoma" w:cs="Tahoma"/><w:sz w:val="18"/><w:szCs w:val="18"/>';
    return '<w:tr>
      <w:tc><w:tcPr><w:tcW w:w="600" w:type="dxa"/></w:tcPr>
        <w:p><w:pPr><w:jc w:val="center"/></w:pPr>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xmlEsc((string)$n).'</w:t></w:r>
        </w:p>
      </w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1500" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xmlEsc($codigo).'</w:t></w:r>
        </w:p>
      </w:tc>
      <w:tc><w:tcPr><w:tcW w:w="4326" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xmlEsc($nombre).'</w:t></w:r>
        </w:p>
      </w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1300" w:type="dxa"/></w:tcPr>
        <w:p>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xmlEsc($unidad).'</w:t></w:r>
        </w:p>
      </w:tc>
      <w:tc><w:tcPr><w:tcW w:w="1300" w:type="dxa"/></w:tcPr>
        <w:p><w:pPr><w:jc w:val="right"/></w:pPr>
          <w:r><w:rPr>'.$f.'</w:rPr><w:t>'.xmlEsc((string)$cantidad).'</w:t></w:r>
        </w:p>
      </w:tc>
    </w:tr>';
}

// Construir TODAS las filas de materiales
$filasXML = '';
foreach ($detalles as $i => $d) {
    $filasXML .= filaXML(
        $i + 1,
        $d['nombre'],
        $d['codigo'] ?? '',
        $d['unidad'],
        $d['cantidad']
    );
}

// Leer el docx temporal como ZIP y modificar document.xml
$tmpFile2 = tempnam(sys_get_temp_dir(), 'carta2_') . '.docx';
$zip = new ZipArchive();
$zip->open($tmpFile1);
$docXml = $zip->getFromName('word/document.xml');
$zip->close();

// La fila placeholder (con las celdas vacías después del setValue '') tiene este patrón:
// Buscar la fila <w:tr> que contiene las celdas de la tabla de materiales (la que
// tiene las celdas vacías y está después de la fila de cabecera azul)
// Usamos regex para encontrar la fila de datos (la que sigue al encabezado)

// Patrón: encontrar la fila de datos placeholder en la tabla de materiales
// La fila tiene 5 celdas con el mismo estilo, después de la fila header (1F3864)
// Buscamos la segunda <w:tr> en la segunda tabla (la tabla de materiales)

// Estrategia: reemplazar la fila con <w:t></w:t> vacíos (placeholder limpio)
// por las filas reales de materiales

// La fila placeholder tiene las celdas vacías con fondo FFFFFF o EBF0FA
$patronFilaVacia = '/<w:tr>\s*<w:tc>[^<]*<w:tcPr>[^<]*<w:tcW w:w="500"[^>]*\/>[^<]*<w:shd[^<]*1F3864[^<]*\/>[^<]*<\/w:tcPr>/';

// Mejor estrategia: buscar la fila que NO tiene fondo 1F3864 en la segunda tabla
// Dividir el XML en las dos tablas y procesar la segunda
$partes = explode('<w:tblStyle w:val="Tablaconcuadrcula"', $docXml);

if (count($partes) >= 2) {
    // La primera tabla es Controlling, la segunda es materiales
    // En la segunda tabla, reemplazar la fila de datos (no la de cabecera)
    // La fila de datos es la que NO tiene fill="1F3864"

    // Encontrar la tabla de materiales completa en el XML
    // y reemplazar su fila de datos con las filas reales
    $xmlNuevo = preg_replace_callback(
        // Busca la segunda <w:tbl> (la de materiales) y dentro reemplaza la fila de datos
        '/(<w:tbl>(?:(?!<w:tbl>).)*?1F3864(?:(?!<w:tbl>).)*?)(<w:tr>(?:(?!1F3864).)*?<\/w:tr>)(\s*<\/w:tbl>)/s',
        function($m) use ($filasXML) {
            return $m[1] . $filasXML . $m[3];
        },
        $docXml
    );

    // Si el regex funcionó, usar el XML nuevo; si no, intentar con un patrón más simple
    if ($xmlNuevo && $xmlNuevo !== $docXml) {
        $docXml = $xmlNuevo;
    } else {
        // Estrategia alternativa: buscar la fila con celdas de ancho 500 sin fondo 1F3864
        $docXml = preg_replace(
            '/<w:tr>\s*<w:tc>\s*<w:tcPr>\s*<w:tcW w:w="500"[^>]*\/>\s*<\/w:tcPr>.*?<\/w:tr>/s',
            $filasXML,
            $docXml,
            1  // Solo la primera ocurrencia (la fila de datos de la tabla de materiales)
        );
    }
}

// Guardar el docx modificado
$zipOut = new ZipArchive();
$zipOut->open($tmpFile2, ZipArchive::CREATE);
$zipIn = new ZipArchive();
$zipIn->open($tmpFile1);
for ($i = 0; $i < $zipIn->numFiles; $i++) {
    $name    = $zipIn->getNameIndex($i);
    $content = $zipIn->getFromIndex($i);
    if ($name === 'word/document.xml') {
        $zipOut->addFromString($name, $docXml);
    } else {
        $zipOut->addFromString($name, $content);
    }
}
$zipIn->close();
$zipOut->close();

// Limpiar archivo temporal intermedio
@unlink($tmpFile1);

audit_log($conn, 'GENERAR_WORD', "Carta pedido REQ ID $id: $numeroCarta");

$filename = 'CartaPedido_' . preg_replace('/[^A-Za-z0-9\-]/', '', $numeroCarta) . '_' . date('Ymd') . '.docx';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile2));
header('Cache-Control: max-age=0');

readfile($tmpFile2);
unlink($tmpFile2);
exit;

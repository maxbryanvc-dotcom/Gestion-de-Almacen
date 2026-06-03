<?php
// ============================================================
// GENERAR CARTA PEDIDO — usa la plantilla oficial CONSORCIO SAR
// Solo reemplaza los marcadores con datos reales
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
    $t  = strtotime($fecha);
    $mm = date('m', $t);
    $yy = date('Y', $t);
    return 'PEDIDO DE MATERIALES ' . $meses[$mm] . ' ' . $yy;
}

// ── Valores para reemplazar ───────────────────────────────────
$fechaDoc     = fechaLarga($req['fecha'] ?? date('Y-m-d'));
$numeroCarta  = $req['codigo_req'] ?? ('REQ-' . str_pad($id, 3, '0', STR_PAD_LEFT));
$asunto       = mesAnio($req['fecha'] ?? date('Y-m-d'));

// ── Usar TemplateProcessor con el archivo real ────────────────
$processor = new TemplateProcessor($templatePath);

// Reemplazar marcadores de texto
$processor->setValue('fecha_larga',  $fechaDoc);
$processor->setValue('numero_carta', $numeroCarta);
$processor->setValue('asunto',       $asunto);

// Clonar la fila de materiales por cada ítem
if (!empty($detalles)) {
    try {
        $processor->cloneRow('mat_nombre', count($detalles));
        foreach ($detalles as $i => $d) {
            $n = $i + 1;
            $processor->setValue("item_num#$n",     $n);
            $processor->setValue("mat_nombre#$n",   $d['nombre']);
            $processor->setValue("mat_codigo#$n",   $d['codigo'] ?? '');
            $processor->setValue("mat_unidad#$n",   $d['unidad']);
            $processor->setValue("mat_cantidad#$n", $d['cantidad']);
        }
    } catch (\Exception $e) {
        // Si falla el cloneRow, reemplazar directamente (un solo material)
        $processor->setValue('item_num',     1);
        $processor->setValue('mat_nombre',   $detalles[0]['nombre'] ?? '');
        $processor->setValue('mat_codigo',   $detalles[0]['codigo'] ?? '');
        $processor->setValue('mat_unidad',   $detalles[0]['unidad'] ?? '');
        $processor->setValue('mat_cantidad', $detalles[0]['cantidad'] ?? '');
    }
} else {
    // Sin materiales — limpiar marcadores
    foreach (['item_num','mat_nombre','mat_codigo','mat_unidad','mat_cantidad'] as $k) {
        $processor->setValue($k, '');
    }
}

// ── Guardar en archivo temporal y enviar ─────────────────────
$tmpFile = tempnam(sys_get_temp_dir(), 'carta_') . '.docx';
$processor->saveAs($tmpFile);

audit_log($conn, 'GENERAR_WORD', "Carta pedido REQ ID $id: $numeroCarta");

$filename = 'CartaPedido_' . preg_replace('/[^A-Za-z0-9\-]/', '', $numeroCarta) . '_' . date('Ymd') . '.docx';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit;

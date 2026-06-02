<?php
// ============================================================
// CATÁLOGO CENTRAL DE VARIABLES DINÁMICAS
// Usadas en plantillas Word, Excel y PDF
// ============================================================

/**
 * Devuelve todos los valores reales para reemplazar variables.
 * $contexto puede incluir: req_id, ot_id, material_id, tecnico_id
 */
function obtenerVariables(mysqli $conn, array $contexto = []): array {

    $vars = [];

    // ── SISTEMA ─────────────────────────────────────────────
    $vars['{{fecha}}']        = date('d/m/Y');
    $vars['{{fecha_larga}}']  = fecha_es(date('Y-m-d'));
    $vars['{{hora}}']         = date('H:i');
    $vars['{{fecha_hora}}']   = date('d/m/Y H:i');
    $vars['{{anio}}']         = date('Y');
    $vars['{{mes}}']          = mes_es(date('m'));
    $vars['{{usuario}}']      = $_SESSION['usuario']         ?? '';
    $vars['{{usuario_nombre}}'] = $_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '';
    $vars['{{empresa}}']      = APP_EMPRESA;
    $vars['{{sistema}}']      = APP_NAME;
    $vars['{{contrato}}']     = 'N° 051 - 2025';
    $vars['{{version}}']      = APP_VERSION;

    // ── REQUERIMIENTO ────────────────────────────────────────
    if (!empty($contexto['req_id'])) {
        $rid  = intval($contexto['req_id']);
        $stmt = $conn->prepare("
            SELECT r.*, COALESCE(t.nombre,'') AS tec_nombre,
                   COALESCE(t.dni,'') AS tec_dni,
                   COALESCE(t.celular,'') AS tec_celular,
                   COALESCE(t.area,'') AS tec_area,
                   COALESCE(t.cargo,'') AS tec_cargo
            FROM requerimientos r
            LEFT JOIN tecnicos t ON t.id = r.tecnico_id
            WHERE r.id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($req) {
            $vars['{{req_id}}']       = $req['id'];
            $vars['{{req_codigo}}']   = $req['codigo_req'] ?? 'REQ-'.$req['id'];
            $vars['{{req_fecha}}']    = $req['fecha'] ? date('d/m/Y', strtotime($req['fecha'])) : '';
            $vars['{{req_fecha_larga}}'] = $req['fecha'] ? fecha_es($req['fecha']) : '';
            $vars['{{req_estado}}']   = $req['estado'] ?? '';
            $vars['{{req_tipo}}']     = $req['tipo_liq'] ?? '';
            $vars['{{req_aprobado_por}}'] = $req['aprobado_por'] ?? '';
            $vars['{{req_observacion}}']  = $req['observacion'] ?? '';

            // Técnico del requerimiento
            $vars['{{tec_nombre}}']  = $req['tec_nombre'];
            $vars['{{tec_dni}}']     = $req['tec_dni'];
            $vars['{{tec_celular}}'] = $req['tec_celular'];
            $vars['{{tec_area}}']    = $req['tec_area'];
            $vars['{{tec_cargo}}']   = $req['tec_cargo'];

            // Materiales del requerimiento (primer material)
            $det = $conn->prepare("
                SELECT m.nombre, m.codigo, m.unidad, d.cantidad
                FROM detalle_requerimiento d
                JOIN materiales m ON m.id = d.material_id
                WHERE d.requerimiento_id = ?
                ORDER BY m.nombre ASC
            ");
            $det->bind_param('i', $rid);
            $det->execute();
            $mats = $det->get_result()->fetch_all(MYSQLI_ASSOC);
            $det->close();

            // Primer material (variables simples)
            if (!empty($mats)) {
                $vars['{{mat_nombre}}']   = $mats[0]['nombre'];
                $vars['{{mat_codigo}}']   = $mats[0]['codigo'] ?? '';
                $vars['{{mat_unidad}}']   = $mats[0]['unidad'];
                $vars['{{mat_cantidad}}'] = $mats[0]['cantidad'];
            }

            // Tabla de materiales (para marcador {{tabla_materiales}})
            $vars['{{total_items}}']  = count($mats);
            $vars['{{lista_materiales}}'] = implode(', ', array_column($mats, 'nombre'));

            // Guardar array completo para tablas clonadas
            $vars['__materiales__'] = $mats;
        }
    }

    // ── ORDEN DE TRABAJO ─────────────────────────────────────
    if (!empty($contexto['ot_id'])) {
        $oid  = intval($contexto['ot_id']);
        $stmt = $conn->prepare("
            SELECT ot.*, t.nombre AS tec_nombre, t.dni AS tec_dni,
                   t.celular AS tec_celular, t.area AS tec_area, t.cargo AS tec_cargo
            FROM ordenes_trabajo ot
            LEFT JOIN tecnicos t ON t.id = ot.tecnico_id
            WHERE ot.id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $oid);
        $stmt->execute();
        $ot = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ot) {
            $tipos = ['IN'=>'Instalación Nueva','CM'=>'Cambio de Medidor',
                      'MJ'=>'Mejora','REUB'=>'Reubicación','REAC'=>'Reactivación'];
            $vars['{{ot_id}}']          = $ot['id'];
            $vars['{{ot_numero}}']      = $ot['numero_ot'];
            $vars['{{ot_tipo}}']        = $ot['tipo'];
            $vars['{{ot_tipo_nombre}}'] = $tipos[$ot['tipo']] ?? $ot['tipo'];
            $vars['{{ot_estado}}']      = $ot['estado'];
            $vars['{{ot_fecha}}']       = $ot['fecha'] ? date('d/m/Y', strtotime($ot['fecha'])) : '';
            $vars['{{ot_serie_medidor}}'] = $ot['serie_medidor'] ?? '';
            $vars['{{ot_observacion}}'] = $ot['observacion'] ?? '';
            $vars['{{tec_nombre}}']     = $ot['tec_nombre'] ?? '';
            $vars['{{tec_dni}}']        = $ot['tec_dni']    ?? '';
            $vars['{{tec_celular}}']    = $ot['tec_celular'] ?? '';
            $vars['{{tec_area}}']       = $ot['tec_area']   ?? '';
            $vars['{{tec_cargo}}']      = $ot['tec_cargo']  ?? '';
        }
    }

    // ── TÉCNICO DIRECTO ──────────────────────────────────────
    if (!empty($contexto['tecnico_id'])) {
        $tid  = intval($contexto['tecnico_id']);
        $stmt = $conn->prepare("SELECT * FROM tecnicos WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $tec = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($tec) {
            $vars['{{tec_nombre}}']  = $tec['nombre'];
            $vars['{{tec_dni}}']     = $tec['dni']     ?? '';
            $vars['{{tec_celular}}'] = $tec['celular'] ?? '';
            $vars['{{tec_area}}']    = $tec['area']    ?? '';
            $vars['{{tec_cargo}}']   = $tec['cargo']   ?? '';
        }
    }

    return $vars;
}

// ── Helpers de fecha en español ──────────────────────────────
function fecha_es(string $fecha): string {
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $t = strtotime($fecha);
    return date('j', $t) . ' de ' . $meses[(int)date('n', $t)] . ' del ' . date('Y', $t);
}

function mes_es(string $num): string {
    $m = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
          '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
          '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
    return $m[$num] ?? '';
}

// ── Catálogo para mostrar en la UI ───────────────────────────
function catalogoVariables(): array {
    return [
        'Sistema' => [
            '{{fecha}}'         => 'Fecha actual (dd/mm/yyyy)',
            '{{fecha_larga}}'   => 'Fecha en letras (7 de Enero del 2026)',
            '{{hora}}'          => 'Hora actual (HH:MM)',
            '{{fecha_hora}}'    => 'Fecha y hora completa',
            '{{mes}}'           => 'Mes actual en letras',
            '{{anio}}'          => 'Año actual',
            '{{usuario}}'       => 'Usuario logueado (login)',
            '{{usuario_nombre}}'=> 'Nombre completo del usuario',
            '{{empresa}}'       => 'Nombre de la empresa',
            '{{contrato}}'      => 'Número de contrato',
        ],
        'Requerimiento' => [
            '{{req_codigo}}'       => 'Código del requerimiento (REQ-...)',
            '{{req_fecha}}'        => 'Fecha del requerimiento',
            '{{req_fecha_larga}}'  => 'Fecha en letras',
            '{{req_estado}}'       => 'Estado (Pendiente/Aprobado/Anulado)',
            '{{req_tipo}}'         => 'Tipo (Instalaciones/Mantenimiento)',
            '{{req_aprobado_por}}' => 'Usuario que aprobó',
            '{{req_observacion}}'  => 'Observación general',
            '{{total_items}}'      => 'Total de ítems del requerimiento',
            '{{lista_materiales}}' => 'Lista de materiales separada por comas',
        ],
        'Material (primer ítem)' => [
            '{{mat_nombre}}'   => 'Nombre del material',
            '{{mat_codigo}}'   => 'Código del material',
            '{{mat_unidad}}'   => 'Unidad de medida',
            '{{mat_cantidad}}' => 'Cantidad solicitada',
        ],
        'Técnico' => [
            '{{tec_nombre}}'  => 'Nombre completo del técnico',
            '{{tec_dni}}'     => 'DNI del técnico',
            '{{tec_celular}}' => 'Celular del técnico',
            '{{tec_area}}'    => 'Área del técnico',
            '{{tec_cargo}}'   => 'Cargo del técnico',
        ],
        'Orden de Trabajo' => [
            '{{ot_numero}}'      => 'Número de OT',
            '{{ot_tipo}}'        => 'Tipo de OT (IN/CM/MJ...)',
            '{{ot_tipo_nombre}}' => 'Tipo en texto (Instalación Nueva...)',
            '{{ot_estado}}'      => 'Estado de la OT',
            '{{ot_fecha}}'       => 'Fecha de la OT',
            '{{ot_serie_medidor}}' => 'Serie del medidor instalado',
            '{{ot_observacion}}' => 'Observación de la OT',
        ],
    ];
}

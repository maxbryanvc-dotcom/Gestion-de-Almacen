<?php
// ============================================================
// MAPEO: Nombre del material en Excel → columna en la plantilla
// Basado en la plantilla oficial TRABAJOS EJECUTADOS
// Columna 0=A, 1=B, etc. (col 4 = tipo OT, 5-25 = materiales)
// ============================================================

define('COL_PERSONAL',      0);   // PERSONAL (técnico)
define('COL_ESTADO',        1);   // ESTADO
define('COL_ITM',           2);   // ITM (solo hojas técnico)
define('COL_OT_BLANK',      3);   // OT (vacío)
define('COL_TIPO_OT',       4);   // ORDEN DE TRABAJO (tipo: IN/CM/MJ...)
define('COL_OBSERVACIONES', 26);  // OBSERVACIONES
define('COL_SERIE',         27);  // Serie medidor
define('COL_FECHA',         28);  // FECHA

// ── Mapeo: nombre columna en Excel → índice ──────────────────
const COLUMNAS_MATERIALES = [
    'MEDIDOR ELECTRONICO 1F 2H'      => 5,
    'MEDIDOR ELECTRONIC 3F 4H'       => 6,
    'MEDIDOR ELECTRONIC 3F 3H'       => 7,
    'CABLE CONCENTRICO D/AL 2X6MM2'  => 8,
    'CABLE CONCENTRICO D/AL 2X16MM2' => 9,
    'CABLE CONCENTRICO D/AL 3X16MM2' => 10,
    'CABLE AL 4X16MM.'               => 11,
    'CAJATOMA POLIMERICA 1F'         => 12,
    'CAJATOMA ELSE I'                => 13,
    'CAJATOMA ELSE II'               => 14,
    'TERMOMAGNETICO 2X16'            => 15,
    'TERMOMAGNETICO 2X50'            => 16,
    'TERMOMAGNETICO 3X63'            => 17,
    'CONECTOR BIPOLAR'               => 18,
    'SEPARADOR DE VIAS'              => 19,
    'TEMPLADOR'                      => 20,
    'TUBO MONOFASICO'                => 21,
    'TUBO TRIFASICO'                 => 22,
    'PRESINTO DE CAJATOMA'           => 23,
    'PRESINTO DE MEDIDOR'            => 24,
    'TUBO CORRUGADO'                 => 25,
];

// ── Hojas consolidadas según estado ─────────────────────────
const HOJA_POR_ESTADO = [
    'Aprobado'   => 'APROBADOS',
    'Ejecutado'  => 'EJECUTADOS',
    'Programado' => 'enviados',
];

/**
 * Encuentra la columna para un material dado su nombre.
 * Hace matching flexible (sin importar mayúsculas/espacios extra).
 */
function buscarColumna(string $nombre): ?int {
    $nombreNorm = strtoupper(trim(preg_replace('/\s+/', ' ', $nombre)));

    foreach (COLUMNAS_MATERIALES as $col => $idx) {
        $colNorm = strtoupper(trim(preg_replace('/\s+/', ' ', $col)));
        if ($colNorm === $nombreNorm) return $idx;

        // Matching parcial — busca si el nombre del material contiene la clave
        if (str_contains($nombreNorm, $colNorm) || str_contains($colNorm, $nombreNorm)) {
            return $idx;
        }
    }

    // Matching por palabras clave
    $keywords = [
        'MEDIDOR'        => ['1F 2H'=>5, '3F 4H'=>6, '3F 3H'=>7],
        'CABLE'          => ['2X6'=>8, '2X16MM2'=>9, '3X16'=>10, '4X16'=>11],
        'CAJATOMA'       => ['POLIM'=>12, 'ELSE I'=>13, 'ELSE II'=>14],
        'TERMOMAGNETICO' => ['16'=>15, '50'=>16, '63'=>17],
        'CONECTOR'       => 18,
        'SEPARADOR'      => 19,
        'TEMPLADOR'      => 20,
        'TUBO'           => ['MONO'=>21, 'TRIF'=>22, 'CORR'=>25],
        'PRESINTO'       => ['CAJAT'=>23, 'MEDI'=>24],
    ];

    foreach ($keywords as $key => $val) {
        if (!str_contains($nombreNorm, $key)) continue;
        if (is_int($val)) return $val;
        foreach ($val as $sub => $idx) {
            if (str_contains($nombreNorm, $sub)) return $idx;
        }
    }

    return null; // material no mapeado → columna extra o ignorar
}

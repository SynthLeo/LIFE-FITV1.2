<?php
// ============================================================
//  POST Api/Cuestionario/GuardarPsicologico.php — PSICOLOGÍA
//  PHQ-8 (ánimo) + GAD-7 (ansiedad) + meta de bienestar.
//
//  ⚠️ Datos sensibles: se guardan, pero nunca salen en ranking,
//  feed, social ni exportaciones.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/instrumentos.php';
require_once __DIR__ . '/../../utils/consentimiento.php';

exigirMetodo('POST');

$uid = (int) $GLOBALS['userId'];
exigirConsentimiento(getDB(), $uid);

try {
    $a = limpiarRespuestas('psicologia', jsonBody());
} catch (InvalidArgumentException $e) {
    salir(422, ['error' => $e->getMessage()]);
}

$ev = evaluarPsicologia($a);

// PHQ/GAD se guardan como número 0-3
$columnas = [];
foreach ($a as $id => $v) {
    $columnas[$id] = (str_starts_with($id, 'phq_') || str_starts_with($id, 'gad_')) && $v !== null ? (int) $v : $v;
}

// Campos viejos calculados (compatibilidad con pantallas y columnas existentes)
$banda4 = fn(int $n, array $vals) => $vals[$n < 5 ? 0 : ($n < 10 ? 1 : ($n < 15 ? 2 : 3))];
$compat = [
    'estado_animo'   => $banda4($ev['phq8'], ['positivo', 'variable', 'bajo', 'muy_bajo']),
    'ansiedad'       => $banda4($ev['gad7'], ['nunca', 'leve', 'moderado', 'intenso']),
    'estres_general' => $banda4(max($ev['phq8'], $ev['gad7']), ['nunca', 'ocasional', 'frecuente', 'constante']),
];

try {
    $db = getDB();
    upsertPorUsuario($db, 'cuestionarios_psicologicos', 'usuario_id', $uid, $columnas + $compat + [
        'phq8_total'          => $ev['phq8'],
        'gad7_total'          => $ev['gad7'],
        'psico_score'         => $ev['score'],
        'nivel_psicologico'   => $ev['nivel'],
        'fecha_actualizacion' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    errorInterno('GuardarPsicologico', $e);
}

salir(200, [
    'ok'                => true,
    'psico_score'       => $ev['score'],
    'nivel_psicologico' => $ev['nivel'],
    'mensaje'           => 'Perfil psicológico guardado correctamente.',
]);

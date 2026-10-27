<?php
// ============================================================
//  POST Api/Cuestionario/GuardarAlimentacion.php — ALIMENTACIÓN
//  12 preguntas del cuestionario validado en adolescentes de la CDMX
//  (Martínez Coronado et al., 2023) + 6 de contexto para el plan.
//
//  plan_semanal es opcional: si no viene, se conserva el que ya
//  estaba guardado (antes se borraba al re-guardar el perfil).
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/instrumentos.php';
require_once __DIR__ . '/../../utils/consentimiento.php';
require_once __DIR__ . '/../../utils/dieta.php';

exigirMetodo('POST');

$uid  = (int) $GLOBALS['userId'];
exigirConsentimiento(getDB(), $uid);
$body = jsonBody();

try {
    $a = limpiarRespuestas('alimentacion', $body);
    $plan = isset($body['plan_semanal']) && $body['plan_semanal'] !== null
        ? json_encode(validarPlanDieta($body['plan_semanal']), JSON_UNESCAPED_UNICODE)
        : null;
} catch (InvalidArgumentException $e) {
    salir(422, ['error' => $e->getMessage()]);
}

$ev = evaluarAlimentacion($a);

// Campos viejos calculados (compatibilidad con columnas y pantallas existentes)
$p  = fn(string $id) => (int) opcionDato('alimentacion', $id, $a);
$fv = ($p('verduras_dias') + $p('frutas_dias')) / 2;
$compat = [
    'verduras_frutas'  => $fv >= 2.5 ? 'diario' : ($fv >= 1.5 ? 'frecuente' : ($fv >= 0.5 ? 'ocasional' : 'casi_nunca')),
    'ultra_procesados' => ['diario', 'frecuente', 'una_vez', 'casi_nunca'][min($p('comida_rapida'), $p('botanas'), $p('refrescos_dias'))],
    'agua'             => ['0_2' => 'menos_4', '3_4' => 'menos_4', '5_6' => '4_6', '7_mas' => '6_8'][$a['agua_vasos'] ?? ''] ?? null,
    'desayuno'         => ['0_1' => 'nunca', '2_3' => 'a_veces', '4_5' => 'casi_siempre', '6_7' => 'siempre'][$a['desayuno_dias'] ?? ''] ?? null,
];

try {
    $db = getDB();
    upsertPorUsuario($db, 'cuestionarios_alimentacion', 'usuario_id', $uid, $a + $compat + [
        'nutricion_score'     => $ev['score'],
        'nivel_nutricion'     => $ev['nivel'],
        'plan_semanal'        => $plan,
        'fecha_actualizacion' => date('Y-m-d H:i:s'),
    ], ['plan_semanal', 'actividad_fisica_alim']);
} catch (Throwable $e) {
    errorInterno('GuardarAlimentacion', $e);
}

salir(200, [
    'ok'              => true,
    'nutricion_score' => $ev['score'],
    'nivel_nutricion' => $ev['nivel'],
    'mensaje'         => 'Perfil nutricional guardado correctamente.',
]);

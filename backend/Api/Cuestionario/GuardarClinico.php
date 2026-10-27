<?php
// ============================================================
//  POST Api/Cuestionario/GuardarClinico.php — cuestionario CLÍNICO
//  Salud autopercibida (OMS), STEPS (tabaco, presión, glucosa,
//  colesterol), AUDIT-C, antecedente FINDRISC, sueño, cartilla.
//
//  El servidor valida y recalcula salud_score, nivel y AUDIT-C
//  (con el corte según sexo y edad guardados del usuario).
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
    $a = limpiarRespuestas('clinico', jsonBody());
} catch (InvalidArgumentException $e) {
    salir(422, ['error' => $e->getMessage()]);
}

try {
    $db   = getDB();
    $base = datosBaseUsuario($db, $uid);
    $ev   = evaluarClinico($a, $base['sexo'], $base['edad']);

    // Campos viejos calculados (compatibilidad)
    $compat = [
        'tabaco'           => ['no' => 'nunca', 'no_diario' => 'ocasional', 'diario' => 'regular'][$a['tabaco_actual'] ?? ''] ?? null,
        'alcohol'          => ['nunca' => 'nunca', 'mensual' => 'social', '2_4_mes' => 'social', '2_3_semana' => 'frecuente', '4_mas_semana' => 'diario'][$a['audit_frecuencia'] ?? ''] ?? null,
        'sueno'            => ['menos_6' => 'menos_5', '6_7' => '5_6', '7_8' => '7_8', '8_10' => '7_8', 'mas_10' => 'mas_9'][$a['sueno_horas'] ?? ''] ?? null,
        'estres_fisico'    => ['muy_buena' => 'excelente', 'buena' => 'bueno', 'regular' => 'regular', 'mala' => 'malo', 'muy_mala' => 'malo'][$a['srh_salud'] ?? ''] ?? null,
        'presion_arterial' => ['nunca' => 'no_se', 'normal' => 'normal', 'alta' => 'alta'][$a['presion_dx'] ?? ''] ?? null,
        'visitas_medico'   => ['ultimo_anio' => 'anual', '1_2_anios' => 'rara_vez', 'mas_2' => 'rara_vez', 'nunca' => 'nunca'][$a['chequeo_ultimo'] ?? ''] ?? null,
    ];

    $db->beginTransaction();

    upsertPorUsuario($db, 'cuestionarios_clinicos', 'usuario_id', $uid, $a + $compat + [
        'audit_c_total'       => $ev['audit_c'],
        'salud_score'         => $ev['score'],
        'nivel_salud'         => $ev['nivel'],
        'fecha_actualizacion' => date('Y-m-d H:i:s'),
    ]);

    // Perfil de salud (lo usa el chat de IA). No toca el consentimiento.
    $horas = ['menos_6' => 5.5, '6_7' => 6.5, '7_8' => 7.5, '8_10' => 9.0, 'mas_10' => 10.5][$a['sueno_horas'] ?? ''] ?? null;
    upsertPorUsuario($db, 'usuario_perfil_salud', 'id_usuario', $uid, [
        'consume_alcohol' => $ev['audit_c'] > 0 ? 1 : 0,
        'consume_tabaco'  => (($a['tabaco_actual'] ?? 'no') !== 'no' || ($a['vapeo_actual'] ?? 'no') !== 'no') ? 1 : 0,
        'duerme_horas'    => $horas,
    ], ['duerme_horas']);

    $db->commit();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    errorInterno('GuardarClinico', $e);
}

salir(200, [
    'ok'          => true,
    'salud_score' => $ev['score'],
    'nivel_salud' => $ev['nivel'],
    'mensaje'     => 'Perfil clínico guardado correctamente.',
]);

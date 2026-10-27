<?php
// ============================================================
//  POST Api/IA/Diagnostico.php — diagnóstico del onboarding
//
//  Lee los 4 cuestionarios ya guardados, arma un RESUMEN
//  (no manda las respuestas crudas) y pide a la IA un JSON
//  con esquema. Si la IA falla, responde un diagnóstico básico
//  calculado aquí, para que el onboarding nunca se quede trabado.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/instrumentos.php';
require_once __DIR__ . '/../../ia/llm.php';
require_once __DIR__ . '/../../ia/prompts.php';

exigirMetodo('POST');

$uid = (int) $GLOBALS['userId'];
$db  = getDB();

$leer = function (string $sql) use ($db, $uid) {
    $st = $db->prepare($sql);
    $st->execute([$uid]);
    return $st->fetch() ?: [];
};
$ej = $leer('SELECT nivel_recomendado, ipaq_categoria, ipaq_met_min, parq_resultado, ipaq_sentado FROM cuestionario_habitos WHERE id_usuario = ?');
$cl = $leer('SELECT nivel_salud, salud_score, presion_dx, glucosa_dx, colesterol_dx, sueno_horas, tabaco_actual, vapeo_actual, chequeo_ultimo, vacunas FROM cuestionarios_clinicos WHERE usuario_id = ?');
$ps = $leer('SELECT nivel_psicologico, metas_bienestar FROM cuestionarios_psicologicos WHERE usuario_id = ?');
$al = $leer('SELECT nivel_nutricion, nutricion_score, objetivo FROM cuestionarios_alimentacion WHERE usuario_id = ?');
$us = $leer('SELECT nombre, peso_kg, altura_cm, fecha_nacimiento FROM usuario WHERE id_usuario = ?');

$imc = (!empty($us['peso_kg']) && !empty($us['altura_cm'])) ? round($us['peso_kg'] / (($us['altura_cm'] / 100) ** 2), 1) : null;
$resumen = [
    'edad'       => edadDesde($us['fecha_nacimiento'] ?? null),
    'imc'        => $imc,
    'ejercicio'  => ['nivel' => $ej['nivel_recomendado'] ?? null, 'actividad_ipaq' => $ej['ipaq_categoria'] ?? null, 'met_min_semana' => $ej['ipaq_met_min'] ?? null, 'par_q' => $ej['parq_resultado'] ?? null, 'horas_sentado' => $ej['ipaq_sentado'] ?? null],
    'clinico'    => ['nivel' => $cl['nivel_salud'] ?? null, 'puntaje' => $cl['salud_score'] ?? null, 'presion' => $cl['presion_dx'] ?? null, 'glucosa' => $cl['glucosa_dx'] ?? null, 'sueno' => $cl['sueno_horas'] ?? null, 'tabaco' => $cl['tabaco_actual'] ?? null, 'vapeo' => $cl['vapeo_actual'] ?? null, 'ultimo_chequeo' => $cl['chequeo_ultimo'] ?? null],
    'nutricion'  => ['nivel' => $al['nivel_nutricion'] ?? null, 'puntaje' => $al['nutricion_score'] ?? null, 'objetivo' => $al['objetivo'] ?? null],
    'psicologia' => ['bienestar' => $ps['nivel_psicologico'] ?? null, 'meta' => $ps['metas_bienestar'] ?? null],
];

// ── Diagnóstico de respaldo (sin IA) ──────────────────────────
function diagnosticoBasico(array $r): array {
    $ejN = ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'][$r['ejercicio']['nivel'] ?? ''] ?? 'Principiante';
    $clN = ['optimo' => 'Óptimo', 'bueno' => 'Bueno'][$r['clinico']['nivel'] ?? ''] ?? 'Atención requerida';
    $nuN = ['optimo' => 'Excelente', 'adecuado' => 'Adecuada'][$r['nutricion']['nivel'] ?? ''] ?? 'Mejorable';
    $psN = ['resiliente' => 'Estable', 'moderado' => 'Moderado'][$r['psicologia']['bienestar'] ?? ''] ?? 'Requiere apoyo';
    $prioridad = $psN === 'Requiere apoyo' ? 'psicologia' : ($clN === 'Atención requerida' ? 'clinico' : ($nuN === 'Mejorable' ? 'nutricion' : 'ejercicio'));
    return [
        'resumen' => 'Ya tenemos tu perfil de salud. Revisa cada módulo para ver tus recomendaciones y empieza por tu prioridad.',
        'modulos' => [
            'ejercicio'  => ['nivel' => $ejN, 'observacion' => 'Tu nivel se calculó con tu actividad de los últimos 7 días (IPAQ).', 'recomendacion' => ($r['ejercicio']['par_q'] ?? '') === 'consultar' ? 'Consulta a un profesional antes de subir la intensidad.' : 'Sigue tu rutina y aumenta la carga poco a poco.'],
            'clinico'    => ['nivel' => $clN, 'observacion' => 'Calculado con tus hábitos y antecedentes de salud.', 'recomendacion' => 'Hazte un chequeo preventivo al año.'],
            'nutricion'  => ['nivel' => $nuN, 'observacion' => 'Calculado con tus hábitos de alimentación de la semana.', 'recomendacion' => 'Revisa tu plan semanal en el módulo de Nutrición.'],
            'psicologia' => ['nivel' => $psN, 'observacion' => 'Basado en el tamizaje de ánimo y ansiedad de las últimas 2 semanas.', 'recomendacion' => $psN === 'Requiere apoyo' ? 'Te recomendamos agendar una cita en el módulo de Psicología.' : 'Practica alguna técnica de bienestar esta semana.'],
        ],
        'prioridad'        => $prioridad,
        'frase_motivadora' => 'Cada pequeño paso cuenta. ¡Vamos!',
        'generado_por'     => 'reglas',
    ];
}

$diag = null;
if (llmPermitido($uid, 'diagnostico')) {
    $r = llmChat(promptDiagnostico($resumen), [
        'temperature'   => 0.4,
        'max_tokens'    => 900,
        'timeout'       => 150,
        'json_schema'   => esquemaDiagnostico(),
        'nombre_schema' => 'diagnostico',
    ]);
    $j = $r['ok'] ? ($r['json'] ?? llmExtraerJSON($r['content'])) : null;
    $base = diagnosticoBasico($resumen);
    if (is_array($j) && isset($j['modulos']) && is_array($j['modulos'])) {
        // Se valida campo por campo; lo que no cumpla se toma del diagnóstico básico
        $diag = $base;
        $diag['generado_por'] = 'ia';
        if (is_string($j['resumen'] ?? null))          $diag['resumen'] = mb_substr(strip_tags($j['resumen']), 0, 500);
        if (is_string($j['frase_motivadora'] ?? null)) $diag['frase_motivadora'] = mb_substr(strip_tags($j['frase_motivadora']), 0, 150);
        if (in_array($j['prioridad'] ?? null, ['ejercicio', 'clinico', 'nutricion', 'psicologia'], true)) $diag['prioridad'] = $j['prioridad'];
        foreach (['observacion', 'recomendacion'] as $campo) {
            foreach ($diag['modulos'] as $m => $_) {
                if (is_string($j['modulos'][$m][$campo] ?? null)) $diag['modulos'][$m][$campo] = mb_substr(strip_tags($j['modulos'][$m][$campo]), 0, 300);
            }
        }
        // Los NIVELES salen de los instrumentos, no del modelo
        // Seguridad: si el tamizaje marcó apoyo, la IA no puede bajarle la prioridad
        if ($base['modulos']['psicologia']['nivel'] === 'Requiere apoyo') $diag['prioridad'] = 'psicologia';
    } else {
        error_log('[Diagnostico] IA sin JSON válido: ' . ($r['error'] ?? 'formato'));
    }
}

salir(200, ['ok' => true, 'diagnostico' => $diag ?? diagnosticoBasico($resumen)]);

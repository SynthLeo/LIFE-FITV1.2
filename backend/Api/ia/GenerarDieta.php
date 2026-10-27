<?php
// ============================================================
//  POST Api/IA/GenerarDieta.php — plan semanal de comidas con IA
//
//  Body (todo opcional):
//   { respuestas: {…respuestas del cuestionario de alimentación…},
//     guardar: true }
//  - Sin "respuestas": usa el perfil guardado del usuario.
//  - Con "guardar": actualiza solo plan_semanal (si el perfil ya existe).
//
//  El prompt se arma en el servidor, el modelo responde con JSON
//  según un esquema, y el plan se valida antes de devolverlo.
//  Calorías: Mifflin-St Jeor × actividad, nunca menos de 1,400.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/instrumentos.php';
require_once __DIR__ . '/../../utils/dieta.php';
require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../ia/llm.php';
require_once __DIR__ . '/../../ia/prompts.php';

exigirMetodo('POST');

$uid  = (int) $GLOBALS['userId'];
$body = jsonBody();
$db   = getDB();

// ── 1. Contexto: respuestas recién contestadas o perfil guardado ──
try {
    if (!empty($body['respuestas']) && is_array($body['respuestas'])) {
        $ctx = limpiarRespuestas('alimentacion', $body['respuestas']);
    } else {
        $st = $db->prepare('SELECT * FROM cuestionarios_alimentacion WHERE usuario_id = ? LIMIT 1');
        $st->execute([$uid]);
        $fila = $st->fetch();
        if (!$fila) salir(422, ['error' => 'Primero completa el cuestionario de alimentación.']);
        $ctx = limpiarRespuestas('alimentacion', $fila);
    }
} catch (InvalidArgumentException $e) {
    salir(422, ['error' => $e->getMessage()]);
}

// ── 2. Calorías objetivo (si hay peso y altura) ───────────────
$st = $db->prepare('SELECT peso_kg, altura_cm, fecha_nacimiento, sexo_biologico FROM usuario WHERE id_usuario = ?');
$st->execute([$uid]);
$u = $st->fetch() ?: [];
$kcalObjetivo = null;
if (!empty($u['peso_kg']) && !empty($u['altura_cm'])) {
    $tmb    = calcularTMB((float) $u['peso_kg'], (int) $u['altura_cm'], calcularEdad($u['fecha_nacimiento'] ?? null), $u['sexo_biologico'] ?? null);
    $factor = ['sedentario' => 1.2, 'leve' => 1.375, 'moderado' => 1.55, 'intenso' => 1.725][$ctx['actividad_fisica_alim'] ?? ''] ?? 1.375;
    $ajuste = ['perder_peso' => 0.85, 'ganar_masa' => 1.10][$ctx['objetivo'] ?? ''] ?? 1.0;   // déficit moderado, nunca agresivo
    $edadU  = calcularEdad($u['fecha_nacimiento'] ?? null);
    if ($edadU !== null && $edadU < 18 && $ajuste < 1.0) $ajuste = 1.0;   // menores: sin déficit calórico (siguen creciendo)
    $kcalObjetivo = (int) (round(max(KCAL_MINIMO_SEGURO, min(3200, $tmb * $factor * $ajuste)) / 50) * 50);
}

// ── 3. Hábitos a mejorar (preguntas con 0 o 1 punto) ──────────
$textos = [
    'verduras_dias' => 'más verduras', 'frutas_dias' => 'más fruta', 'lacteos_dias' => 'lácteos naturales',
    'agua_vasos' => 'más agua natural', 'carnes_procesadas' => 'menos embutidos', 'comida_rapida' => 'menos comida rápida',
    'dulces' => 'menos dulces', 'pan_dulce' => 'menos pan dulce', 'botanas' => 'menos botanas',
    'refrescos_dias' => 'menos refresco', 'desayuno_dias' => 'desayunar diario',
];
$habitos = [];
foreach ($textos as $id => $t) {
    if (($ctx[$id] ?? null) !== null && (int) opcionDato('alimentacion', $id, $ctx) <= 1) $habitos[] = $t;
}

// ── 4. IA ─────────────────────────────────────────────────────
if (!llmPermitido($uid, 'dieta')) salir(429, ['error' => 'Espera un minuto antes de generar otro plan.']);

$menorDeEdad = isset($edadU) && $edadU !== null && $edadU < 18;
if ($menorDeEdad && ($ctx['objetivo'] ?? '') === 'perder_peso') $ctx['objetivo'] = 'mejorar_salud';
$mensajes = promptDieta($ctx + ['kcal_objetivo' => $kcalObjetivo, 'habitos' => implode(', ', array_slice($habitos, 0, 5))]);
$plan  = null;
$ultimoError = '';
for ($intento = 1; $intento <= 2 && $plan === null; $intento++) {
    $r = llmChat($mensajes, [
        'temperature'   => 0.4,
        'max_tokens'    => 3000,
        'timeout'       => 240,
        'json_schema'   => esquemaPlanDieta(),
        'nombre_schema' => 'plan_semanal',
    ]);
    if (!$r['ok']) { $ultimoError = $r['error']; break; }   // si LM Studio no responde, no tiene caso reintentar
    try {
        $plan = validarPlanDieta($r['json'] ?? llmExtraerJSON($r['content']));
    } catch (InvalidArgumentException $e) {
        $ultimoError = $e->getMessage();
        error_log("[GenerarDieta] intento $intento inválido: $ultimoError");
    }
}
if ($plan === null) {
    error_log('[GenerarDieta] ' . $ultimoError);
    salir(503, ['error' => 'No se pudo generar el plan. Revisa que LM Studio esté corriendo e intenta de nuevo.']);
}

// ── 5. Guardar (solo plan_semanal) ────────────────────────────
$guardado = false;
if (!empty($body['guardar'])) {
    try {
        $st = $db->prepare('UPDATE cuestionarios_alimentacion SET plan_semanal = ?, fecha_actualizacion = NOW() WHERE usuario_id = ?');
        $st->execute([json_encode($plan, JSON_UNESCAPED_UNICODE), $uid]);
        $guardado = $st->rowCount() > 0;
    } catch (Throwable $e) {
        errorInterno('GenerarDieta', $e);
    }
}

salir(200, ['ok' => true, 'plan' => $plan, 'guardado' => $guardado, 'kcal_objetivo' => $kcalObjetivo]);

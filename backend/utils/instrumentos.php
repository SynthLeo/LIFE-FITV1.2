<?php
// ============================================================
//  utils/instrumentos.php
//  Validación y calificación de los cuestionarios EN EL SERVIDOR.
//
//  Las opciones válidas y sus puntos se leen de
//  Configuracion/instrumentos.json, que se genera desde el
//  frontend (scripts/exportar-instrumentos.mjs), así PHP y JS
//  usan exactamente las mismas preguntas.
//
//  Las reglas de calificación replican src/modulos/cuestionarios/
//  instrumentos.js (PAR-Q+, IPAQ, AUDIT-C, PHQ-8, GAD-7, CDMX 2023).
//  Si cambias una, cambia la otra (hay una prueba que las compara).
// ============================================================

/** Preguntas y opciones válidas por módulo. */
function instrumentos(): array {
    static $data = null;
    if ($data === null) {
        $raw  = file_get_contents(__DIR__ . '/../Configuracion/instrumentos.json');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
    return $data;
}

/**
 * Preguntas que solo aparecen según otra respuesta: id => [pregunta_de_la_que_depende, valor_que_la_oculta].
 * Debe coincidir con los showIf de instrumentos.js.
 */
const PREGUNTAS_CONDICIONALES = [
    'ipaq_vig_min'    => ['ipaq_vig_dias', '0'],
    'ipaq_mod_min'    => ['ipaq_mod_dias', '0'],
    'ipaq_cam_min'    => ['ipaq_cam_dias', '0'],
    'audit_cantidad'  => ['audit_frecuencia', 'nunca'],
    'audit_atracon'   => ['audit_frecuencia', 'nunca'],
    'refrescos_vasos' => ['refrescos_dias', 'ninguno'],
];

function preguntaAplica(string $id, array $a): bool {
    if (!isset(PREGUNTAS_CONDICIONALES[$id])) return true;
    [$padre, $oculta] = PREGUNTAS_CONDICIONALES[$id];
    return isset($a[$padre]) && $a[$padre] !== '' && (string) $a[$padre] !== $oculta;
}

/**
 * Toma el body crudo y devuelve solo las respuestas válidas del módulo:
 *  - campos desconocidos: se ignoran
 *  - pregunta que no aplica o sin contestar: null
 *  - valor que no está entre las opciones: lanza InvalidArgumentException (422)
 */
function limpiarRespuestas(string $modulo, array $body): array {
    $preguntas = instrumentos()[$modulo] ?? throw new InvalidArgumentException("Módulo desconocido: $modulo");
    $limpias = [];

    // Primero los valores crudos válidos (necesarios para saber qué aplica)
    foreach ($preguntas as $id => $p) {
        $v = $body[$id] ?? null;
        if ($v === null || $v === '') { $limpias[$id] = null; continue; }
        $v = (string) $v;
        if (!array_key_exists($v, $p['opciones'])) {
            throw new InvalidArgumentException("Respuesta no válida en '$id'.");
        }
        $limpias[$id] = $v;
    }
    // Luego se anulan las que no aplican
    foreach ($limpias as $id => $v) {
        if (!preguntaAplica($id, $limpias)) $limpias[$id] = null;
    }
    return $limpias;
}

/** Puntos (pts) o valor (v) de la opción elegida. */
function opcionDato(string $modulo, string $id, array $a, string $campo = 'pts', $def = 0) {
    $v = $a[$id] ?? null;
    if ($v === null) return $def;
    return instrumentos()[$modulo][$id]['opciones'][$v][$campo] ?? $def;
}

// ─────────────────────────── EJERCICIO (PAR-Q+ + IPAQ) ───────────────────────────

function evaluarEjercicio(array $a): array {
    $dias = fn(string $id) => (int) opcionDato('ejercicio', $id, $a, 'v', 0);
    $mins = fn(string $diasId, string $minId) => $dias($diasId) > 0 ? (int) opcionDato('ejercicio', $minId, $a, 'v', 0) : 0;

    $vigD = $dias('ipaq_vig_dias'); $vigM = $mins('ipaq_vig_dias', 'ipaq_vig_min');
    $modD = $dias('ipaq_mod_dias'); $modM = $mins('ipaq_mod_dias', 'ipaq_mod_min');
    $camD = $dias('ipaq_cam_dias'); $camM = $mins('ipaq_cam_dias', 'ipaq_cam_min');

    $metVig = 8.0 * $vigM * $vigD;
    $metMod = 4.0 * $modM * $modD;
    $metCam = 3.3 * $camM * $camD;
    $met    = (int) round($metVig + $metMod + $metCam);
    $diasTotales = min(7, $vigD + $modD + $camD);

    $categoria = 'bajo';
    if (($vigD >= 3 && $metVig >= 1500) || ($diasTotales >= 7 && $met >= 3000)) {
        $categoria = 'alto';
    } elseif (
        ($vigD >= 3 && $vigM >= 20) ||
        ((($modM >= 30) ? $modD : 0) + (($camM >= 30) ? $camD : 0)) >= 5 ||
        ($diasTotales >= 5 && $met >= 600)
    ) {
        $categoria = 'moderado';
    }

    $parqSi = [];
    foreach (array_keys(instrumentos()['ejercicio']) as $id) {
        if (str_starts_with($id, 'parq_') && ($a[$id] ?? null) === 'si') $parqSi[] = $id;
    }

    $nivel = ['bajo' => 'principiante', 'moderado' => 'intermedio', 'alto' => 'avanzado'][$categoria];
    if ($parqSi) $nivel = 'principiante';

    return [
        'met'            => $met,
        'categoria'      => $categoria,
        'score'          => min(100, (int) round($met / 30)),
        'nivel'          => $nivel,
        'parq_si'        => $parqSi,
        'parq_resultado' => $parqSi ? 'consultar' : 'apto',
        'minutos_semana' => $vigM * $vigD + $modM * $modD + $camM * $camD,
        'dias_max'       => max($vigD, $modD),
    ];
}

// ─────────────────────────── CLÍNICO (OMS STEPS, AUDIT-C, FINDRISC) ───────────────────────────

/** AUDIT-C: 4+ hombres, 3+ mujeres o sin dato; en menores de 18 cualquier consumo. */
function corteAuditC(?string $sexo, ?int $edad): int {
    if ($edad !== null && $edad < 18) return 1;
    return $sexo === 'masculino' ? 4 : 3;
}

function evaluarClinico(array $a, ?string $sexo = null, ?int $edad = null): array {
    $p = fn(string $id) => (int) opcionDato('clinico', $id, $a);
    $toma = ($a['audit_frecuencia'] ?? null) !== null && $a['audit_frecuencia'] !== 'nunca';

    $auditC   = $toma ? $p('audit_frecuencia') + $p('audit_cantidad') + $p('audit_atracon') : 0;
    $corte    = corteAuditC($sexo, $edad);
    $positivo = $auditC >= $corte;
    $ptsAlc   = $auditC === 0 ? 15 : ($positivo ? 0 : 8);

    $score = min(100,
        $p('srh_salud') + $p('tabaco_actual') + $p('vapeo_actual') + $ptsAlc +
        $p('presion_dx') + $p('glucosa_dx') + $p('colesterol_dx') + $p('sueno_horas') + $p('vacunas')
    );
    $nivel = $score < 40 ? 'critico' : ($score < 60 ? 'moderado' : ($score < 80 ? 'bueno' : 'optimo'));

    return [
        'score'          => $score,
        'nivel'          => $nivel,
        'audit_c'        => $auditC,
        'audit_positivo' => $positivo,
        'findrisc'       => (int) opcionDato('clinico', 'antecedente_diabetes', $a, 'findrisc', 0),
    ];
}

// ─────────────────────────── PSICOLOGÍA (PHQ-8 + GAD-7) ───────────────────────────

function evaluarPsicologia(array $a): array {
    $phq8 = 0; $gad7 = 0;
    foreach (array_keys(instrumentos()['psicologia']) as $id) {
        if (str_starts_with($id, 'phq_')) $phq8 += (int) opcionDato('psicologia', $id, $a);
        if (str_starts_with($id, 'gad_')) $gad7 += (int) opcionDato('psicologia', $id, $a);
    }
    $peor  = max($phq8, $gad7);
    $score = (int) round(100 - (($phq8 + $gad7) / 45) * 100);
    $nivel = $peor >= 15 ? 'critico' : ($peor >= 10 ? 'vulnerable' : ($peor >= 5 ? 'moderado' : 'resiliente'));

    return ['phq8' => $phq8, 'gad7' => $gad7, 'score' => $score, 'nivel' => $nivel];
}

// ─────────────────────────── ALIMENTACIÓN (CDMX 2023) ───────────────────────────

function evaluarAlimentacion(array $a): array {
    $puntos = 0; $max = 0;
    foreach (instrumentos()['alimentacion'] as $id => $p) {
        $califica = false;
        foreach ($p['opciones'] as $o) { if (isset($o['pts'])) { $califica = true; break; } }
        if (!$califica) continue;
        $max += 3;
        if ($id === 'refrescos_vasos' && !preguntaAplica($id, $a)) { $puntos += 3; continue; }
        $puntos += (int) opcionDato('alimentacion', $id, $a);
    }
    $pct   = $max ? $puntos / $max : 0;
    $nivel = $pct < 0.5 ? 'deficiente' : ($pct < 0.75 ? 'mejorable' : ($pct < 0.9 ? 'adecuado' : 'optimo'));
    return ['puntos' => $puntos, 'max' => $max, 'score' => (int) round($pct * 100), 'nivel' => $nivel];
}

// ─────────────────────────── utilidades ───────────────────────────

/** Edad en años a partir de una fecha AAAA-MM-DD (null si no hay). */
function edadDesde(?string $fecha): ?int {
    if (!$fecha) return null;
    try { return (new DateTime($fecha))->diff(new DateTime('today'))->y; }
    catch (Exception) { return null; }
}

/** Fila mínima del usuario para calificar (sexo y edad). */
function datosBaseUsuario(PDO $db, int $uid): array {
    $st = $db->prepare('SELECT sexo_biologico, fecha_nacimiento FROM usuario WHERE id_usuario = ?');
    $st->execute([$uid]);
    $u = $st->fetch() ?: [];
    return ['sexo' => $u['sexo_biologico'] ?? null, 'edad' => edadDesde($u['fecha_nacimiento'] ?? null)];
}

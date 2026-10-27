<?php
// ============================================================
//  POST Api/Cuestionario/Guardar.php — cuestionario de EJERCICIO
//  PAR-Q+ 2025 (7) + IPAQ versión corta (7) + preferencias (4) + biometría
//
//  El servidor valida cada respuesta contra las opciones permitidas
//  y recalcula MET-min, categoría IPAQ y nivel. Lo que mande el
//  cliente como puntaje o nivel se ignora.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/instrumentos.php';
require_once __DIR__ . '/../../utils/consentimiento.php';

exigirMetodo('POST');

$uid  = (int) $GLOBALS['userId'];
exigirConsentimiento(getDB(), $uid);
$body = jsonBody();

try {
    $a = limpiarRespuestas('ejercicio', $body);
} catch (InvalidArgumentException $e) {
    salir(422, ['error' => $e->getMessage()]);
}

$ev = evaluarEjercicio($a);

// ── Biometría (opcional: solo viene en el onboarding) ────────
$sexo   = in_array($body['sexo'] ?? null, ['masculino', 'femenino', 'otro'], true) ? $body['sexo'] : null;
$peso   = isset($body['peso'])   && is_numeric($body['peso'])   ? round((float) $body['peso'], 1)   : null;
$altura = isset($body['altura']) && is_numeric($body['altura']) ? round((float) $body['altura'], 1) : null;
if ($peso !== null   && ($peso < 25 || $peso > 300))     salir(422, ['error' => 'Peso fuera de rango (25-300 kg).']);
if ($altura !== null && ($altura < 100 || $altura > 250)) salir(422, ['error' => 'Altura fuera de rango (100-250 cm).']);
$imc = ($peso && $altura) ? round($peso / (($altura / 100) ** 2), 1) : null;

$fechaNac = null;
if (!empty($body['fecha_nacimiento'])) {
    $d = DateTime::createFromFormat('!Y-m-d', (string) $body['fecha_nacimiento']);
    $edad = $d ? $d->diff(new DateTime('today'))->y : null;
    if (!$d || $edad < 12 || $edad > 100) salir(422, ['error' => 'Fecha de nacimiento no válida.']);
    $fechaNac = $d->format('Y-m-d');
}

// ── Campos viejos, calculados aquí (compatibilidad con columnas existentes) ──
$min = $ev['minutos_semana'];
$compat = [
    'minutos_semana'    => $min < 75 ? 'menos_75' : ($min < 150 ? '75_150' : ($min <= 300 ? '150_300' : 'mas_300')),
    'frecuencia_semana' => $ev['dias_max'] === 0 ? 'nunca' : ($ev['dias_max'] <= 2 ? '1_2' : ($ev['dias_max'] <= 4 ? '3_4' : '5_mas')),
    'horas_sedentario'  => ['menos_4' => 'menos_4', '4_6' => '4_6', '7_9' => '6_8', '10_mas' => 'mas_8'][$a['ipaq_sentado'] ?? ''] ?? null,
    'condicion_salud'   => $ev['parq_si'] ? 'moderada' : 'ninguna',
    'condicion_fisica'  => $ev['categoria'] === 'alto' ? 'muy_buena' : ($ev['categoria'] === 'moderado' ? 'buena' : ($ev['met'] > 0 ? 'regular' : 'mala')),
];

// equipo del cuestionario → columna usuario.equipo_disponible (como la usa GenerarRutina)
$equipoUsuario = [
    'gimnasio_completo' => 'gimnasio_completo',
    'mancuernas'        => 'mancuernas',
    'peso_corporal'     => 'ninguno',
    'bandas'            => 'bandas_elasticas',
][$a['equipo'] ?? ''] ?? null;

try {
    $db = getDB();
    $db->beginTransaction();

    upsertPorUsuario($db, 'cuestionario_habitos', 'id_usuario', $uid, $a + $compat + [
        'parq_resultado'    => $ev['parq_resultado'],
        'ipaq_met_min'      => $ev['met'],
        'ipaq_categoria'    => $ev['categoria'],
        'sexo'              => $sexo,
        'peso'              => $peso,
        'altura'            => $altura,
        'imc'               => $imc,
        'fitness_score'     => $ev['score'],
        'nivel_recomendado' => $ev['nivel'],
    ], ['sexo', 'peso', 'altura', 'imc']);   // si no vienen (cuestionario suelto) se conservan

    // Perfil principal. onboarding_completo se marca en Auth/CompletarOnboarding.php
    $db->prepare('
        UPDATE usuario SET
            nivel_entrenamiento   = :nivel,
            objetivo              = COALESCE(:objetivo, objetivo),
            equipo_disponible     = COALESCE(:equipo, equipo_disponible),
            fitness_score         = :score,
            sexo_biologico        = COALESCE(:sexo, sexo_biologico),
            peso_kg               = COALESCE(:peso, peso_kg),
            altura_cm             = COALESCE(:altura, altura_cm),
            fecha_nacimiento      = COALESCE(:fnac, fecha_nacimiento),
            cuestionario_completo = 1
        WHERE id_usuario = :uid
    ')->execute([
        ':nivel'    => $ev['nivel'],
        ':objetivo' => $a['objetivo_ejercicio'],          // fuerza | masa_muscular | perdida_grasa | resistencia (ENUM de usuario)
        ':equipo'   => $equipoUsuario,
        ':score'    => $ev['score'],
        ':sexo'     => in_array($sexo, ['masculino', 'femenino'], true) ? $sexo : null,   // el ENUM no tiene "otro"
        ':peso'     => $peso,
        ':altura'   => $altura !== null ? (int) round($altura) : null,
        ':fnac'     => $fechaNac,
        ':uid'      => $uid,
    ]);

    $db->commit();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    errorInterno('Cuestionario/Guardar', $e);
}

salir(200, [
    'ok'                => true,
    'nivel'             => $ev['nivel'],
    'nivel_recomendado' => $ev['nivel'],
    'fitness_score'     => $ev['score'],
    'ipaq_met_min'      => $ev['met'],
    'ipaq_categoria'    => $ev['categoria'],
    'parq_resultado'    => $ev['parq_resultado'],
]);

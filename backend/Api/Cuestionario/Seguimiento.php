<?php
// ============================================================
//  Api/Cuestionario/Seguimiento.php — PHQ-8 + GAD-7 cada 2 semanas (M3)
//  GET  → { mediciones:[{fecha, phq8, gad7}], toca, dias_para_siguiente }
//         (la primera medición es la del onboarding)
//  POST { respuestas: { phq_interes: 0-3, …, gad_miedo: 0-3 } }
//       → guarda una medición NUEVA y dice si empeoró.
//  Datos sensibles: solo los ve el propio usuario.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/consentimiento.php';

const SEG_PHQ  = ['phq_interes', 'phq_animo', 'phq_sueno', 'phq_energia', 'phq_apetito', 'phq_autoestima', 'phq_concentracion', 'phq_movimiento'];
const SEG_GAD  = ['gad_nervios', 'gad_control', 'gad_preocupacion', 'gad_relajarse', 'gad_inquietud', 'gad_irritable', 'gad_miedo'];
const SEG_DIAS = 14;
const SEG_CAMBIO_IMPORTANTE = 5;   // 5 puntos = cambio clínicamente relevante en PHQ/GAD

$uid = (int) $GLOBALS['userId'];

function medicionesDe(PDO $db, int $uid): array {
    $base = $db->prepare('SELECT fecha_registro AS fecha, phq8_total AS phq8, gad7_total AS gad7
                          FROM cuestionarios_psicologicos WHERE usuario_id = ? AND phq8_total IS NOT NULL');
    $base->execute([$uid]);
    $seg = $db->prepare('SELECT created_at AS fecha, phq8_total AS phq8, gad7_total AS gad7
                         FROM seguimiento_animo WHERE id_usuario = ? ORDER BY created_at');
    $seg->execute([$uid]);
    $todas = array_merge($base->fetchAll(), $seg->fetchAll());
    return array_map(fn($m) => ['fecha' => $m['fecha'], 'phq8' => (int) $m['phq8'], 'gad7' => (int) $m['gad7']], $todas);
}

function estadoSeguimiento(array $mediciones): array {
    if (!$mediciones) return ['toca' => false, 'dias_para_siguiente' => null];
    $ultima = new DateTime(end($mediciones)['fecha']);
    $dias   = (int) $ultima->diff(new DateTime())->days;
    return ['toca' => $dias >= SEG_DIAS, 'dias_para_siguiente' => max(0, SEG_DIAS - $dias)];
}

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $m = medicionesDe($db, $uid);
        salir(200, ['ok' => true, 'mediciones' => $m] + estadoSeguimiento($m));
    }

    exigirMetodo('POST');
    exigirConsentimiento($db, $uid);

    $r = jsonBody()['respuestas'] ?? null;
    if (!is_array($r)) salir(422, ['error' => 'Faltan las respuestas.']);
    $limpias = [];
    foreach (array_merge(SEG_PHQ, SEG_GAD) as $id) {
        $v = $r[$id] ?? null;
        if (!is_numeric($v) || (int) $v != $v || $v < 0 || $v > 3) salir(422, ['error' => "Respuesta inválida o faltante: $id."]);
        $limpias[$id] = (int) $v;
    }
    $phq8 = array_sum(array_intersect_key($limpias, array_flip(SEG_PHQ)));
    $gad7 = array_sum(array_intersect_key($limpias, array_flip(SEG_GAD)));

    $antes = medicionesDe($db, $uid);
    if (!estadoSeguimiento($antes)['toca'] && $antes) {
        salir(429, ['error' => 'Todavía no toca. El seguimiento es cada 2 semanas.'] + estadoSeguimiento($antes));
    }

    $db->prepare('INSERT INTO seguimiento_animo (id_usuario, phq8_total, gad7_total, respuestas) VALUES (?, ?, ?, ?)')
       ->execute([$uid, $phq8, $gad7, json_encode($limpias)]);
} catch (Throwable $e) {
    errorInterno('Seguimiento', $e);
}

$previa  = $antes ? end($antes) : null;
$empeoro = $previa && ($phq8 - $previa['phq8'] >= SEG_CAMBIO_IMPORTANTE || $gad7 - $previa['gad7'] >= SEG_CAMBIO_IMPORTANTE);
$peor    = max($phq8, $gad7);

salir(200, [
    'ok'               => true,
    'phq8'             => $phq8,
    'gad7'             => $gad7,
    'empeoro'          => $empeoro,
    'sugerir_consulta' => $empeoro || $peor >= 10,
    'mostrar_linea'    => $peor >= 15,
]);

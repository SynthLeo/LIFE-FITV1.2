<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

try {
    // Todos los cuestionarios del usuario ordenados por fecha
    $stmt = $db->prepare('
        SELECT id, minutos_semana, frecuencia_semana, tipo_actividad,
               lugar_ejercicio, condicion_fisica, horas_sedentario,
               motivacion, barrera, condicion_salud, nutricion,
               calentamiento, entorno_social, fitness_score,
               nivel_recomendado, fecha_respuesta, updated_at
        FROM cuestionario_habitos
        WHERE id_usuario = ?
        ORDER BY fecha_respuesta DESC
    ');
    $stmt->execute([$userId]);
    $historial = $stmt->fetchAll();

    // Cuestionario más reciente
    $ultimo = $historial[0] ?? null;

    // Calcular días desde el último cuestionario
    $diasDesdeUltimo = null;
    if ($ultimo) {
        $fecha  = new DateTime($ultimo['fecha_respuesta']);
        $hoy    = new DateTime('today');
        $diasDesdeUltimo = (int) $hoy->diff($fecha)->days;
    }

    echo json_encode([
        'ok'               => true,
        'total'            => count($historial),
        'historial'        => $historial,
        'ultimo'           => $ultimo,
        'dias_desde_ultimo' => $diasDesdeUltimo,
        'puede_repetir'    => true, // siempre puede repetirlo
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[Historial] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}
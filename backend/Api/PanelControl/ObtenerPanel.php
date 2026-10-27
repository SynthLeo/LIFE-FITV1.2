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
    $stmt = $db->prepare('
        SELECT nombre, avatar_id, nivel_entrenamiento, objetivo,
               peso_kg, altura_cm, onboarding_completo, sexo_biologico
        FROM usuario WHERE id_usuario = ?
    ');
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['error' => 'usuario no encontrado.']);
        exit;
    }

    if (!(int) $usuario['onboarding_completo']) {
        http_response_code(403);
        echo json_encode(['error' => 'onboarding_pendiente', 'redirect' => '/onboarding']);
        exit;
    }

    // KPIs
    $stmt = $db->prepare('
        SELECT
            COUNT(DISTINCT DATE(created_at)) AS sesiones,
            COALESCE(SUM(CASE WHEN numero_serie = 1 THEN duracion_minutos ELSE 0 END), 0) AS minutos,
            COALESCE(SUM(volumen_total_kg), 0) AS volumen
        FROM registro_entrenamiento WHERE id_usuario = ?
    ');
    $stmt->execute([$userId]);
    $kpis = $stmt->fetch();

    // Racha
    $stmt = $db->prepare('
        SELECT DISTINCT DATE(created_at) AS dia
        FROM registro_entrenamiento WHERE id_usuario = ?
        ORDER BY dia DESC LIMIT 60
    ');
    $stmt->execute([$userId]);
    $dias = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $racha = 0;
    $hoy = new DateTime('today', new DateTimeZone('America/Mexico_City'));
    foreach ($dias as $dia) {
        $d = new DateTime($dia, new DateTimeZone('America/Mexico_City'));
        if ((int) $hoy->diff($d)->days === $racha) { $racha++; $hoy->modify('-1 day'); }
        else break;
    }

    // Última sesión
    $stmt = $db->prepare('
        SELECT DATE(re.created_at) AS fecha,
               COALESCE(re.duracion_minutos, 0) AS duracion_minutos,
               COALESCE(re.volumen_total_kg, 0) AS volumen_total,
               e.nombre AS ejercicio
        FROM registro_entrenamiento re
        LEFT JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id_usuario = ? AND re.numero_serie = 1
        ORDER BY re.created_at DESC LIMIT 1
    ');
    $stmt->execute([$userId]);
    $ultimaSesion = $stmt->fetch() ?: null;

    echo json_encode([
        'ok'      => true,
        'usuario' => $usuario,
        'stats'   => [
            'sesiones' => (int)   $kpis['sesiones'],
            'minutos'  => (int)   $kpis['minutos'],
            'volumen'  => round((float) $kpis['volumen'], 1),
            'racha'    => $racha,
        ],
        'ultima_sesion' => $ultimaSesion,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[Panel] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}

<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$uid  = (int) $GLOBALS['userId'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!isset($body['completados']) || !is_array($body['completados'])) {
    http_response_code(400);
    echo json_encode(['error' => 'El campo completados es requerido.']);
    exit;
}

$diasValidos = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$completados = [];
foreach ($body['completados'] as $dia => $hecho) {
    if (in_array($dia, $diasValidos, true)) {
        $completados[$dia] = (bool) $hecho;
    }
}

try {
    $db    = getDB();
    $lunes = date('Y-m-d', strtotime('monday this week'));

    $stmt = $db->prepare('
        INSERT INTO bienestar_plan_semanal
            (id_usuario, semana_inicio, completados, actualizado_en)
        VALUES
            (:uid, :lunes, :completados, NOW())
        ON DUPLICATE KEY UPDATE
            completados    = VALUES(completados),
            actualizado_en = NOW()
    ');
    $stmt->execute([
        ':uid'         => $uid,
        ':lunes'       => $lunes,
        ':completados' => json_encode($completados),
    ]);

    echo json_encode([
        'ok'               => true,
        'completados'      => $completados,
        'dias_completados' => count(array_filter($completados)),
        'semana'           => $lunes,
    ]);

} catch (PDOException $e) {
    error_log('[GuardarPlanSemanal] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al guardar el plan semanal.']);
}
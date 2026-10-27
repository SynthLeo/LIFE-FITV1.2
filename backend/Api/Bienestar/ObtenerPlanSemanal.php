<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$uid = (int) $GLOBALS['userId'];

try {
    $db    = getDB();
    $lunes = date('Y-m-d', strtotime('monday this week'));

    $stmt = $db->prepare('
        SELECT completados
        FROM bienestar_plan_semanal
        WHERE id_usuario   = :uid
          AND semana_inicio = :lunes
        LIMIT 1
    ');
    $stmt->execute([':uid' => $uid, ':lunes' => $lunes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'         => true,
        'completados' => $row ? (json_decode($row['completados'], true) ?? []) : [],
        'semana'     => $lunes,
    ]);

} catch (PDOException $e) {
    error_log('[ObtenerPlanSemanal] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al obtener el plan semanal.']);
}
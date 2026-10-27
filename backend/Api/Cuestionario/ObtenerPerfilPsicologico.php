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
    $db   = getDB();
    $stmt = $db->prepare('
        SELECT * FROM cuestionarios_psicologicos
        WHERE usuario_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':uid' => (int) $GLOBALS['userId']]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    // Solo el dueño ve sus propias respuestas. Este endpoint nunca se usa en social/ranking.
    if ($row) {
        foreach (['psico_score', 'phq8_total', 'gad7_total'] as $n) {
            if ($row[$n] !== null) $row[$n] = (int) $row[$n];
        }
    }

    echo json_encode([
        'ok'     => true,
        'perfil' => $row ?: null,
    ]);

} catch (\PDOException $e) {
    error_log('[ObtenerPerfilPsicologico] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno.']);
}
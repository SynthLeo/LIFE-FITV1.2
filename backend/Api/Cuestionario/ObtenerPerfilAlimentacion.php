<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}


try {
    $db   = getDB();
    $stmt = $db->prepare('
        SELECT
            nutricion_score,
            nivel_nutricion,
            plan_semanal,
            objetivo,
            frecuencia_comidas,
            proteina,
            presupuesto,
            actividad_fisica_alim,
            alergias, cocina,
            verduras_dias, frutas_dias, lacteos_dias, agua_vasos,
            carnes_procesadas, comida_rapida, dulces, pan_dulce, botanas,
            refrescos_dias, refrescos_vasos, desayuno_dias,
            fecha_actualizacion
        FROM cuestionarios_alimentacion
        WHERE usuario_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':uid' => (int) $GLOBALS['userId']]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['ok' => false, 'perfil' => null]);
        exit;
    }

    // Decodificar plan_semanal de JSON string a array
    $row['plan_semanal'] = !empty($row['plan_semanal'])
        ? json_decode($row['plan_semanal'], true)
        : null;

    $row['nutricion_score'] = (int) $row['nutricion_score'];

    echo json_encode(['ok' => true, 'perfil' => $row]);

} catch (\PDOException $e) {
    error_log('[ObtenerPerfilAlimentacion] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al obtener el perfil nutricional.']);
}
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
        SELECT
            visitas_medico,
            condicion_cronica,
            medicamentos,
            presion_arterial,
            sueno,
            tabaco,
            alcohol,
            sintomas_frecuentes,
            vacunas,
            estres_fisico,
            hidratacion,
            urgencias,
            salud_score,
            nivel_salud,
            srh_salud, tabaco_actual, vapeo_actual,
            audit_frecuencia, audit_cantidad, audit_atracon, audit_c_total,
            presion_dx, glucosa_dx, colesterol_dx, antecedente_diabetes,
            sueno_horas, chequeo_ultimo,
            fecha_actualizacion
        FROM cuestionarios_clinicos
        WHERE usuario_id = :uid
        LIMIT 1
    ');
    $stmt->execute([':uid' => $uid]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'     => true,
        'perfil' => $row ?: null,
    ]);

} catch (\PDOException $e) {
    error_log('[ObtenerPerfilClinico] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al obtener el perfil clínico.']);
}
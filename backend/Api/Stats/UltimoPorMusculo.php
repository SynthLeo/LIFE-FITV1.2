<?php
/**
 * GET /Api/Stats/UltimoPorMusculo.php
 * Devuelve cuántos días han pasado desde el último entreno
 * por cada grupo muscular del usuario autenticado.
 *
 * Response:
 * {
 *   "ok": true,
 *   "data": [
 *     { "grupo_muscular": "Pecho", "dias_desde": 2 },
 *     ...
 *   ]
 * }
 */
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
    // Obtener el último registro por grupo muscular
    // DATEDIFF entre hoy y la última sesión de ese grupo
    $stmt = $db->prepare("
        SELECT
            e.grupo_muscular,
            DATEDIFF(CURDATE(), MAX(DATE(re.created_at))) AS dias_desde
        FROM registro_entrenamiento re
        JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id_usuario = ?
        GROUP BY e.grupo_muscular
        ORDER BY dias_desde ASC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    // Normalizar los nombres de grupo al formato que usa el frontend
    $mapaGrupos = [
        // ejercicio.grupo_muscular  =>  key del frontend (GRUPOS en EstadisticasPage)
        'Pecho'      => 'Pecho',
        'pecho'      => 'Pecho',
        'Espalda'    => 'Espalda',
        'espalda'    => 'Espalda',
        'Hombros'    => 'Hombros',
        'hombros'    => 'Hombros',
        'Hombro'     => 'Hombros',
        'hombro'     => 'Hombros',
        'Bíceps'     => 'Bíceps',
        'Biceps'     => 'Bíceps',
        'biceps'     => 'Bíceps',
        'Tríceps'    => 'Bíceps',  // se agrupa con bíceps/brazo
        'Triceps'    => 'Bíceps',
        'triceps'    => 'Bíceps',
        'Piernas'    => 'Piernas',
        'piernas'    => 'Piernas',
        'Pierna'     => 'Piernas',
        'pierna'     => 'Piernas',
        'Abdomen'    => 'Abdomen',
        'abdomen'    => 'Abdomen',
        'Abdominal'  => 'Abdomen',
        'core'       => 'Abdomen',
        'Core'       => 'Abdomen',
        'Cardio'     => null,       // ignorar cardio
        'cardio'     => null,
        'gluteos'    => 'Piernas',
        'Gluteos'    => 'Piernas',
        'gluteo'     => 'Piernas',
    ];

    // Agrupar: si un grupo tiene varios registros (ej Pecho + pecho),
    // quedarse con el mínimo días_desde (más reciente)
    $resultado = [];
    foreach ($rows as $row) {
        $grupoFront = $mapaGrupos[$row['grupo_muscular']] ?? null;
        if ($grupoFront === null) continue;

        $dias = (int) $row['dias_desde'];
        if (!isset($resultado[$grupoFront]) || $dias < $resultado[$grupoFront]) {
            $resultado[$grupoFront] = $dias;
        }
    }

    // Convertir a array indexado para el frontend
    $data = [];
    foreach ($resultado as $grupo => $dias) {
        $data[] = [
            'grupo_muscular' => $grupo,
            'dias_desde'     => $dias,
        ];
    }

    echo json_encode([
        'ok'   => true,
        'data' => $data,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[UltimoPorMusculo] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}
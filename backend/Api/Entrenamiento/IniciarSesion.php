<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$idrutina = isset($body['id_rutina']) ? (int) $body['id_rutina'] : null;

try {
    // Si viene con rutina, verificar que pertenece al usuario
    if ($idrutina) {
        $stmt = $db->prepare('SELECT id_rutina, nombre FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
        $stmt->execute([$idrutina, $userId]);
        $rutina = $stmt->fetch();
        if (!$rutina) {
            http_response_code(404);
            echo json_encode(['error' => 'rutina no encontrada.']);
            exit;
        }
    }

    // Crear sesión
    $stmt = $db->prepare('
        INSERT INTO sesion (id_usuario, id_rutina, fecha)
        VALUES (?, ?, CURDATE())
    ');
    $stmt->execute([$userId, $idrutina]);
    $idSesion = (int) $db->lastInsertId();

    // Si tiene rutina, traer ejercicios del día
    $ejercicios = [];
    if ($idrutina) {
        // Determinar qué día de la semana es (1=Lunes ... 7=Domingo)
        $diaSemana = (int) date('N');

        $stmt = $db->prepare('
            SELECT re.id, re.id_ejercicio, re.dia_semana, re.orden,
                   re.series_objetivo, re.reps_objetivo, re.peso_sugerido,
                   e.nombre, e.grupo_muscular, e.nivel_dificultad, e.equipo_necesario, e.imagen_url
            FROM rutina_ejercicio re
            JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
            WHERE re.id_rutina = ?
            ORDER BY re.dia_semana, re.orden
        ');
        $stmt->execute([$idrutina]);
        $todos = $stmt->fetchAll();

        // Intentar filtrar por día actual, si no hay usar todos
        $delDia = array_filter($todos, fn($e) => $e['dia_semana'] === $diaSemana);
        $ejercicios = !empty($delDia) ? array_values($delDia) : $todos;
    }

    echo json_encode([
        'ok'         => true,
        'id_sesion'  => $idSesion,
        'ejercicios' => $ejercicios,
        'rutina'     => $rutina ?? null,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[IniciarSesion] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}
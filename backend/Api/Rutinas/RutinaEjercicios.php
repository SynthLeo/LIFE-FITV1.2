<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

$userId = $GLOBALS['userId'];
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET /rutinaejercicios.php?id_rutina=X ──────────────────────────────────
// Devuelve todos los ejercicios de la rutina agrupados por día
if ($method === 'GET') {
    $idrutina = (int) ($_GET['id_rutina'] ?? 0);
    if (!$idrutina) { http_response_code(422); echo json_encode(['error' => 'id_rutina requerido.']); exit; }

    // Verificar que la rutina pertenece al usuario
    $stmt = $db->prepare('SELECT id_rutina, nombre, dias_semana FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
    $stmt->execute([$idrutina, $userId]);
    $rutina = $stmt->fetch();
    if (!$rutina) { http_response_code(404); echo json_encode(['error' => 'rutina no encontrada.']); exit; }

    $stmt = $db->prepare('
        SELECT re.id, re.id_ejercicio, re.dia_semana, re.orden,
               re.series_objetivo, re.reps_objetivo, re.peso_sugerido,
               e.nombre, e.grupo_muscular, e.nivel_dificultad,
               e.equipo_necesario, e.imagen_url
        FROM rutina_ejercicio re
        JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id_rutina = ?
        ORDER BY re.dia_semana ASC, re.orden ASC
    ');
    $stmt->execute([$idrutina]);
    $ejercicios = $stmt->fetchAll();

    // Agrupar por día
    $porDia = [];
    foreach ($ejercicios as $ej) {
        $porDia[$ej['dia_semana']][] = $ej;
    }

    echo json_encode([
        'ok'       => true,
        'rutina'   => $rutina,
        'por_dia'  => $porDia,
        'total'    => count($ejercicios),
    ]);
    exit;
}

// ── POST — Agregar ejercicio a un día ──────────────────────────────────────
if ($method === 'POST') {
    $body         = json_decode(file_get_contents('php://input'), true) ?? [];
    $idrutina     = (int)  ($body['id_rutina']      ?? 0);
    $idejercicio  = (int)  ($body['id_ejercicio']   ?? 0);
    $diaSemana    = (int)  ($body['dia_semana']      ?? 0);  // 1=Lunes … 7=Domingo
    $series       = (int)  ($body['series_objetivo'] ?? 3);
    $reps         = (int)  ($body['reps_objetivo']   ?? 10);
    $pesoSugerido = (float)($body['peso_sugerido']   ?? 0);

    if (!$idrutina || !$idejercicio || $diaSemana < 1 || $diaSemana > 7) {
        http_response_code(422);
        echo json_encode(['error' => 'id_rutina, id_ejercicio y dia_semana (1-7) son requeridos.']);
        exit;
    }

    // Verificar pertenencia
    $stmt = $db->prepare('SELECT id_rutina, dias_semana FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
    $stmt->execute([$idrutina, $userId]);
    $rutina = $stmt->fetch();
    if (!$rutina) { http_response_code(404); echo json_encode(['error' => 'rutina no encontrada.']); exit; }

    // Verificar que el día no supera los días configurados
    if ($diaSemana > $rutina['dias_semana']) {
        http_response_code(422);
        echo json_encode(['error' => "Esta rutina solo tiene {$rutina['dias_semana']} días."]);
        exit;
    }

    // Evitar duplicado en el mismo día
    $stmt = $db->prepare('SELECT id FROM rutina_ejercicio WHERE id_rutina = ? AND id_ejercicio = ? AND dia_semana = ?');
    $stmt->execute([$idrutina, $idejercicio, $diaSemana]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Este ejercicio ya está en ese día.']);
        exit;
    }

    // Calcular orden
    $stmt = $db->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM rutina_ejercicio WHERE id_rutina = ? AND dia_semana = ?');
    $stmt->execute([$idrutina, $diaSemana]);
    $orden = (int) $stmt->fetchColumn();

    $db->prepare('
        INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, dia_semana, orden, series_objetivo, reps_objetivo, peso_sugerido)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ')->execute([$idrutina, $idejercicio, $diaSemana, $orden, $series, $reps, $pesoSugerido]);

    $newId = (int) $db->lastInsertId();

    // Devolver el registro completo con datos del ejercicio
    $stmt = $db->prepare('
        SELECT re.id, re.id_ejercicio, re.dia_semana, re.orden,
               re.series_objetivo, re.reps_objetivo, re.peso_sugerido,
               e.nombre, e.grupo_muscular, e.nivel_dificultad, e.imagen_url
        FROM rutina_ejercicio re
        JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id = ?
    ');
    $stmt->execute([$newId]);
    $nuevo = $stmt->fetch();

    echo json_encode(['ok' => true, 'ejercicio' => $nuevo]);
    exit;
}

// ── PUT — Editar series/reps/peso de un ejercicio en la rutina ────────────
if ($method === 'PUT') {
    $body         = json_decode(file_get_contents('php://input'), true) ?? [];
    $id           = (int)  ($body['id']               ?? 0);
    $series       = (int)  ($body['series_objetivo']  ?? 3);
    $reps         = (int)  ($body['reps_objetivo']    ?? 10);
    $pesoSugerido = (float)($body['peso_sugerido']    ?? 0);

    if (!$id) { http_response_code(422); echo json_encode(['error' => 'id requerido.']); exit; }

    // Verificar pertenencia a través de la rutina
    $stmt = $db->prepare('
        SELECT re.id FROM rutina_ejercicio re
        JOIN rutina r ON r.id_rutina = re.id_rutina
        WHERE re.id = ? AND r.id_usuario = ?
    ');
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error' => 'No encontrado.']); exit; }

    $db->prepare('
        UPDATE rutina_ejercicio SET series_objetivo = ?, reps_objetivo = ?, peso_sugerido = ?
        WHERE id = ?
    ')->execute([$series, $reps, $pesoSugerido, $id]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE — Eliminar ejercicio de un día ──────────────────────────────────
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id'] ?? 0);

    if (!$id) { http_response_code(422); echo json_encode(['error' => 'id requerido.']); exit; }

    $stmt = $db->prepare('
        SELECT re.id FROM rutina_ejercicio re
        JOIN rutina r ON r.id_rutina = re.id_rutina
        WHERE re.id = ? AND r.id_usuario = ?
    ');
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error' => 'No encontrado.']); exit; }

    $db->prepare('DELETE FROM rutina_ejercicio WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);
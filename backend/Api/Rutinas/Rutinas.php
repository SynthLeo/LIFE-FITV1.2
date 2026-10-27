<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

$userId = $GLOBALS['userId'];
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

const OBJETIVOS_VALIDOS = ['fuerza', 'resistencia', 'perdida_peso', 'volumen', 'flexibilidad'];
const NIVELES_VALIDOS   = ['principiante', 'intermedio', 'avanzado'];

if ($method === 'GET') {
    $idrutina = (int) ($_GET['id'] ?? 0);

    if ($idrutina > 0) {
        $stmt = $db->prepare('SELECT * FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
        $stmt->execute([$idrutina, $userId]);
        $rutina = $stmt->fetch();
        if (!$rutina) { http_response_code(404); echo json_encode(['error' => 'rutina no encontrada.']); exit; }
        $rutina['activa']     = (bool) $rutina['activa'];
        $rutina['ejercicios'] = getejercicios($db, $idrutina);
        echo json_encode(['ok' => true, 'rutina' => $rutina]);
        exit;
    }

    $stmt = $db->prepare('
        SELECT r.id_rutina, r.nombre, r.descripcion, r.objetivo, r.nivel,
               r.dias_semana, r.activa, r.created_at, r.updated_at,
               COUNT(DISTINCT s.id_sesion) AS sesiones_completadas
        FROM rutina r
        LEFT JOIN sesion s
            ON s.id_rutina = r.id_rutina
           AND s.id_usuario = r.id_usuario
           AND s.estado = "finalizada"
        WHERE r.id_usuario = ?
        GROUP BY r.id_rutina
        ORDER BY r.activa DESC, r.updated_at DESC
    ');
    $stmt->execute([$userId]);
    $rutinas = $stmt->fetchAll();
    foreach ($rutinas as &$r) {
        $r['activa']     = (bool) $r['activa'];
        $r['ejercicios'] = getejercicios($db, (int) $r['id_rutina']);
    }
    unset($r);
    echo json_encode(['ok' => true, 'rutinas' => $rutinas, 'total' => count($rutinas)]);
    exit;
}

if ($method === 'POST') {
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $objetivo    = trim($body['objetivo']    ?? '');
    $nivel       = trim($body['nivel']       ?? 'principiante');
    $dias        = max(1, min(7, (int) ($body['dias_semana'] ?? 3)));

    $errors = validarrutina($nombre, $objetivo, $nivel);
    if ($errors) { http_response_code(422); echo json_encode(['error' => implode(' ', $errors)]); exit; }

    $db->prepare('INSERT INTO rutina (id_usuario, nombre, descripcion, objetivo, nivel, dias_semana) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$userId, $nombre, $descripcion, $objetivo, $nivel, $dias]);
    $id = (int) $db->lastInsertId();

    $stmt = $db->prepare('SELECT * FROM rutina WHERE id_rutina = ?');
    $stmt->execute([$id]);
    $nueva = $stmt->fetch();
    $nueva['activa'] = (bool) $nueva['activa'];
    $nueva['ejercicios'] = [];
    echo json_encode(['ok' => true, 'id_rutina' => $id, 'rutina' => $nueva]);
    exit;
}

if ($method === 'PUT') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)  ($body['id_rutina'] ?? 0);
    $accion = trim($body['accion']       ?? '');
    if (!$id) { http_response_code(422); echo json_encode(['error' => 'id_rutina requerido.']); exit; }

    $stmt = $db->prepare('SELECT id_rutina, activa FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
    $stmt->execute([$id, $userId]);
    $rutina = $stmt->fetch();
    if (!$rutina) { http_response_code(404); echo json_encode(['error' => 'rutina no encontrada.']); exit; }

    if ($accion === 'toggle') {
        $nueva = $rutina['activa'] ? 0 : 1;
        $db->prepare('UPDATE rutina SET activa = ?, updated_at = NOW() WHERE id_rutina = ?')->execute([$nueva, $id]);
        echo json_encode(['ok' => true, 'activa' => (bool) $nueva]);
        exit;
    }

    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $objetivo    = trim($body['objetivo']    ?? '');
    $nivel       = trim($body['nivel']       ?? '');
    $dias        = max(1, min(7, (int) ($body['dias_semana'] ?? 3)));
    $errors = validarrutina($nombre, $objetivo, $nivel);
    if ($errors) { http_response_code(422); echo json_encode(['error' => implode(' ', $errors)]); exit; }

    $db->prepare('UPDATE rutina SET nombre=?, descripcion=?, objetivo=?, nivel=?, dias_semana=?, updated_at=NOW() WHERE id_rutina=? AND id_usuario=?')
       ->execute([$nombre, $descripcion, $objetivo, $nivel, $dias, $id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int) ($body['id_rutina'] ?? 0);
    if (!$id) { http_response_code(422); echo json_encode(['error' => 'id_rutina requerido.']); exit; }

    $stmt = $db->prepare('SELECT id_rutina FROM rutina WHERE id_rutina = ? AND id_usuario = ?');
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error' => 'rutina no encontrada.']); exit; }

    $db->prepare('DELETE FROM rutina WHERE id_rutina = ? AND id_usuario = ?')->execute([$id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);

function getejercicios(PDO $db, int $idrutina): array {
    $stmt = $db->prepare('
        SELECT re.id, re.id_ejercicio, re.dia_semana, re.orden,
               re.series_objetivo, re.reps_objetivo, re.peso_sugerido,
               e.nombre, e.grupo_muscular, e.nivel_dificultad, e.equipo_necesario, e.imagen_url
        FROM rutina_ejercicio re
        JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id_rutina = ? ORDER BY re.dia_semana ASC, re.orden ASC
    ');
    $stmt->execute([$idrutina]);
    return $stmt->fetchAll();
}

function validarrutina(string $n, string $o, string $nv): array {
    $e = [];
    if ($n === '' || mb_strlen($n) > 80) $e[] = 'Nombre inválido.';
    if (!in_array($o, OBJETIVOS_VALIDOS, true)) $e[] = 'Objetivo inválido.';
    if (!in_array($nv, NIVELES_VALIDOS, true))  $e[] = 'Nivel inválido.';
    return $e;
}
<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

$userId = $GLOBALS['userId'];
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare('
        SELECT e.id_ejercicio, e.nombre, e.descripcion, e.grupo_muscular,
               e.nivel_dificultad, e.equipo_necesario, e.imagen_url,
               e.musculo_antagonista, f.created_at AS guardado_el
        FROM favorito f
        JOIN ejercicio e ON e.id_ejercicio = f.id_ejercicio
        WHERE f.id_usuario = ? ORDER BY f.created_at DESC
    ');
    $stmt->execute([$userId]);
    $favoritos = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'favoritos' => $favoritos, 'total' => count($favoritos)]);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $ejercicioId = (int) ($body['ejercicio_id'] ?? 0);
    if (!$ejercicioId) { http_response_code(422); echo json_encode(['error' => 'ejercicio_id requerido.']); exit; }

    $stmt = $db->prepare('SELECT id_ejercicio FROM ejercicio WHERE id_ejercicio = ?');
    $stmt->execute([$ejercicioId]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error' => 'ejercicio no encontrado.']); exit; }

    $db->prepare('INSERT IGNORE INTO favorito (id_usuario, id_ejercicio) VALUES (?, ?)')->execute([$userId, $ejercicioId]);
    $total = contarFavs($db, $userId);
    echo json_encode(['ok' => true, 'es_favorito' => true, 'total' => $total]);
    exit;
}

if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $ejercicioId = (int) ($body['ejercicio_id'] ?? 0);
    if (!$ejercicioId) { http_response_code(422); echo json_encode(['error' => 'ejercicio_id requerido.']); exit; }

    $db->prepare('DELETE FROM favorito WHERE id_usuario = ? AND id_ejercicio = ?')->execute([$userId, $ejercicioId]);
    echo json_encode(['ok' => true, 'es_favorito' => false, 'total' => contarFavs($db, $userId)]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);

function contarFavs(PDO $db, int $uid): int {
    $s = $db->prepare('SELECT COUNT(*) FROM favorito WHERE id_usuario = ?');
    $s->execute([$uid]);
    return (int) $s->fetchColumn();
}

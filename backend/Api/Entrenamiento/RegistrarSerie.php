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

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$idSesion    = (int) ($body['id_sesion']    ?? 0);
$idejercicio = (int) ($body['id_ejercicio'] ?? 0);
$idrutina    = isset($body['id_rutina']) ? (int) $body['id_rutina'] : null;
$numeroSerie = (int) ($body['numero_serie'] ?? 1);
$repeticiones= (int) ($body['repeticiones'] ?? 0);
$pesoKg      = (float) ($body['peso_kg']   ?? 0);
$duracion    = isset($body['duracion_minutos']) ? (int) $body['duracion_minutos'] : null;
$sensacion   = trim($body['sensacion']     ?? '');
$notas       = trim($body['notas']         ?? '');

// Validaciones
if (!$idSesion || !$idejercicio) {
    http_response_code(422);
    echo json_encode(['error' => 'id_sesion e id_ejercicio son requeridos.']);
    exit;
}

$sensacionesValidas = ['excelente', 'buena', 'regular', 'mala', ''];
if (!in_array($sensacion, $sensacionesValidas, true)) $sensacion = '';

try {
    // Verificar que la sesión pertenece al usuario
    $stmt = $db->prepare('SELECT id_sesion FROM sesion WHERE id_sesion = ? AND id_usuario = ?');
    $stmt->execute([$idSesion, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Sesión no válida.']);
        exit;
    }

    // Calcular volumen de esta serie
    $volumen = $repeticiones > 0 ? round($repeticiones * $pesoKg, 2) : 0;

    // Insertar serie
    $stmt = $db->prepare('
        INSERT INTO registro_entrenamiento
            (id_sesion, id_usuario, id_ejercicio, id_rutina, numero_serie,
             repeticiones, peso_kg, duracion_minutos, notas, volumen_total_kg, sensacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $idSesion, $userId, $idejercicio, $idrutina, $numeroSerie,
        $repeticiones, $pesoKg, $duracion,
        $notas ?: null,
        $volumen,
        $sensacion ?: null,
    ]);
    $idRegistro = (int) $db->lastInsertId();

    // Actualizar volumen acumulado de la sesión
    $db->prepare('
        UPDATE sesion
        SET volumen_total_kg = volumen_total_kg + ?
        WHERE id_sesion = ?
    ')->execute([$volumen, $idSesion]);

    echo json_encode([
        'ok'          => true,
        'id_registro' => $idRegistro,
        'volumen'     => $volumen,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[RegistrarSerie] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}
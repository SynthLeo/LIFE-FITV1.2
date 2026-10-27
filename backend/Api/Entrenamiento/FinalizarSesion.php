<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$idSesion  = (int) ($body['id_sesion']        ?? 0);
$duracion  = (int) ($body['duracion_minutos'] ?? 0);
$sensacion = trim($body['sensacion']          ?? '');
$notas     = trim($body['notas']              ?? '');

if (!$idSesion) {
    http_response_code(422);
    echo json_encode(['error' => 'id_sesion requerido.']);
    exit;
}

$sensacionesValidas = ['muy_mal', 'mal', 'regular', 'bien', 'muy_bien', ''];
if (!in_array($sensacion, $sensacionesValidas, true)) $sensacion = '';

try {
    // Verificar sesión del usuario
    $stmt = $db->prepare('SELECT id_sesion, volumen_total_kg FROM sesion WHERE id_sesion = ? AND id_usuario = ?');
    $stmt->execute([$idSesion, $userId]);
    $sesion = $stmt->fetch();

    if (!$sesion) {
        http_response_code(404);
        echo json_encode(['error' => 'Sesión no encontrada.']);
        exit;
    }

    // Recalcular volumen real desde los registros (más preciso)
    $stmt = $db->prepare('
        SELECT COALESCE(SUM(volumen_total_kg), 0) as volumen_real,
               COUNT(*) as total_series
        FROM registro_entrenamiento
        WHERE id_sesion = ? AND id_usuario = ?
    ');
    $stmt->execute([$idSesion, $userId]);
    $stats = $stmt->fetch();

    $volumenReal  = (float) $stats['volumen_real'];
    $totalSeries  = (int)   $stats['total_series'];

    // Finalizar sesión
    $stmt = $db->prepare('
        UPDATE sesion SET
            duracion_minutos  = ?,
            sensacion         = ?,
            notas             = ?,
            volumen_total_kg  = ?
        WHERE id_sesion = ? AND id_usuario = ?
    ');
    $stmt->execute([
        $duracion > 0 ? $duracion : null,
        $sensacion ?: null,
        $notas ?: null,
        $volumenReal,
        $idSesion,
        $userId,
    ]);

    // Actualizar progreso_usuario del día
    $stmt = $db->prepare('
        INSERT INTO progreso_usuario (id_usuario, fecha, volumen_total_kg, sesiones_completadas)
        VALUES (?, CURDATE(), ?, 1)
        ON DUPLICATE KEY UPDATE
            volumen_total_kg      = volumen_total_kg + VALUES(volumen_total_kg),
            sesiones_completadas  = sesiones_completadas + 1
    ');
    $stmt->execute([$userId, $volumenReal]);

    echo json_encode([
        'ok'           => true,
        'id_sesion'    => $idSesion,
        'duracion'     => $duracion,
        'volumen'      => $volumenReal,
        'total_series' => $totalSeries,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[FinalizarSesion] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}
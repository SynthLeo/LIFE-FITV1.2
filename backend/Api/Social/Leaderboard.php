<?php
/**
 * GET /Social/Leaderboard.php
 * Devuelve el ranking de usuarios ordenado por sesiones completadas.
 * Requiere JWT válido.
 */

require_once __DIR__ . '/../../Configuracion/Conexion.php';
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
$pdo = getDB();

header('Content-Type: application/json; charset=utf-8');

try {
    /**
     * Consulta principal:
     * Trae todos los usuarios con onboarding completo,
     * contando sus sesiones y calculando la racha actual.
     *
     * Ajusta los nombres de tabla/columna según tu esquema real.
     * Esquema asumido:
     *   usuario   (id_usuario, nombre, nivel_entrenamiento, onboarding_completo)
     *   sesion   (id_sesion, id_usuario, fecha_inicio, completada, volumen_total_kg)
     */
   $sql = <<<SQL
    SELECT
        u.id_usuario,
        u.nombre,
        COALESCE(u.nivel_entrenamiento, 'principiante') AS nivel,
        COUNT(CASE WHEN s.estado = 'finalizada' THEN 1 END) AS sesiones,
        COALESCE(SUM(CASE WHEN s.estado = 'finalizada' THEN s.volumen_total_kg ELSE 0 END), 0) AS volumen,
        COALESCE(u.racha_actual, 0) AS racha
    FROM usuario u
    LEFT JOIN sesion s ON s.id_usuario = u.id_usuario
    WHERE u.onboarding_completo = 1
      AND (u.ocultar_ranking = 0 OR u.id_usuario = :yo)
    GROUP BY u.id_usuario, u.nombre, u.nivel_entrenamiento, u.racha_actual
    ORDER BY COUNT(CASE WHEN s.estado = 'finalizada' THEN 1 END) DESC, u.racha_actual DESC
    LIMIT 50
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':yo' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    foreach ($rows as $i => $row) {
        $leaderboard[] = [
            'posicion' => $i + 1,
            'nombre'   => $row['nombre'],
            'nivel'    => $row['nivel'],
            'sesiones' => (int) $row['sesiones'],
            'racha'    => (int) $row['racha'],
            'volumen'  => (int) $row['volumen'],
            'eres_tu'  => (int) $row['id_usuario'] === $userId,
        ];
    }

    echo json_encode(['ok' => true, 'leaderboard' => $leaderboard]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('[Social] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
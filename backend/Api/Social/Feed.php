<?php
/**
 * GET /Social/Feed.php
 * Devuelve el feed de actividad reciente de todos los usuarios.
 * Requiere JWT válido.
 */



require_once __DIR__ . '/../../Configuracion/Conexion.php';
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
$pdo = getDB();

header('Content-Type: application/json; charset=utf-8');

try {
    /**
     * Construimos el feed uniendo tres tipos de eventos:
     *   1. Sesiones completadas  → tipo "sesion"
     *   2. Logros de racha       → tipo "racha"  (cuando racha >= 7, 14, 21, 28 …)
     *   3. rutinas creadas       → tipo "rutina"
     *
     * Esquema asumido:
     *   sesiones  (id_sesion, id_usuario, fecha_inicio, completada,
     *               total_series, volumen_total_kg, nombre_rutina)
     *   rutinas   (id_rutina, id_usuario, nombre, fecha_creacion, generada_ia)
     *   usuarios  (id_usuario, nombre, racha_actual)
     */

    /* ── 1. Sesiones completadas (últimas 30 días) ── */
   $sqlSesiones = <<<SQL
SELECT
    CONCAT('s_', s.id_sesion) AS id,
    u.id_usuario,
    u.nombre AS usuario,
    LEFT(u.nombre, 1) AS avatar,
    'sesion' AS tipo,
    CONCAT(
        'completó sesión',
        CASE WHEN s.nombre_rutina IS NOT NULL AND s.nombre_rutina <> ''
             THEN CONCAT(' de ', s.nombre_rutina) ELSE '' END,
        CASE WHEN s.volumen_total_kg > 0
             THEN CONCAT(' · ', FORMAT(s.volumen_total_kg, 0), ' kg') ELSE '' END
    ) AS texto,
    s.fecha AS fecha,
    (u.id_usuario = :uid) AS eres_tu
FROM sesion s
JOIN usuario u ON u.id_usuario = s.id_usuario
WHERE s.estado = 'finalizada'
  AND (u.ocultar_ranking = 0 OR u.id_usuario = :uid_a)
  AND s.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY s.fecha DESC
LIMIT 30
SQL;

$sqlRachas = <<<SQL
SELECT
    CONCAT('r_', u.id_usuario) AS id,
    u.id_usuario,
    u.nombre AS usuario,
    LEFT(u.nombre, 1) AS avatar,
    'racha' AS tipo,
    CONCAT('alcanzó ', u.racha_actual, ' días de racha 🔥') AS texto,
    NOW() AS fecha,
    (u.id_usuario = :uid2) AS eres_tu
FROM usuario u
WHERE u.racha_actual > 0
  AND (u.ocultar_ranking = 0 OR u.id_usuario = :uid_b)
  AND (u.racha_actual MOD 7) = 0
ORDER BY u.racha_actual DESC
LIMIT 10
SQL;

$sqlrutinas = <<<SQL
SELECT
    CONCAT('rt_', r.id_rutina) AS id,
    u.id_usuario,
    u.nombre AS usuario,
    LEFT(u.nombre, 1) AS avatar,
    'rutina' AS tipo,
    CONCAT(
        CASE WHEN r.generada_ia = 1 THEN 'creó rutina con IA: "' ELSE 'creó rutina: "' END,
        r.nombre,
        '"'
    ) AS texto,
    r.created_at AS fecha,
    (u.id_usuario = :uid3) AS eres_tu
FROM rutina r
JOIN usuario u ON u.id_usuario = r.id_usuario
WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND (u.ocultar_ranking = 0 OR u.id_usuario = :uid_c)
ORDER BY r.created_at DESC
LIMIT 15
SQL;

    /* ── Ejecutar las 3 consultas ── */
    $stmtS = $pdo->prepare($sqlSesiones);
    $stmtS->execute([':uid' => $userId, ':uid_a' => $userId]);
    $sesiones = $stmtS->fetchAll(PDO::FETCH_ASSOC);

    $stmtR = $pdo->prepare($sqlRachas);
    $stmtR->execute([':uid2' => $userId, ':uid_b' => $userId]);
    $rachas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    $stmtRt = $pdo->prepare($sqlrutinas);
    $stmtRt->execute([':uid3' => $userId, ':uid_c' => $userId]);
    $rutinas = $stmtRt->fetchAll(PDO::FETCH_ASSOC);

    /* ── Combinar, ordenar por fecha y formatear tiempos relativos ── */
    $todos = array_merge($sesiones, $rachas, $rutinas);

    usort($todos, fn($a, $b) => strtotime($b['fecha']) - strtotime($a['fecha']));

    $feed = [];
    $now  = time();
    foreach (array_slice($todos, 0, 30) as $item) {
        $diff     = $now - strtotime($item['fecha']);
        $minutos  = (int)($diff / 60);
        $horas    = (int)($diff / 3600);
        $dias     = (int)($diff / 86400);

        if ($minutos < 5)        $hace = 'Hace unos minutos';
        elseif ($horas < 1)      $hace = "Hace {$minutos} min";
        elseif ($horas < 24)     $hace = "Hace {$horas}h";
        elseif ($dias === 1)     $hace = 'Hace 1 día';
        else                     $hace = "Hace {$dias} días";

        $feed[] = [
            'id'      => $item['id'],
            'usuario' => $item['usuario'],
            'avatar'  => strtoupper($item['avatar']),
            'tipo'    => $item['tipo'],
            'texto'   => $item['texto'],
            'hace'    => $hace,
            'eres_tu' => (bool)(int)$item['eres_tu'],
        ];
    }

    echo json_encode(['ok' => true, 'feed' => $feed]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('[Social] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
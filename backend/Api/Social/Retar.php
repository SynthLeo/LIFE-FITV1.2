<?php
/**
 * POST /Social/Retar.php
 * Registra un reto de un usuario hacia otro.
 * Body JSON: { "objetivo": "nombre del usuario" }
 * Requiere JWT válido.
 */



require_once __DIR__ . '/../../Configuracion/Conexion.php';
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

$pdo = getDB(); // ← esto

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

/* ── Leer body ── */
$body    = json_decode(file_get_contents('php://input'), true);
$objetivo = trim($body['objetivo'] ?? '');

if ($objetivo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el campo "objetivo".']);
    exit;
}

try {
    /**
     * Esquema asumido para la tabla de retos:
     *
     * CREATE TABLE IF NOT EXISTS retos (
     *     id_reto       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     *     id_retador    INT UNSIGNED NOT NULL,
     *     id_retado     INT UNSIGNED NOT NULL,
     *     fecha         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
     *     estado        ENUM('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
     *     FOREIGN KEY (id_retador) REFERENCES usuarios(id_usuario),
     *     FOREIGN KEY (id_retado)  REFERENCES usuarios(id_usuario),
     *     UNIQUE KEY no_duplicados (id_retador, id_retado)
     * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
     */

    /* ── Buscar usuario retado por nombre ── */
    $stmtU = $pdo->prepare(
        'SELECT id_usuario FROM usuario WHERE nombre = :nombre AND ocultar_ranking = 0 LIMIT 1'
    );
    $stmtU->execute([':nombre' => $objetivo]);
    $retado = $stmtU->fetch(PDO::FETCH_ASSOC);

    if (!$retado) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Ese usuario no existe o no participa en la comunidad.']);
        exit;
    }

    $idRetado = (int) $retado['id_usuario'];

    if ($idRetado === $userId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No puedes retarte a ti mismo.']);
        exit;
    }

    /* ── Insertar reto (ignorar duplicados) ── */
    $stmtI = $pdo->prepare(<<<SQL
        INSERT IGNORE INTO retos (id_retador, id_retado, fecha, estado)
        VALUES (:retador, :retado, NOW(), 'pendiente')
    SQL);
    $stmtI->execute([
        ':retador' => $userId,
        ':retado'  => $idRetado,
    ]);

    echo json_encode([
        'ok'      => true,
        'mensaje' => "Reto enviado a {$objetivo} 🥊",
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('[Social] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
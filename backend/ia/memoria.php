<?php
// ============================================================
//  ia/memoria.php
//  Guardar y recuperar historial de conversaciones
// ============================================================

require_once __DIR__ . '/../config/database.php';

// Guarda un mensaje en la BD
function guardarMensaje(
    int    $idusuario,
    string $modulo,
    string $rol,
    string $contenido,
    bool   $fueAlerta = false
): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO ia_conversacion
            (id_usuario, modulo, rol, contenido, fue_alerta, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $idusuario,
        $modulo,
        $rol,
        $contenido,
        (int)$fueAlerta,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

// Historial de la sesión actual (últimas 2 horas, máx 20 mensajes)
// Se envía completo a Ollama para que recuerde el hilo
function getHistorialSesion(int $idusuario, string $modulo): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT rol, contenido
        FROM   ia_conversacion
        WHERE  id_usuario = ?
          AND  modulo     = ?
          AND  created_at >= NOW() - INTERVAL 2 HOUR
        ORDER  BY created_at ASC
        LIMIT  20
    ");
    $stmt->execute([$idusuario, $modulo]);
    return $stmt->fetchAll();
}

// Resumen de sesiones anteriores (> 2 horas) para el system prompt
// Solo muestra los últimos 8 mensajes del usuario como bullet points
function getResumenHistorial(int $idusuario, string $modulo): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT contenido
        FROM   ia_conversacion
        WHERE  id_usuario = ?
          AND  modulo     = ?
          AND  rol        = 'user'
          AND  created_at < NOW() - INTERVAL 2 HOUR
        ORDER  BY created_at DESC
        LIMIT  8
    ");
    $stmt->execute([$idusuario, $modulo]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Acortar cada mensaje a 100 caracteres para no saturar el contexto
    return array_map(fn($r) => mb_substr($r, 0, 100) . (mb_strlen($r) > 100 ? '…' : ''), $rows);
}

// Borrar historial de sesión (botón "Nuevo chat")
function limpiarSesion(int $idusuario, string $modulo): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        DELETE FROM ia_conversacion
        WHERE  id_usuario = ?
          AND  modulo     = ?
          AND  created_at >= NOW() - INTERVAL 2 HOUR
    ");
    $stmt->execute([$idusuario, $modulo]);
}

// Últimas N conversaciones completas del usuario (para panel admin o historial)
function getHistorialCompleto(int $idusuario, string $modulo, int $limite = 50): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT rol, contenido, fue_alerta, created_at
        FROM   ia_conversacion
        WHERE  id_usuario = ?
          AND  modulo     = ?
        ORDER  BY created_at ASC
        LIMIT  ?
    ");
    $stmt->execute([$idusuario, $modulo, $limite]);
    return $stmt->fetchAll();
}

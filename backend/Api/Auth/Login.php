<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Tokens.php';
require_once __DIR__ . '/../../Configuracion/Conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Correo y contraseña son obligatorios.']);
    exit;
}

$db   = getDB();
$ip   = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

// ── Límite de intentos: 5 fallos por correo o 20 por IP en 15 minutos ──
const LOGIN_MAX_EMAIL = 5;
const LOGIN_MAX_IP    = 20;
const LOGIN_VENTANA   = 15;   // minutos
try {
    $st = $db->prepare('SELECT
        SUM(email = ?) AS por_email,
        SUM(ip_address = ?) AS por_ip
        FROM login_intento
        WHERE (email = ? OR ip_address = ?) AND created_at >= NOW() - INTERVAL ' . LOGIN_VENTANA . ' MINUTE');
    $st->execute([$email, $ip, $email, $ip]);
    $n = $st->fetch();
    if ((int) $n['por_email'] >= LOGIN_MAX_EMAIL || (int) $n['por_ip'] >= LOGIN_MAX_IP) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiados intentos. Espera ' . LOGIN_VENTANA . ' minutos e inténtalo de nuevo.']);
        exit;
    }
} catch (PDOException $e) {
    // Si aún no se corre la migración 003, el login sigue funcionando sin límite
    error_log('[Login] ' . $e->getMessage());
}

$stmt = $db->prepare(
    'SELECT id_usuario, nombre, password_hash, onboarding_completo FROM usuario WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    try {
        $db->prepare('INSERT INTO login_intento (email, ip_address) VALUES (?, ?)')->execute([mb_substr($email, 0, 150), $ip]);
        if (random_int(1, 50) === 1) $db->exec('DELETE FROM login_intento WHERE created_at < NOW() - INTERVAL 1 DAY');
    } catch (PDOException $e) { error_log('[Login] ' . $e->getMessage()); }
    http_response_code(401);
    echo json_encode(['error' => 'Correo o contraseña incorrectos.']);
    exit;
}

try { $db->prepare('DELETE FROM login_intento WHERE email = ?')->execute([$email]); } catch (PDOException $e) { /* sin migración 003 */ }

$token = emitirTokenUsuario($user);

echo json_encode([
    'ok'                  => true,
    'token'               => $token,
    'nombre'              => $user['nombre'],
    'onboarding_completo' => (int) $user['onboarding_completo'],
]);

<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Tokens.php';
require_once __DIR__ . '/../../Configuracion/Conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$nombre    = trim($body['nombre']    ?? '');
$email     = trim($body['email']     ?? '');
$password  = $body['password']       ?? '';
$password2 = $body['password2']      ?? '';

$errors = [];
if (mb_strlen($nombre) < 3)                        $errors[] = 'El nombre debe tener al menos 3 caracteres.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Correo electrónico no válido.';
if (strlen($password) < 8)                         $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
if ($password !== $password2)                      $errors[] = 'Las contraseñas no coinciden.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

$db   = getDB();

// ── Límite: 5 cuentas nuevas por IP cada hora (usa la tabla de la migración 003) ──
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
try {
    $st = $db->prepare("SELECT COUNT(*) FROM login_intento WHERE email = '#registro' AND ip_address = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
    $st->execute([$ip]);
    if ((int) $st->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiados registros desde esta red. Intenta en una hora.']);
        exit;
    }
    $db->prepare("INSERT INTO login_intento (email, ip_address) VALUES ('#registro', ?)")->execute([$ip]);
} catch (PDOException $e) {
    error_log('[Registro] ' . $e->getMessage());   // sin migración 003: sigue sin límite
}
$stmt = $db->prepare('SELECT id_usuario FROM usuario WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Este correo ya está registrado.']);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare('INSERT INTO usuario (nombre, email, password_hash) VALUES (?, ?, ?)');
$stmt->execute([$nombre, $email, $hash]);
$id = (int) $db->lastInsertId();

$token = emitirTokenUsuario(['id_usuario' => $id, 'nombre' => $nombre, 'onboarding_completo' => 0]);

http_response_code(201);
echo json_encode([
    'ok'                  => true,
    'token'               => $token,
    'id_usuario'          => $id,
    'nombre'              => $nombre,
    'onboarding_completo' => 0,
]);

<?php
// ============================================================
//  Seguridad/Sesion.php
//  Única forma de saber QUIÉN hace la petición:
//  el JWT (cookie httpOnly o header Authorization), con la firma verificada.
//
//  ⚠️ Nunca uses un id_usuario que venga en la URL o en el body.
//     El cliente puede mandar el número que quiera.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';
require_once __DIR__ . '/JWT.php';

/** Lee el token "Bearer ..." sin importar cómo lo entregue Apache/PHP. */
function leerTokenBearer(): string {
    $auth = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $auth = $v; break; }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) return $m[1];
    // La app web manda la sesión en la cookie httpOnly (ver Tokens.php)
    return (string) ($_COOKIE['lifefit_sesion'] ?? '');
}

/** Responde 401 en el mismo formato que usa el resto del backend y termina. */
function responderNoAutenticado(string $msg): never {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Devuelve el payload del JWT verificado (firma + expiración).
 * Si no hay token o no es válido, responde 401 y termina.
 */
function sesionActual(): array {
    static $payload = null;
    if ($payload !== null) return $payload;

    $token = leerTokenBearer();
    if ($token === '') responderNoAutenticado('Token requerido.');

    $datos = jwtDecode($token);
    if (!$datos || empty($datos['sub'])) responderNoAutenticado('Token inválido o expirado.');

    return $payload = $datos;
}

/** id_usuario autenticado (entero > 0). */
function usuarioAutenticadoId(): int {
    return (int) sesionActual()['sub'];
}

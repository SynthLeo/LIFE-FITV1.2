<?php
// ============================================================
//  Seguridad/Tokens.php
//  Emite el JWT de la app a partir de la fila del usuario.
//  Lo usan Login, Registro y CompletarOnboarding para que el
//  token siempre traiga los mismos datos.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';
require_once __DIR__ . '/JWT.php';

const COOKIE_SESION = 'lifefit_sesion';

/**
 * Crea el JWT y lo manda en una cookie httpOnly: el JavaScript de la página
 * no la puede leer, así que un XSS ya no puede robar la sesión.
 * SameSite=Lax evita que otros sitios hagan peticiones con tu sesión (CSRF).
 */
function emitirTokenUsuario(array $u): string {
    $token = jwtEncode([
        'sub'                 => (int) $u['id_usuario'],
        'nombre'              => $u['nombre'],
        'onboarding_completo' => (int) $u['onboarding_completo'],
        'iat'                 => time(),
        'exp'                 => time() + JWT_EXPIRY,
    ]);
    enviarCookieSesion($token, time() + JWT_EXPIRY);
    return $token;
}

function enviarCookieSesion(string $valor, int $expira): void {
    if (headers_sent()) return;
    setcookie(COOKIE_SESION, $valor, [
        'expires'  => $expira,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function borrarCookieSesion(): void {
    enviarCookieSesion('', time() - 3600);
}

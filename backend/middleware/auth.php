<?php
// ============================================================
//  middleware/auth.php
//  Valida que el usuario esté autenticado con el JWT de la app.
//
//  Antes aceptaba un id_usuario en el body (modo "debug") y
//  buscaba el token en la tabla sesion, que la app no usa.
//  Eso permitía entrar como cualquier usuario. Ya no.
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../Seguridad/Sesion.php';

/** Devuelve la fila del usuario autenticado (o responde 401/404). */
function autenticar(): array {
    return obtenerusuario(usuarioAutenticadoId());
}

function obtenerusuario(int $id): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) error('usuario no encontrado.', 404);
    unset($u['password_hash']);
    return $u;
}

<?php
// ============================================================
//  POST Api/Auth/CompletarOnboarding.php
//  Marca el onboarding como terminado y devuelve un JWT nuevo
//  con onboarding_completo = 1.
//
//  Arregla el bucle: antes el frontend solo cambiaba su estado
//  local, el token seguía diciendo 0 y al recargar regresaba
//  a /onboarding.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../Seguridad/Tokens.php';
require_once __DIR__ . '/../../utils/api.php';

exigirMetodo('POST');

$uid = (int) $GLOBALS['userId'];

try {
    $db = getDB();
    $db->prepare('UPDATE usuario SET onboarding_completo = 1 WHERE id_usuario = ?')->execute([$uid]);

    $st = $db->prepare('SELECT id_usuario, nombre, onboarding_completo FROM usuario WHERE id_usuario = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u) salir(404, ['error' => 'Usuario no encontrado.']);
} catch (Throwable $e) {
    errorInterno('CompletarOnboarding', $e);
}

salir(200, [
    'ok'                  => true,
    'token'               => emitirTokenUsuario($u),
    'onboarding_completo' => 1,
]);

<?php
/**
 * Middleware de autenticación JWT (endpoints de Api/).
 * Lee el token del header: Authorization: Bearer <token>
 * Inyecta $GLOBALS['jwt_user'] y $GLOBALS['userId'].
 * Incluir DESPUÉS de Cors.php y ANTES de la lógica del endpoint.
 */

require_once __DIR__ . '/Sesion.php';

$GLOBALS['jwt_user'] = sesionActual();
$GLOBALS['userId']   = usuarioAutenticadoId();

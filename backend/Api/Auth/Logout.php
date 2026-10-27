<?php
// POST Api/Auth/Logout.php — borra la cookie de sesión (el navegador no la puede borrar solo: es httpOnly)
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Tokens.php';
require_once __DIR__ . '/../../utils/api.php';

exigirMetodo('POST');
borrarCookieSesion();
salir(200, ['ok' => true]);

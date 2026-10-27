<?php
// ============================================================
//  POST ia/limpiar.php — borra el historial reciente del chat
//  Body: { modulo }
//  Auth: Bearer JWT verificado. Solo borra mensajes del usuario
//  dueño del token (antes se podía mandar otro id_usuario).
// ============================================================

require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Seguridad/Sesion.php';
require_once __DIR__ . '/memoria.php';

metodo('POST');
$idusuario = usuarioAutenticadoId();

$modulo = body()['modulo'] ?? '';
if (!in_array($modulo, ['ejercicio', 'psicologia', 'alimentacion', 'clinico'], true)) {
    error('Módulo no válido.');
}

limpiarSesion($idusuario, $modulo);
respuesta(['mensaje' => 'Historial de sesión eliminado.']);

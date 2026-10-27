<?php
// ============================================================
//  Api/Social/Privacidad.php — ocultarse del ranking y del feed (M5)
//  GET  → { ocultar_ranking: bool }
//  POST { ocultar_ranking: bool }
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';

$uid = (int) $GLOBALS['userId'];

try {
    $db = getDB();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $b = jsonBody();
        if (!is_bool($b['ocultar_ranking'] ?? null)) salir(422, ['error' => 'ocultar_ranking debe ser true o false.']);
        $db->prepare('UPDATE usuario SET ocultar_ranking = ? WHERE id_usuario = ?')->execute([(int) $b['ocultar_ranking'], $uid]);
    } else {
        exigirMetodo('GET');
    }
    $st = $db->prepare('SELECT ocultar_ranking FROM usuario WHERE id_usuario = ?');
    $st->execute([$uid]);
    salir(200, ['ok' => true, 'ocultar_ranking' => (bool) $st->fetchColumn()]);
} catch (Throwable $e) {
    errorInterno('Social/Privacidad', $e);
}

<?php
// ============================================================
//  Api/Auth/Consentimiento.php — Consentimiento informado (M2)
//  GET  → { aceptado, version }
//  POST { acepto: true, version } → registra la aceptación
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';
require_once __DIR__ . '/../../utils/consentimiento.php';

$uid = (int) $GLOBALS['userId'];

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        salir(200, ['ok' => true, 'aceptado' => consentimientoVigente($db, $uid), 'version' => CONSENTIMIENTO_VERSION]);
    }

    exigirMetodo('POST');
    $b = jsonBody();
    if (($b['acepto'] ?? false) !== true) salir(422, ['error' => 'Para continuar tienes que aceptar el consentimiento.']);
    if (($b['version'] ?? '') !== CONSENTIMIENTO_VERSION) {
        salir(409, ['error' => 'El texto del consentimiento cambió. Recarga la página y léelo de nuevo.', 'version' => CONSENTIMIENTO_VERSION]);
    }

    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
    $db->beginTransaction();
    $db->prepare('INSERT INTO consentimiento_informado (id_usuario, version, acepto, ip_address) VALUES (?, ?, 1, ?)')
       ->execute([$uid, CONSENTIMIENTO_VERSION, $ip]);
    $db->prepare('INSERT INTO usuario_perfil_salud (id_usuario, acepto_consentimiento, fecha_consentimiento) VALUES (?, 1, NOW())
                  ON DUPLICATE KEY UPDATE acepto_consentimiento = 1, fecha_consentimiento = NOW()')
       ->execute([$uid]);
    $db->commit();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    errorInterno('Consentimiento', $e);
}

salir(200, ['ok' => true, 'aceptado' => true, 'version' => CONSENTIMIENTO_VERSION]);

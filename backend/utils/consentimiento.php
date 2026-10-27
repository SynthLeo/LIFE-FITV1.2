<?php
// ============================================================
//  utils/consentimiento.php — Consentimiento informado (M2)
//  Cada aceptación queda como un registro en consentimiento_informado
//  (con versión e IP). Si cambia el texto, se sube CONSENTIMIENTO_VERSION
//  y todos tienen que volver a aceptar.
// ============================================================

const CONSENTIMIENTO_VERSION = '2.0';

function consentimientoVigente(PDO $db, int $uid): bool {
    $st = $db->prepare('SELECT acepto FROM consentimiento_informado WHERE id_usuario = ? AND version = ? ORDER BY id_consentimiento DESC LIMIT 1');
    $st->execute([$uid, CONSENTIMIENTO_VERSION]);
    return (int) $st->fetchColumn() === 1;
}

/** Corta con 403 si el usuario no ha aceptado la versión vigente. */
function exigirConsentimiento(PDO $db, int $uid): void {
    if (!consentimientoVigente($db, $uid)) {
        salir(403, ['error' => 'Antes de contestar los cuestionarios tienes que aceptar el consentimiento informado.', 'requiere_consentimiento' => true]);
    }
}

<?php
// ============================================================
//  modules/perfil/index.php
//  Gestión del perfil de salud extendido
//
//  GET  ?id_usuario=X         → obtener perfil completo
//  POST ?accion=salud         → guardar/actualizar perfil salud
//  POST ?accion=consentimiento → registrar aceptación
// ============================================================

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../Seguridad/Sesion.php';

// ── Seguridad: el usuario sale SOLO del JWT verificado ────────
// Se ignora cualquier id_usuario que mande el cliente en la URL o en el body.
$ID_AUTENTICADO     = usuarioAutenticadoId();
$_GET['id_usuario'] = $ID_AUTENTICADO;

$accion = $_GET['accion'] ?? 'perfil';
$metReq = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metReq === 'GET') {
    $idusuario = (int)($_GET['id_usuario'] ?? 0);
    if (!$idusuario) error('id_usuario requerido.');

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, u.peso_kg, u.altura_cm,
               u.fecha_nacimiento, u.sexo_biologico, u.nivel_entrenamiento,
               u.objetivo, u.equipo_disponible, u.racha_actual,
               u.fitness_score, u.onboarding_completo,
               ups.*
        FROM   usuario u
        LEFT JOIN usuario_perfil_salud ups ON ups.id_usuario = u.id_usuario
        WHERE  u.id_usuario = ?
    ");
    $stmt->execute([$idusuario]);
    $perfil = $stmt->fetch();
    if (!$perfil) error('usuario no encontrado.', 404);

    // Calcular IMC si hay datos
    if ($perfil['peso_kg'] && $perfil['altura_cm']) {
        $perfil['imc']          = calcularIMC((float)$perfil['peso_kg'], (int)$perfil['altura_cm']);
        $perfil['categoria_imc']= categoriaIMC($perfil['imc']);
    }

    // Verificar consentimiento
    $cons = $pdo->prepare("SELECT acepto FROM consentimiento_informado WHERE id_usuario = ? ORDER BY created_at DESC LIMIT 1");
    $cons->execute([$idusuario]);
    $perfil['consentimiento_aceptado'] = (bool)($cons->fetchColumn() ?? false);

    respuesta($perfil);
}

// ── POST ─────────────────────────────────────────────────────
if ($metReq === 'POST') {
    $b   = body();
    $b['id_usuario'] = $ID_AUTENTICADO;
    $pdo = getDB();

    switch ($accion) {

        // Guardar/actualizar perfil de salud extendido
        case 'salud':
            if (empty($b['id_usuario'])) error('id_usuario requerido.');

            $stmt = $pdo->prepare("
                INSERT INTO usuario_perfil_salud
                    (id_usuario, tipo_sangre, alergias, enfermedades_cronicas,
                     medicamentos_actuales, antecedentes_familiares,
                     condicion_alimentaria, alergias_alimentarias, meta_nutricional,
                     nivel_estres_base, duerme_horas, consume_alcohol, consume_tabaco)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    tipo_sangre              = VALUES(tipo_sangre),
                    alergias                 = VALUES(alergias),
                    enfermedades_cronicas    = VALUES(enfermedades_cronicas),
                    medicamentos_actuales    = VALUES(medicamentos_actuales),
                    antecedentes_familiares  = VALUES(antecedentes_familiares),
                    condicion_alimentaria    = VALUES(condicion_alimentaria),
                    alergias_alimentarias    = VALUES(alergias_alimentarias),
                    meta_nutricional         = VALUES(meta_nutricional),
                    nivel_estres_base        = VALUES(nivel_estres_base),
                    duerme_horas             = VALUES(duerme_horas),
                    consume_alcohol          = VALUES(consume_alcohol),
                    consume_tabaco           = VALUES(consume_tabaco),
                    updated_at               = NOW()
            ");
            $stmt->execute([
                $b['id_usuario'],
                $b['tipo_sangre']             ?? null,
                $b['alergias']                ?? null,
                $b['enfermedades_cronicas']   ?? null,
                $b['medicamentos_actuales']   ?? null,
                $b['antecedentes_familiares'] ?? null,
                $b['condicion_alimentaria']   ?? 'ninguna',
                $b['alergias_alimentarias']   ?? null,
                $b['meta_nutricional']        ?? 'salud_general',
                $b['nivel_estres_base']       ?? null,
                $b['duerme_horas']            ?? null,
                (int)($b['consume_alcohol']   ?? 0),
                (int)($b['consume_tabaco']    ?? 0),
            ]);
            respuesta(['mensaje' => 'Perfil de salud actualizado.']);

        // Registrar consentimiento informado
        case 'consentimiento':
            if (empty($b['id_usuario'])) error('id_usuario requerido.');

            $stmt = $pdo->prepare("
                INSERT INTO consentimiento_informado
                    (id_usuario, version, acepto, ip_address)
                VALUES (?, '1.0', 1, ?)
            ");
            $stmt->execute([$b['id_usuario'], $_SERVER['REMOTE_ADDR'] ?? null]);

            // Marcar en perfil_salud también
            $upd = $pdo->prepare("
                UPDATE usuario_perfil_salud
                SET acepto_consentimiento = 1, fecha_consentimiento = NOW()
                WHERE id_usuario = ?
            ");
            $upd->execute([$b['id_usuario']]);

            respuesta(['mensaje' => 'Consentimiento registrado.']);

        default:
            error('Acción POST no reconocida.');
    }
}

error('Método no permitido.', 405);

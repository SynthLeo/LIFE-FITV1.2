<?php
// ============================================================
//  ia/perfil.php
//  Lee el perfil y los cuestionarios del usuario para dar
//  contexto a la IA. Las columnas sensibles (respuestas del
//  PHQ-8/GAD-7, AUDIT-C) NO se leen aquí.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';

function cargarPerfilIA(int $uid): ?array {
    $st = getDB()->prepare('
        SELECT id_usuario, nombre, fecha_nacimiento, sexo_biologico, peso_kg, altura_cm,
               nivel_entrenamiento, objetivo, equipo_disponible, racha_actual
        FROM usuario WHERE id_usuario = ? LIMIT 1
    ');
    $st->execute([$uid]);
    return $st->fetch() ?: null;
}

function cargarCuestionariosIA(int $uid): array {
    $db = getDB();
    $consultas = [
        'ejercicio'    => 'SELECT ipaq_categoria, ipaq_met_min, parq_resultado, equipo, lugar_ejercicio, objetivo_ejercicio, nivel_recomendado
                           FROM cuestionario_habitos WHERE id_usuario = ? LIMIT 1',
        'clinico'      => 'SELECT presion_dx, glucosa_dx, colesterol_dx, sueno_horas, nivel_salud
                           FROM cuestionarios_clinicos WHERE usuario_id = ? LIMIT 1',
        'psicologia'   => 'SELECT nivel_psicologico, metas_bienestar
                           FROM cuestionarios_psicologicos WHERE usuario_id = ? LIMIT 1',
        'alimentacion' => 'SELECT objetivo, proteina, alergias, presupuesto, cocina, actividad_fisica_alim, nivel_nutricion
                           FROM cuestionarios_alimentacion WHERE usuario_id = ? LIMIT 1',
    ];
    $out = [];
    foreach ($consultas as $k => $sql) {
        try {
            $st = $db->prepare($sql);
            $st->execute([$uid]);
            $out[$k] = $st->fetch() ?: null;
        } catch (PDOException $e) {
            // Si la migración aún no corre, alguna columna no existe: se sigue sin ese bloque
            error_log("[cargarCuestionariosIA:$k] " . $e->getMessage());
            $out[$k] = null;
        }
    }
    return $out;
}

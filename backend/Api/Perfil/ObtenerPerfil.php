<?php
/**
 * GET /Api/Perfil/ObtenerPerfil.php
 * Devuelve el perfil completo del usuario autenticado,
 * incluyendo sexo_biologico para el modelo anatómico.
 */
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

try {
    $stmt = $db->prepare('
        SELECT
            id_usuario,
            nombre,
            email,
            avatar_id,
            sexo_biologico,
            peso_kg,
            altura_cm,
            fecha_nacimiento,
            nivel_entrenamiento,
            objetivo,
            equipo_disponible,
            fitness_score,
            onboarding_completo,
            cuestionario_completo,
            created_at,
            updated_at
        FROM usuario
        WHERE id_usuario = ?
    ');
    $stmt->execute([$userId]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        http_response_code(404);
        echo json_encode(['error' => 'usuario no encontrado.']);
        exit;
    }

    // Calcular IMC si hay datos disponibles
    $imc = null;
    if ($perfil['peso_kg'] && $perfil['altura_cm']) {
        $alturaM = $perfil['altura_cm'] / 100;
        $imc = round($perfil['peso_kg'] / ($alturaM * $alturaM), 1);
    }

    // Calcular edad si hay fecha de nacimiento
    $edad = null;
    if ($perfil['fecha_nacimiento']) {
        $nacimiento = new DateTime($perfil['fecha_nacimiento']);
        $hoy        = new DateTime('today');
        $edad       = (int) $hoy->diff($nacimiento)->y;
    }

    echo json_encode([
        'ok'     => true,
        'perfil' => [
            'id_usuario'          => (int)   $perfil['id_usuario'],
            'nombre'              =>          $perfil['nombre'],
            'email'               =>          $perfil['email'],
            'avatar_id'           =>          $perfil['avatar_id'],
            'sexo_biologico'      =>          $perfil['sexo_biologico'] ?? 'masculino',
            'peso_kg'             => $perfil['peso_kg']    ? (float) $perfil['peso_kg']    : null,
            'altura_cm'           => $perfil['altura_cm']  ? (float) $perfil['altura_cm']  : null,
            'imc'                 => $imc,
            'edad'                => $edad,
            'fecha_nacimiento'    =>          $perfil['fecha_nacimiento'],
            'nivel_entrenamiento' =>          $perfil['nivel_entrenamiento'],
            'objetivo'            =>          $perfil['objetivo'],
            'equipo_disponible'   =>          $perfil['equipo_disponible'],
            'fitness_score'       => (int)   ($perfil['fitness_score'] ?? 0),
            'onboarding_completo' => (bool)  $perfil['onboarding_completo'],
            'cuestionario_completo' => (bool) ($perfil['cuestionario_completo'] ?? false),
            'created_at'          =>          $perfil['created_at'],
            'updated_at'          =>          $perfil['updated_at'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[ObtenerPerfil] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}

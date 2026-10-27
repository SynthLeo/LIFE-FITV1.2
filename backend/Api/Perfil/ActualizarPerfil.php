<?php
/**
 * PUT /Api/Perfil/ActualizarPerfil.php
 * Actualiza los datos del perfil del usuario autenticado.
 * Todos los campos son opcionales — solo se actualiza lo que se envíe.
 *
 * Body JSON (todos opcionales):
 *   nombre           string
 *   sexo_biologico   'masculino' | 'femenino'
 *   peso_kg          float
 *   altura_cm        float
 *   fecha_nacimiento 'YYYY-MM-DD'
 *   avatar_id        string
 */
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se enviaron datos para actualizar.']);
    exit;
}

// ── Construir SET dinámico — solo campos presentes en el body ─
$campos   = [];
$valores  = [];
$errores  = [];

if (array_key_exists('nombre', $body)) {
    $nombre = trim($body['nombre']);
    if (mb_strlen($nombre) < 2) {
        $errores[] = 'El nombre debe tener al menos 2 caracteres.';
    } else {
        $campos[]  = 'nombre = ?';
        $valores[] = $nombre;
    }
}

if (array_key_exists('sexo_biologico', $body)) {
    $sexo = trim($body['sexo_biologico']);
    if (!in_array($sexo, ['masculino', 'femenino'], true)) {
        $errores[] = "sexo_biologico debe ser 'masculino' o 'femenino'.";
    } else {
        $campos[]  = 'sexo_biologico = ?';
        $valores[] = $sexo;
    }
}

if (array_key_exists('peso_kg', $body)) {
    $peso = filter_var($body['peso_kg'], FILTER_VALIDATE_FLOAT);
    if ($peso === false || $peso < 20 || $peso > 400) {
        $errores[] = 'peso_kg debe ser un número entre 20 y 400.';
    } else {
        $campos[]  = 'peso_kg = ?';
        $valores[] = round($peso, 2);
    }
}

if (array_key_exists('altura_cm', $body)) {
    $altura = filter_var($body['altura_cm'], FILTER_VALIDATE_FLOAT);
    if ($altura === false || $altura < 100 || $altura > 250) {
        $errores[] = 'altura_cm debe ser un número entre 100 y 250.';
    } else {
        $campos[]  = 'altura_cm = ?';
        $valores[] = round($altura, 1);
    }
}

if (array_key_exists('fecha_nacimiento', $body)) {
    $fecha = trim($body['fecha_nacimiento']);
    $dt    = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        $errores[] = 'fecha_nacimiento debe estar en formato YYYY-MM-DD.';
    } else {
        $campos[]  = 'fecha_nacimiento = ?';
        $valores[] = $fecha;
    }
}

if (array_key_exists('avatar_id', $body)) {
    $campos[]  = 'avatar_id = ?';
    $valores[] = trim($body['avatar_id']);
}

// ── Errores de validación ─────────────────────────────────────
if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errores)]);
    exit;
}

if (empty($campos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ningún campo válido para actualizar.']);
    exit;
}

// ── UPDATE ────────────────────────────────────────────────────
$campos[]  = 'updated_at = NOW()';
$valores[] = $userId;

try {
    $sql  = 'UPDATE usuario SET ' . implode(', ', $campos) . ' WHERE id_usuario = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($valores);

    // Devolver el perfil actualizado
    $stmt = $db->prepare('
        SELECT id_usuario, nombre, email, avatar_id, sexo_biologico,
               peso_kg, altura_cm, fecha_nacimiento,
               nivel_entrenamiento, objetivo, fitness_score,
               onboarding_completo, cuestionario_completo, updated_at
        FROM usuario WHERE id_usuario = ?
    ');
    $stmt->execute([$userId]);
    $perfil = $stmt->fetch();

    // Recalcular IMC
    $imc = null;
    if ($perfil['peso_kg'] && $perfil['altura_cm']) {
        $alturaM = $perfil['altura_cm'] / 100;
        $imc = round($perfil['peso_kg'] / ($alturaM * $alturaM), 1);
    }

    echo json_encode([
        'ok'     => true,
        'perfil' => [
            'id_usuario'           => (int)   $perfil['id_usuario'],
            'nombre'               =>          $perfil['nombre'],
            'email'                =>          $perfil['email'],
            'avatar_id'            =>          $perfil['avatar_id'],
            'sexo_biologico'       =>          $perfil['sexo_biologico'] ?? 'masculino',
            'peso_kg'              => $perfil['peso_kg']   ? (float) $perfil['peso_kg']   : null,
            'altura_cm'            => $perfil['altura_cm'] ? (float) $perfil['altura_cm'] : null,
            'imc'                  => $imc,
            'nivel_entrenamiento'  =>          $perfil['nivel_entrenamiento'],
            'objetivo'             =>          $perfil['objetivo'],
            'fitness_score'        => (int)   ($perfil['fitness_score'] ?? 0),
            'onboarding_completo'  => (bool)   $perfil['onboarding_completo'],
            'cuestionario_completo'=> (bool)  ($perfil['cuestionario_completo'] ?? false),
            'updated_at'           =>          $perfil['updated_at'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('[ActualizarPerfil] ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}

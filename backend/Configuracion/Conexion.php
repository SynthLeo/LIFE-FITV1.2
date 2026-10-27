<?php
// ============================================================
//  Configuracion/Conexion.php
//  ÚNICA fuente de configuración del backend (BD, JWT e IA).
//  config/database.php ahora solo incluye este archivo.
//
//  Para no subir contraseñas al repo, puedes crear
//  Configuracion/local.php con tus propios define(...)
//  y se cargará antes que los valores por defecto.
// ============================================================

if (is_file(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

// ── Base de datos ─────────────────────────────────────────────
defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_NAME')    || define('DB_NAME', 'fitness');
defined('DB_USER')    || define('DB_USER', 'LEO');
defined('DB_PASS')    || define('DB_PASS', 'LM99F30');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');
defined('DB_PORT')    || define('DB_PORT', '3307');

// ── JWT ───────────────────────────────────────────────────────
// ⚠️ Cambia este secreto por uno largo y aleatorio (64+ caracteres) y no lo subas a git.
defined('JWT_SECRET') || define('JWT_SECRET', 'tu_secreto_super_seguro_cambiar_en_produccion_32chars');
defined('JWT_EXPIRY') || define('JWT_EXPIRY', 3600 * 24 * 7); // 7 días

// ── IA (LM Studio, API compatible con OpenAI) ─────────────────
// LLM_MODEL = el identificador que muestra LM Studio (pestaña Developer → modelo cargado).
// Recomendado: Qwen3 4B Instruct 2507 → 'qwen/qwen3-4b-2507' (cabe en la RTX 4050 de 6 GB).
// ⚠️ No uses modelos "uncensored"/"abliterated"/"heretic": les quitaron los filtros de seguridad
//    y esta app habla de salud mental con estudiantes.
defined('LLM_URL')   || define('LLM_URL', 'http://127.0.0.1:1234/v1');
defined('LLM_MODEL') || define('LLM_MODEL', 'qwen/qwen3-4b-2507');
// Solo para modelos que "piensan" antes de responder (Qwen3 híbrido / Thinking):
//   LLM_NO_THINK = true agrega /no_think; LLM_EXTRA_TOKENS da margen al razonamiento.
// Con Qwen3 4B Instruct 2507 déjalos en false y 0.
defined('LLM_NO_THINK')     || define('LLM_NO_THINK', false);
defined('LLM_EXTRA_TOKENS') || define('LLM_EXTRA_TOKENS', 0);
// Máximo de peticiones de IA por usuario por minuto (para no saturar la GPU de la laptop)
defined('LLM_RATE_LIMIT_MIN') || define('LLM_RATE_LIMIT_MIN', 6);

// ── Línea de ayuda (la misma que muestra el frontend) ─────────
defined('LINEA_AYUDA_NOMBRE') || define('LINEA_AYUDA_NOMBRE', 'Línea de la Vida');
defined('LINEA_AYUDA_TEL')    || define('LINEA_AYUDA_TEL', '800 911 2000');

// ── Orígenes que pueden llamar a la API (CORS). Agrega aquí tu dominio o túnel ngrok. ──
defined('CORS_ORIGINS') || define('CORS_ORIGINS', [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:4173',
    'https://surely-wok-sanding.ngrok-free.dev',
]);

// ── Modo depuración: muestra detalles de errores. Déjalo en false. ──
defined('APP_DEBUG') || define('APP_DEBUG', false);

if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo !== null) return $pdo;

        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[getDB] ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Error interno del sistema']);
            exit;
        }
        return $pdo;
    }
}

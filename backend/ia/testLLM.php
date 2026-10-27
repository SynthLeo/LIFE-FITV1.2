<?php
// Herramienta de desarrollo: solo responde con APP_DEBUG = true (Configuracion/Conexion.php)
require_once __DIR__ . '/../Configuracion/Conexion.php';
if (!APP_DEBUG) { http_response_code(404); exit; }
// ia/testLLM.php — prueba directa a LM Studio (reemplaza testOllama.php)
header('Content-Type: application/json');
set_time_limit(300);
require_once __DIR__ . '/llm.php';

$inicio = microtime(true);
$r = llmChat([['role' => 'user', 'content' => 'Responde solo: hola']], ['max_tokens' => 50, 'timeout' => 300]);

echo json_encode([
    'modelo'          => LLM_MODEL,
    'tiempo_segundos' => round(microtime(true) - $inicio, 2),
    'ok'              => $r['ok'],
    'http_code'       => $r['http'],
    'error'           => $r['error'],
    'respuesta'       => $r['content'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
<?php
// Herramienta de desarrollo: solo responde con APP_DEBUG = true (Configuracion/Conexion.php)
require_once __DIR__ . '/../Configuracion/Conexion.php';
if (!APP_DEBUG) { http_response_code(404); exit; }
// Archivo temporal de diagnóstico para LM Studio — eliminar después
header('Content-Type: application/json');
require_once __DIR__ . '/llm.php';

$resultados = [];

// Test 1: ¿LM Studio responde y qué modelos ve?
$ch = curl_init(rtrim(LLM_URL, '/') . '/models');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    $resultados['servidor'] = "❌ No responde en " . LLM_URL . " — $err";
} else {
    $ids = array_column(json_decode($res, true)['data'] ?? [], 'id');
    $resultados['servidor']            = "✅ Responde";
    $resultados['modelos_disponibles'] = $ids ?: ['(ninguno)'];
    $resultados['modelo_configurado']  = LLM_MODEL;
    $resultados['modelo_encontrado']   = in_array(LLM_MODEL, $ids, true) ? "✅ Sí" : "❌ No coincide con ninguno de los ids";
}

// Test 2: entorno PHP
$resultados['curl_habilitado'] = function_exists('curl_init') ? "✅ Sí" : "❌ No";
$resultados['php_version']     = PHP_VERSION;

echo json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
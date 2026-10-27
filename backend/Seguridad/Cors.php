<?php
/**
 * CORS + Content-Type para todos los endpoints.
 * Incluir PRIMERO en cada archivo de API.
 * La lista de orígenes permitidos está en Configuracion/Conexion.php (CORS_ORIGINS).
 */

require_once __DIR__ . '/../Configuracion/Conexion.php';

$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen !== '' && in_array($origen, CORS_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

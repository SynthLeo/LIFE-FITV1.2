<?php
// ============================================================
//  config/headers.php
//  Headers CORS + helpers de respuesta — incluir en todo endpoint
// ============================================================

// CORS y Content-Type: la misma lógica (y lista de orígenes) que usa Api/
require_once __DIR__ . '/../Seguridad/Cors.php';

// ── Helpers ──────────────────────────────────────────────────

function respuesta(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['success' => true,  'data'  => $data],  JSON_UNESCAPED_UNICODE);
    exit;
}

function error(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg],   JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function metodo(string $m): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($m))
        error('Método no permitido.', 405);
}

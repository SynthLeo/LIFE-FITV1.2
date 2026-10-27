<?php
// ============================================================
//  POST ia/chat.php — chat con la IA (LM Studio)
//  Body: { modulo: ejercicio|psicologia|alimentacion|clinico, mensaje, stream? }
//  Con stream: true responde NDJSON: {"delta":"..."} … {"fin":true,"es_crisis":bool} o {"error":"..."}
//  Auth: Bearer JWT (verificado). El id_usuario del body se ignora.
//
//  Orden:
//   1. Autenticación y límite de peticiones
//   2. Detección de crisis en el servidor → mensaje fijo, SIN llamar al modelo
//   3. Prompt del módulo + perfil resumido + historial
//   4. LM Studio
//   5. Guardar en ia_conversacion
// ============================================================

require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Seguridad/Sesion.php';
require_once __DIR__ . '/prompts.php';
require_once __DIR__ . '/memoria.php';
require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/crisis.php';
require_once __DIR__ . '/perfil.php';

metodo('POST');

/** Respuesta en pedazos: una línea JSON por pedazo (NDJSON), sin búfer. */
function iniciarStream(): void {
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');   // nginx/proxies: no acumular
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    while (ob_get_level() > 0) ob_end_flush();
}
function enviarLinea(array $datos): void {
    echo json_encode($datos, JSON_UNESCAPED_UNICODE) . "\n";
    @ob_flush();
    flush();
}
$idusuario = usuarioAutenticadoId();

$body    = body();
$stream  = !empty($body['stream']);   // true → la respuesta llega en pedazos (NDJSON)
$modulo  = $body['modulo'] ?? '';
$mensaje = trim((string) ($body['mensaje'] ?? ''));

$modulosValidos = ['ejercicio', 'psicologia', 'alimentacion', 'clinico'];
if (!in_array($modulo, $modulosValidos, true)) error('Módulo no válido.');
if ($mensaje === '')            error('El mensaje no puede estar vacío.');
if (mb_strlen($mensaje) > 2000) error('El mensaje es demasiado largo (máximo 2000 caracteres).');

$perfil = cargarPerfilIA($idusuario);
if (!$perfil) error('Usuario no encontrado.', 404);

// ── 1. Crisis: respuesta fija, sin modelo ─────────────────────
$tipoCrisis = detectarCrisis($mensaje);
if ($tipoCrisis !== null) {
    $respuesta = mensajeCrisis($tipoCrisis);
    guardarMensaje($idusuario, $modulo, 'user',      $mensaje,   true);
    guardarMensaje($idusuario, $modulo, 'assistant', $respuesta, true);
    if ($stream) {
        iniciarStream();
        enviarLinea(['delta' => $respuesta]);
        enviarLinea(['fin' => true, 'es_crisis' => true, 'tipo_crisis' => $tipoCrisis]);
        exit;
    }
    respuesta([
        'respuesta'   => $respuesta,
        'es_crisis'   => true,
        'tipo_crisis' => $tipoCrisis,
        'tokens'      => 0,
    ]);
}

// ── 2. Límite de peticiones (la GPU de la laptop es una sola) ──
if (!llmPermitido($idusuario, 'chat')) {
    error('Vas muy rápido 😅 Espera un minuto y vuelve a intentarlo.', 429);
}

// ── 3. Construir mensajes ─────────────────────────────────────
$resumen         = getResumenHistorial($idusuario, $modulo);
$systemPrompt    = getSystemPrompt($modulo, $perfil, $resumen, cargarCuestionariosIA($idusuario));
$historialSesion = getHistorialSesion($idusuario, $modulo);

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($historialSesion as $h) {
    $messages[] = ['role' => $h['rol'], 'content' => $h['contenido']];
}

// Refuerzo determinista: si proponen una dieta muy baja en calorías, se le recuerda al modelo no validarla
$mensajeModelo = $mensaje;
if (preg_match('/(\d{3,4})\s*(k?cal|calor[ií]as)/iu', $mensaje, $m) && (int) $m[1] < 1400) {
    $mensajeModelo .= "\n\n[Nota del sistema: el usuario propone {$m[1]} kcal al día, por debajo del mínimo seguro. No lo valides; explica el riesgo con empatía y sugiere un nutriólogo.]";
}
$messages[] = ['role' => 'user', 'content' => $mensajeModelo];

// ── 4. LM Studio ──────────────────────────────────────────────
if ($stream) {
    iniciarStream();
    $r = llmChatStream($messages, fn(string $t) => enviarLinea(['delta' => $t]), [
        'temperature' => 0.6,
        'top_p'       => 0.9,
        'max_tokens'  => 700,
        'timeout'     => 180,
    ]);
    if (!$r['ok']) {
        error_log('[ia/chat stream] ' . $r['error']);
        enviarLinea(['error' => 'El asistente no está disponible en este momento. Revisa que LM Studio esté corriendo.']);
        exit;
    }
    guardarMensaje($idusuario, $modulo, 'user',      $mensaje,       false);
    guardarMensaje($idusuario, $modulo, 'assistant', $r['content'],  false);
    enviarLinea(['fin' => true, 'es_crisis' => false]);
    exit;
}

$r = llmChat($messages, [
    'temperature' => 0.6,
    'top_p'       => 0.9,
    'max_tokens'  => 700,
    'timeout'     => 180,
]);
if (!$r['ok']) {
    error_log('[ia/chat] ' . $r['error']);
    error('El asistente no está disponible en este momento. Revisa que LM Studio esté corriendo.', 503);
}
$respuesta = $r['content'];

// ── 5. Guardar y responder ────────────────────────────────────
guardarMensaje($idusuario, $modulo, 'user',      $mensaje,   false);
guardarMensaje($idusuario, $modulo, 'assistant', $respuesta, false);

respuesta([
    'respuesta' => $respuesta,
    'es_crisis' => false,
    'tokens'    => $r['tokens'],
]);

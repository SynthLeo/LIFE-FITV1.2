<?php
// ============================================================
//  ia/llm.php — Cliente para LM Studio (API compatible con OpenAI)
//  POST {LLM_URL}/chat/completions → choices[0].message.content
//
//  URL, modelo y límites se configuran en Configuracion/Conexion.php
//  (o en Configuracion/local.php). Nada fijo en el código.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';


/** Quita bloques <think>…</think> (también si quedaron sin cerrar por un corte). */
function llmLimpiarRespuesta(string $texto): string {
    $texto = preg_replace('/<think>.*?<\/think>/s', '', $texto);
    $texto = preg_replace('/<think>.*$/s', '', $texto);
    return trim($texto);
}

/** Extrae el primer objeto JSON de un texto (quita ```json y basura alrededor). */
function llmExtraerJSON(string $texto): ?array {
    $texto = llmLimpiarRespuesta($texto);
    $texto = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($texto));
    $data  = json_decode($texto, true);
    if (is_array($data)) return $data;
    if (preg_match('/\{[\s\S]*\}/', $texto, $m)) {
        $data = json_decode($m[0], true);
        if (is_array($data)) return $data;
    }
    return null;
}

/**
 * Llama a LM Studio.
 * $messages: [['role' => 'system|user|assistant', 'content' => '...'], ...]
 * $opts: temperature, top_p, max_tokens, timeout,
 *        json_schema (array) → pide respuesta estructurada,
 *        nombre_schema (string), no_think (bool)
 * Devuelve: ['ok'=>bool, 'content'=>string, 'json'=>?array, 'error'=>?string, 'http'=>int, 'tokens'=>?int]
 */
function llmChat(array $messages, array $opts = []): array {
    $messagesOriginales = $messages;
    if ($opts['no_think'] ?? LLM_NO_THINK) {
        if (($messages[0]['role'] ?? '') === 'system') {
            $messages[0]['content'] .= "\n\n/no_think";
        } else {
            array_unshift($messages, ['role' => 'system', 'content' => '/no_think']);
        }
    }

    $payload = [
        'model'       => LLM_MODEL,
        'messages'    => $messages,
        'temperature' => $opts['temperature'] ?? 0.5,
        'top_p'       => $opts['top_p']       ?? 0.9,
        'max_tokens'  => ($opts['max_tokens'] ?? 1024) + LLM_EXTRA_TOKENS,
        'stream'      => false,
    ];
    if (!empty($opts['json_schema'])) {
        $payload['response_format'] = [
            'type'        => 'json_schema',
            'json_schema' => [
                'name'   => $opts['nombre_schema'] ?? 'respuesta',
                'strict' => true,
                'schema' => $opts['json_schema'],
            ],
        ];
    }

    $timeout = $opts['timeout'] ?? 180;
    @set_time_limit($timeout + 15);   // PHP en Windows corta a 30 s por defecto

    $ch = curl_init(rtrim(LLM_URL, '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Si esta versión de LM Studio no acepta response_format (HTTP 400), se reintenta sin él:
    // el prompt ya pide JSON y la respuesta se valida igual.
    if (!$err && $http === 400 && isset($payload['response_format']) && empty($opts['_sin_schema'])) {
        error_log('[llmChat] response_format rechazado, reintentando sin esquema');
        unset($opts['json_schema']);
        $r = llmChat($messagesOriginales, $opts + ['_sin_schema' => true]);
        $r['json'] = llmExtraerJSON($r['content']);
        return $r;
    }

    $fallo = fn(string $msg) => ['ok' => false, 'content' => '', 'json' => null, 'error' => $msg, 'http' => $http, 'tokens' => null];

    if ($err)          return $fallo(str_contains($err, 'timed out') ? "timeout: $err" : "conexión: $err");
    if ($http !== 200) return $fallo("HTTP $http: " . substr((string) $raw, 0, 300));

    $data    = json_decode($raw, true);
    $content = llmLimpiarRespuesta($data['choices'][0]['message']['content'] ?? '');
    if ($content === '') {
        $razono = !empty($data['choices'][0]['message']['reasoning_content']);
        $corto  = ($data['choices'][0]['finish_reason'] ?? '') === 'length';
        if ($razono && $corto) {
            return $fallo('el modelo agotó los tokens razonando. Sube LLM_EXTRA_TOKENS o usa un modelo Instruct.');
        }
        return $fallo('respuesta vacía.');
    }

    return [
        'ok'      => true,
        'content' => $content,
        'json'    => !empty($opts['json_schema']) ? llmExtraerJSON($content) : null,
        'error'   => null,
        'http'    => $http,
        'tokens'  => $data['usage']['completion_tokens'] ?? null,
    ];
}

/**
 * Límite de peticiones de IA por usuario (LLM_RATE_LIMIT_MIN por minuto).
 * Devuelve true si puede seguir; registra el uso.
 */
function llmPermitido(int $uid, string $tipo): bool {
    $db = getDB();
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM ia_uso WHERE id_usuario = ? AND created_at >= NOW() - INTERVAL 1 MINUTE');
        $st->execute([$uid]);
        if ((int) $st->fetchColumn() >= LLM_RATE_LIMIT_MIN) return false;
        $db->prepare('INSERT INTO ia_uso (id_usuario, tipo) VALUES (?, ?)')->execute([$uid, $tipo]);
        // Limpieza ocasional de registros viejos
        if (random_int(1, 50) === 1) $db->exec('DELETE FROM ia_uso WHERE created_at < NOW() - INTERVAL 1 DAY');
    } catch (PDOException $e) {
        // Si aún no se corre la migración (no existe ia_uso), no bloquea la app
        error_log('[llmPermitido] ' . $e->getMessage());
    }
    return true;
}

/**
 * Igual que llmChat, pero con stream: true. Llama a $alRecibir($texto) con cada
 * pedazo de respuesta en cuanto LM Studio lo genera. Quita los bloques <think>.
 * Devuelve ['ok'=>bool, 'content'=>string (respuesta completa), 'error'=>?string]
 */
function llmChatStream(array $messages, callable $alRecibir, array $opts = []): array {
    if ($opts['no_think'] ?? LLM_NO_THINK) {
        if (($messages[0]['role'] ?? '') === 'system') $messages[0]['content'] .= "\n\n/no_think";
        else array_unshift($messages, ['role' => 'system', 'content' => '/no_think']);
    }
    $payload = [
        'model'       => LLM_MODEL,
        'messages'    => $messages,
        'temperature' => $opts['temperature'] ?? 0.5,
        'top_p'       => $opts['top_p']       ?? 0.9,
        'max_tokens'  => ($opts['max_tokens'] ?? 1024) + LLM_EXTRA_TOKENS,
        'stream'      => true,
    ];
    $timeout = $opts['timeout'] ?? 180;
    @set_time_limit($timeout + 15);

    $pendiente = '';      // líneas SSE incompletas
    $completo  = '';      // texto ya enviado (sin <think>)
    $crudo     = '';      // todo lo recibido del modelo
    $enviado   = 0;       // cuánto de $crudo (ya limpio) se mandó
    $http      = 0;

    $ch = curl_init(rtrim(LLM_URL, '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: text/event-stream'],
        CURLOPT_WRITEFUNCTION  => function ($ch, $datos) use (&$pendiente, &$completo, &$crudo, &$enviado, &$http, $alRecibir) {
            $http = $http ?: (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http !== 200) { $pendiente .= $datos; return strlen($datos); }
            $pendiente .= $datos;
            while (($p = strpos($pendiente, "\n")) !== false) {
                $linea = trim(substr($pendiente, 0, $p));
                $pendiente = substr($pendiente, $p + 1);
                if (!str_starts_with($linea, 'data:')) continue;
                $json = trim(substr($linea, 5));
                if ($json === '[DONE]') continue;
                $delta = json_decode($json, true)['choices'][0]['delta']['content'] ?? '';
                if ($delta === '') continue;
                $crudo .= $delta;
                // Se manda solo lo que ya no puede ser parte de un <think> abierto
                $limpio = llmLimpiarRespuesta($crudo);
                $abierto = strrpos($crudo, '<') !== false && !str_contains(substr($crudo, strrpos($crudo, '<')), '>');
                if ($abierto) continue;
                if (mb_strlen($limpio) > $enviado) {
                    $nuevo = mb_substr($limpio, $enviado);
                    $enviado = mb_strlen($limpio);
                    $completo .= $nuevo;
                    $alRecibir($nuevo);
                }
            }
            return strlen($datos);
        },
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $http = $http ?: (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Lo que haya quedado retenido al final
    $limpio = llmLimpiarRespuesta($crudo);
    if (mb_strlen($limpio) > $enviado) {
        $nuevo = mb_substr($limpio, $enviado);
        $completo .= $nuevo;
        $alRecibir($nuevo);
    }

    if ($err)          return ['ok' => false, 'content' => $completo, 'error' => "conexión: $err"];
    if ($http !== 200) return ['ok' => false, 'content' => '', 'error' => "HTTP $http: " . substr($pendiente, 0, 300)];
    if (trim($completo) === '') return ['ok' => false, 'content' => '', 'error' => 'respuesta vacía.'];
    return ['ok' => true, 'content' => trim($completo), 'error' => null];
}

<?php
// ============================================================
//  utils/api.php
//  Utilidades comunes para los endpoints de Api/:
//  leer el body JSON y responder siempre en el mismo formato.
// ============================================================

/** Body JSON de la petición (array vacío si no viene o es inválido). */
function jsonBody(): array {
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

/** Responde JSON con el código HTTP dado y termina. */
function salir(int $codigo, array $datos): never {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Error genérico: el detalle va al log del servidor, nunca al cliente. */
function errorInterno(string $etiqueta, Throwable $e): never {
    error_log("[$etiqueta] " . $e->getMessage());
    salir(500, ['error' => 'Error interno del servidor.'] + (APP_DEBUG ? ['detalle' => $e->getMessage()] : []));
}

/** Exige un método HTTP. */
function exigirMetodo(string $metodo): void {
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) salir(405, ['error' => 'Método no permitido.']);
}

/**
 * Upsert por usuario: INSERT … ON DUPLICATE KEY UPDATE con solo las columnas dadas.
 * $columnas: ['col' => valor]. $colUsuario: nombre de la columna del usuario en la tabla.
 * $conservarSiNull: columnas que NO se pisan cuando el valor nuevo es NULL.
 */
function upsertPorUsuario(PDO $db, string $tabla, string $colUsuario, int $uid, array $columnas, array $conservarSiNull = []): void {
    $cols   = array_merge([$colUsuario], array_keys($columnas));
    $marcas = array_map(fn($c) => ":$c", $cols);
    $update = array_map(
        fn($c) => in_array($c, $conservarSiNull, true)
            ? "`$c` = COALESCE(VALUES(`$c`), `$c`)"
            : "`$c` = VALUES(`$c`)",
        array_keys($columnas)
    );
    $sql = sprintf(
        'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        $tabla,
        implode(', ', array_map(fn($c) => "`$c`", $cols)),
        implode(', ', $marcas),
        implode(', ', $update)
    );
    $st = $db->prepare($sql);
    $st->bindValue(":$colUsuario", $uid, PDO::PARAM_INT);
    foreach ($columnas as $c => $v) {
        $st->bindValue(":$c", $v, $v === null ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    $st->execute();
}

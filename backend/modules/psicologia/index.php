<?php
// ============================================================
//  modules/psicologia/index.php  — v2 (expandido)
//
//  ENDPOINTS EXISTENTES:
//  GET  ?accion=citas&id_usuario=X
//  GET  ?accion=animos&id_usuario=X&dias=7
//  GET  ?accion=tests&id_usuario=X
//  POST ?accion=cita        → agendar cita
//  POST ?accion=animo       → registrar ánimo
//  POST ?accion=test        → guardar resultado PSS-10 o PHQ-9
//  PUT  ?accion=cancelar    → cancelar cita
//
//  NUEVOS ENDPOINTS:
//  GET  ?accion=estadisticas&id_usuario=X&dias=30
//       → Resumen ejecutivo: promedios, racha de registros,
//         distribución de ánimos, último test, citas pendientes
//
//  GET  ?accion=tendencia_animo&id_usuario=X&dias=14
//       → Serie de tiempo diaria del ánimo (para gráfica de líneas)
//         Incluye valor numérico, emoji y factores más frecuentes
//
//  GET  ?accion=reporte&id_usuario=X&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
//       → Reporte completo del período:
//         ánimos + tests + citas + progreso + recomendaciones
//
//  GET  ?accion=resumen_tests&id_usuario=X
//       → Histórico de PSS-10 y PHQ-9 con evolución en el tiempo
//         Último puntaje, delta vs anterior, nivel actual
//
//  GET  ?accion=factores_frecuentes&id_usuario=X&dias=30
//       → Top factores que afectan el ánimo (desde psico_registro_animo.factores)
//
//  POST ?accion=test  (expandido)
//       → Ahora acepta tipo_test: 'PSS10' | 'PHQ9'
//         PHQ-9 calcula nivel: minimo/leve/moderado/moderado_severo/severo
// ============================================================

require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../Seguridad/Sesion.php';

// ── Seguridad: el usuario sale SOLO del JWT verificado ────────
// Se ignora cualquier id_usuario que mande el cliente en la URL o en el body.
$ID_AUTENTICADO     = usuarioAutenticadoId();
$_GET['id_usuario'] = $ID_AUTENTICADO;

$accion = $_GET['accion'] ?? '';
$metReq = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metReq === 'GET') {

    $idusuario = (int)($_GET['id_usuario'] ?? 0);
    if (!$idusuario) error('id_usuario requerido.');
    $pdo = getDB();

    switch ($accion) {

        // ── EXISTENTE: Citas ──────────────────────────────────
        case 'citas':
            $stmt = $pdo->prepare("
                SELECT * FROM psico_cita
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC, hora DESC
                LIMIT  20
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Registros de ánimo ─────────────────────
        case 'animos':
            $dias = (int)($_GET['dias'] ?? 7);
            $stmt = $pdo->prepare("
                SELECT * FROM psico_registro_animo
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                ORDER  BY fecha DESC, hora DESC
            ");
            $stmt->execute([$idusuario, $dias]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Tests ──────────────────────────────────
        case 'tests':
            $stmt = $pdo->prepare("
                SELECT * FROM psico_test_estres
                WHERE  id_usuario = ?
                ORDER  BY created_at DESC
                LIMIT  10
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── NUEVO: Estadísticas generales ────────────────────
        case 'estadisticas':
            $dias = max(7, min(90, (int)($_GET['dias'] ?? 30)));

            // 1. Conteo y promedio de ánimos en el período
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)                        AS total_registros,
                    SUM(CASE WHEN estado_animo = 'muy_bien' THEN 1 ELSE 0 END) AS muy_bien,
                    SUM(CASE WHEN estado_animo = 'bien'     THEN 1 ELSE 0 END) AS bien,
                    SUM(CASE WHEN estado_animo = 'regular'  THEN 1 ELSE 0 END) AS regular,
                    SUM(CASE WHEN estado_animo = 'mal'      THEN 1 ELSE 0 END) AS mal,
                    SUM(CASE WHEN estado_animo = 'muy_mal'  THEN 1 ELSE 0 END) AS muy_mal,
                    AVG(CASE estado_animo
                        WHEN 'muy_bien' THEN 5
                        WHEN 'bien'     THEN 4
                        WHEN 'regular'  THEN 3
                        WHEN 'mal'      THEN 2
                        WHEN 'muy_mal'  THEN 1
                    END) AS promedio_numerico
                FROM psico_registro_animo
                WHERE id_usuario = ?
                  AND fecha >= CURDATE() - INTERVAL ? DAY
            ");
            $stmt->execute([$idusuario, $dias]);
            $animoStats = $stmt->fetch();

            // 2. Racha actual de registros de ánimo (días consecutivos)
            $stmt = $pdo->prepare("
                SELECT DISTINCT fecha
                FROM   psico_registro_animo
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC
                LIMIT  60
            ");
            $stmt->execute([$idusuario]);
            $fechas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $racha  = calcularRachaDias($fechas);

            // 3. Último test de estrés y depresión
            $stmt = $pdo->prepare("
                SELECT tipo_test, puntaje_total, nivel, created_at
                FROM   psico_test_estres
                WHERE  id_usuario = ?
                ORDER  BY created_at DESC
                LIMIT  2
            ");
            $stmt->execute([$idusuario]);
            $ultimosTests = $stmt->fetchAll();

            // Separar PSS10 y PHQ9
            $ultimoPSS = null;
            $ultimoPHQ = null;
            foreach ($ultimosTests as $t) {
                if ($t['tipo_test'] === 'PSS10' && !$ultimoPSS) $ultimoPSS = $t;
                if ($t['tipo_test'] === 'PHQ9'  && !$ultimoPHQ) $ultimoPHQ = $t;
            }
            // Si los dos primeros son del mismo tipo, buscar más
            if (!$ultimoPSS || !$ultimoPHQ) {
                $stmt = $pdo->prepare("
                    SELECT tipo_test, puntaje_total, nivel, created_at
                    FROM   psico_test_estres
                    WHERE  id_usuario = ?
                    ORDER  BY created_at DESC
                    LIMIT  10
                ");
                $stmt->execute([$idusuario]);
                foreach ($stmt->fetchAll() as $t) {
                    if ($t['tipo_test'] === 'PSS10' && !$ultimoPSS) $ultimoPSS = $t;
                    if ($t['tipo_test'] === 'PHQ9'  && !$ultimoPHQ) $ultimoPHQ = $t;
                }
            }

            // 4. Citas pendientes / próximas
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total,
                       MIN(fecha) AS proxima_fecha,
                       MIN(hora)  AS proxima_hora
                FROM   psico_cita
                WHERE  id_usuario = ?
                  AND  estado NOT IN ('cancelada','atendida')
                  AND  fecha >= CURDATE()
            ");
            $stmt->execute([$idusuario]);
            $citasPendientes = $stmt->fetch();

            // 5. Día de la semana con mejor/peor ánimo
            $stmt = $pdo->prepare("
                SELECT
                    DAYNAME(fecha)  AS dia_semana,
                    DAYOFWEEK(fecha) AS dia_num,
                    AVG(CASE estado_animo
                        WHEN 'muy_bien' THEN 5
                        WHEN 'bien'     THEN 4
                        WHEN 'regular'  THEN 3
                        WHEN 'mal'      THEN 2
                        WHEN 'muy_mal'  THEN 1
                    END) AS promedio
                FROM psico_registro_animo
                WHERE id_usuario = ?
                  AND fecha >= CURDATE() - INTERVAL 90 DAY
                GROUP BY dia_semana, dia_num
                ORDER BY dia_num ASC
            ");
            $stmt->execute([$idusuario]);
            $porDia = $stmt->fetchAll();

            // Calcular texto de tendencia
            $promedio = (float)($animoStats['promedio_numerico'] ?? 3);
            $tendencia = match(true) {
                $promedio >= 4.2 => 'excelente',
                $promedio >= 3.5 => 'buena',
                $promedio >= 2.8 => 'regular',
                $promedio >= 2.0 => 'baja',
                default          => 'critica',
            };

            respuesta([
                'periodo_dias'    => $dias,
                'animo' => [
                    'total_registros'   => (int)$animoStats['total_registros'],
                    'promedio'          => round((float)$animoStats['promedio_numerico'], 2),
                    'tendencia'         => $tendencia,
                    'racha_dias'        => $racha,
                    'distribucion' => [
                        'muy_bien' => (int)$animoStats['muy_bien'],
                        'bien'     => (int)$animoStats['bien'],
                        'regular'  => (int)$animoStats['regular'],
                        'mal'      => (int)$animoStats['mal'],
                        'muy_mal'  => (int)$animoStats['muy_mal'],
                    ],
                    'por_dia_semana' => $porDia,
                ],
                'tests' => [
                    'estres'    => $ultimoPSS,   // PSS-10
                    'depresion' => $ultimoPHQ,   // PHQ-9
                ],
                'citas_pendientes' => [
                    'total'         => (int)$citasPendientes['total'],
                    'proxima_fecha' => $citasPendientes['proxima_fecha'],
                    'proxima_hora'  => $citasPendientes['proxima_hora'],
                ],
            ]);

        // ── NUEVO: Tendencia de ánimo (serie de tiempo) ───────
        case 'tendencia_animo':
            $dias = max(7, min(60, (int)($_GET['dias'] ?? 14)));

            // Un valor por día (el más reciente del día si hay varios)
            $stmt = $pdo->prepare("
                SELECT
                    p.fecha,
                    p.estado_animo,
                    p.emoji,
                    p.factores,
                    CASE p.estado_animo
                        WHEN 'muy_bien' THEN 5
                        WHEN 'bien'     THEN 4
                        WHEN 'regular'  THEN 3
                        WHEN 'mal'      THEN 2
                        WHEN 'muy_mal'  THEN 1
                    END AS valor
                FROM psico_registro_animo p
                INNER JOIN (
                    SELECT fecha, MAX(hora) AS ultima_hora
                    FROM   psico_registro_animo
                    WHERE  id_usuario = ?
                      AND  fecha >= CURDATE() - INTERVAL ? DAY
                    GROUP  BY fecha
                ) sub ON p.fecha = sub.fecha AND p.hora = sub.ultima_hora
                WHERE p.id_usuario = ?
                ORDER BY p.fecha ASC
            ");
            $stmt->execute([$idusuario, $dias, $idusuario]);
            $serie = $stmt->fetchAll();

            // Decodificar factores (JSON) y calcular media móvil de 3 días
            $valores = array_column($serie, 'valor');
            foreach ($serie as $i => &$punto) {
                $punto['factores'] = $punto['factores']
                    ? json_decode($punto['factores'], true)
                    : [];
                // Media móvil 3 días
                $ventana = array_slice($valores, max(0, $i - 1), min(3, $i + 2));
                $punto['media_movil'] = $ventana
                    ? round(array_sum($ventana) / count($ventana), 2)
                    : (float)$punto['valor'];
            }
            unset($punto);

            // Estadísticas de la serie
            $n = count($valores);
            $mediaGlobal = $n ? round(array_sum($valores) / $n, 2) : null;

            respuesta([
                'dias'          => $dias,
                'puntos'        => count($serie),
                'media_global'  => $mediaGlobal,
                'serie'         => $serie,
            ]);

        // ── NUEVO: Reporte completo del período ───────────────
        case 'reporte':
            $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $hasta = $_GET['hasta'] ?? date('Y-m-d');

            // Validar fechas
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');
            if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

            // a. Ánimos del período
            $stmt = $pdo->prepare("
                SELECT fecha, hora, estado_animo, emoji, factores, nota_libre
                FROM   psico_registro_animo
                WHERE  id_usuario = ?
                  AND  fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC, hora ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $animosReporte = $stmt->fetchAll();
            foreach ($animosReporte as &$a) {
                $a['factores'] = $a['factores'] ? json_decode($a['factores'], true) : [];
            }
            unset($a);

            // b. Tests del período
            $stmt = $pdo->prepare("
                SELECT tipo_test, puntaje_total, nivel, created_at
                FROM   psico_test_estres
                WHERE  id_usuario = ?
                  AND  DATE(created_at) BETWEEN ? AND ?
                ORDER  BY created_at ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $testsReporte = $stmt->fetchAll();

            // c. Citas del período
            $stmt = $pdo->prepare("
                SELECT id_cita, tipo_apoyo, fecha, hora, modalidad, estado, folio
                FROM   psico_cita
                WHERE  id_usuario = ?
                  AND  fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $citasReporte = $stmt->fetchAll();

            // d. Progreso: comparar primera mitad vs segunda mitad del período
            $mitad = date('Y-m-d', (strtotime($desde) + strtotime($hasta)) / 2);

            $stmt = $pdo->prepare("
                SELECT
                    AVG(CASE estado_animo
                        WHEN 'muy_bien' THEN 5 WHEN 'bien' THEN 4
                        WHEN 'regular'  THEN 3 WHEN 'mal'  THEN 2
                        WHEN 'muy_mal'  THEN 1
                    END) AS promedio
                FROM psico_registro_animo
                WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute([$idusuario, $desde, $mitad]);
            $promedioInicio = (float)($stmt->fetchColumn() ?? 3);

            $stmt->execute([$idusuario, $mitad, $hasta]);
            $promedioFin = (float)($stmt->fetchColumn() ?? 3);

            $delta      = round($promedioFin - $promedioInicio, 2);
            $tendencia  = $delta > 0.3 ? 'mejora' : ($delta < -0.3 ? 'baja' : 'estable');

            // e. Factores más frecuentes del período
            $factoresFrec = calcularFactoresFrecuentes($pdo, $idusuario, $desde, $hasta, 5);

            // f. Recomendaciones automáticas
            $recomendaciones = generarRecomendaciones(
                $promedioFin,
                $tendencia,
                $testsReporte,
                count($citasReporte)
            );

            respuesta([
                'periodo' => ['desde' => $desde, 'hasta' => $hasta],
                'resumen' => [
                    'total_registros_animo' => count($animosReporte),
                    'total_tests'           => count($testsReporte),
                    'total_citas'           => count($citasReporte),
                    'promedio_inicio'       => round($promedioInicio, 2),
                    'promedio_fin'          => round($promedioFin, 2),
                    'delta'                 => $delta,
                    'tendencia'             => $tendencia,
                ],
                'animos'            => $animosReporte,
                'tests'             => $testsReporte,
                'citas'             => $citasReporte,
                'factores_frecuentes' => $factoresFrec,
                'recomendaciones'   => $recomendaciones,
            ]);

        // ── NUEVO: Resumen histórico de tests ─────────────────
        case 'resumen_tests':
            // Todos los PSS-10
            $stmt = $pdo->prepare("
                SELECT tipo_test, puntaje_total, nivel, created_at
                FROM   psico_test_estres
                WHERE  id_usuario = ?
                ORDER  BY tipo_test, created_at ASC
            ");
            $stmt->execute([$idusuario]);
            $todosTests = $stmt->fetchAll();

            // Separar por tipo y calcular deltas
            $pss = array_values(array_filter($todosTests, fn($t) => $t['tipo_test'] === 'PSS10'));
            $phq = array_values(array_filter($todosTests, fn($t) => $t['tipo_test'] === 'PHQ9'));

            $pss = agregarDeltas($pss);
            $phq = agregarDeltas($phq);

            respuesta([
                'PSS10' => [
                    'historia'      => $pss,
                    'total'         => count($pss),
                    'ultimo'        => end($pss) ?: null,
                    'mejor'         => $pss ? min(array_column($pss, 'puntaje_total')) : null,
                    'peor'          => $pss ? max(array_column($pss, 'puntaje_total')) : null,
                ],
                'PHQ9' => [
                    'historia'      => $phq,
                    'total'         => count($phq),
                    'ultimo'        => end($phq) ?: null,
                    'mejor'         => $phq ? min(array_column($phq, 'puntaje_total')) : null,
                    'peor'          => $phq ? max(array_column($phq, 'puntaje_total')) : null,
                ],
            ]);

        // ── NUEVO: Factores frecuentes ────────────────────────
        case 'factores_frecuentes':
            $dias  = max(7, min(90, (int)($_GET['dias'] ?? 30)));
            $desde = date('Y-m-d', strtotime("-{$dias} days"));
            $hasta = date('Y-m-d');
            $top   = calcularFactoresFrecuentes($pdo, $idusuario, $desde, $hasta, 10);
            respuesta(['dias' => $dias, 'factores' => $top]);

        default:
            error('Acción GET no reconocida.');
    }
}

// ── POST ─────────────────────────────────────────────────────
if ($metReq === 'POST') {
    $b = body();
    $b['id_usuario'] = $ID_AUTENTICADO;

    switch ($accion) {

        // ── EXISTENTE: Agendar cita ───────────────────────────
        case 'cita':
            $req = ['id_usuario','tipo_apoyo','fecha','hora','modalidad'];
            foreach ($req as $campo) {
                if (empty($b[$campo])) error("Campo requerido: $campo");
            }

            $pdo = getDB();
            $dup = $pdo->prepare("
                SELECT id_cita FROM psico_cita
                WHERE  id_usuario = ? AND fecha = ? AND estado NOT IN ('cancelada')
                LIMIT  1
            ");
            $dup->execute([$b['id_usuario'], $b['fecha']]);
            if ($dup->fetch()) error('Ya tienes una cita agendada para esa fecha.');

            $folio = generarFolio('PS');
            $stmt  = $pdo->prepare("
                INSERT INTO psico_cita
                    (id_usuario, tipo_apoyo, fecha, hora, modalidad, notas_usuario, folio)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'], $b['tipo_apoyo'], $b['fecha'],
                $b['hora'], $b['modalidad'],
                $b['notas_usuario'] ?? null, $folio,
            ]);
            respuesta(['folio' => $folio, 'id_cita' => $pdo->lastInsertId()], 201);

        // ── EXISTENTE: Registrar estado de ánimo ─────────────
        case 'animo':
            if (empty($b['id_usuario']) || empty($b['estado_animo']))
                error('id_usuario y estado_animo requeridos.');

            $pdo  = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO psico_registro_animo
                    (id_usuario, fecha, hora, estado_animo, emoji, factores, nota_libre)
                VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'],
                $b['estado_animo'],
                $b['emoji']      ?? null,
                isset($b['factores']) ? json_encode($b['factores']) : null,
                $b['nota_libre'] ?? null,
            ]);
            respuesta(['id_registro' => $pdo->lastInsertId()], 201);

        // ── EXPANDIDO: Guardar test PSS-10 o PHQ-9 ───────────
        case 'test':
            if (empty($b['id_usuario']) || empty($b['respuestas']))
                error('id_usuario y respuestas requeridos.');

            $tipoTest  = strtoupper(trim($b['tipo_test'] ?? 'PSS10'));
            $respuestas = $b['respuestas'];

            if ($tipoTest === 'PSS10') {
                // ── PSS-10: 10 ítems 0-4, ítems 4,5,6,7,8 invertidos (base 0) ──
                if (count($respuestas) !== 10) error('PSS-10 requiere exactamente 10 respuestas (0-4).');
                $positivos = [3, 4, 5, 6, 7];
                $total = 0;
                foreach ($respuestas as $i => $v) {
                    $v = max(0, min(4, (int)$v));
                    $total += in_array($i, $positivos) ? (4 - $v) : $v;
                }
                $nivel = $total <= 13 ? 'bajo' : ($total <= 26 ? 'moderado' : 'alto');

            } elseif ($tipoTest === 'PHQ9') {
                // ── PHQ-9: 9 ítems 0-3 ───────────────────────────────────────
                if (count($respuestas) !== 9) error('PHQ-9 requiere exactamente 9 respuestas (0-3).');
                $total = 0;
                foreach ($respuestas as $v) {
                    $total += max(0, min(3, (int)$v));
                }
                $nivel = match(true) {
                    $total <= 4  => 'minimo',
                    $total <= 9  => 'leve',
                    $total <= 14 => 'moderado',
                    $total <= 19 => 'moderado_severo',
                    default      => 'severo',
                };
            } else {
                error("tipo_test no válido. Usa 'PSS10' o 'PHQ9'.");
            }

            $pdo  = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO psico_test_estres
                    (id_usuario, tipo_test, respuestas, puntaje_total, nivel)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'],
                $tipoTest,
                json_encode($respuestas),
                $total,
                $nivel,
            ]);

            // Interpretación por tipo
            $interpretacion = interpretarTest($tipoTest, $total, $nivel);

            respuesta([
                'id_test'         => $pdo->lastInsertId(),
                'tipo_test'       => $tipoTest,
                'puntaje_total'   => $total,
                'nivel'           => $nivel,
                'interpretacion'  => $interpretacion,
            ], 201);

        default:
            error('Acción POST no reconocida.');
    }
}

// ── PUT ──────────────────────────────────────────────────────
if ($metReq === 'PUT') {
    $b = body();
    $b['id_usuario'] = $ID_AUTENTICADO;

    if ($accion === 'cancelar') {
        if (empty($b['id_cita']) || empty($b['id_usuario']))
            error('id_cita e id_usuario requeridos.');

        $pdo  = getDB();
        $stmt = $pdo->prepare("
            UPDATE psico_cita
            SET    estado = 'cancelada', updated_at = NOW()
            WHERE  id_cita = ? AND id_usuario = ?
        ");
        $stmt->execute([$b['id_cita'], $b['id_usuario']]);
        if (!$stmt->rowCount()) error('Cita no encontrada o no autorizada.', 404);
        respuesta(['mensaje' => 'Cita cancelada correctamente.']);
    }

    error('Acción PUT no reconocida.');
}

error('Método no permitido.', 405);


// ════════════════════════════════════════════════════════════
//  FUNCIONES AUXILIARES
// ════════════════════════════════════════════════════════════

/**
 * Calcula la racha de días consecutivos dado un array de fechas 'Y-m-d'
 * ordenadas de más reciente a más antigua.
 */
function calcularRachaDias(array $fechas): int {
    if (empty($fechas)) return 0;
    $racha = 0;
    $hoy   = new DateTime('today');
    foreach ($fechas as $f) {
        $d = new DateTime($f);
        if ((int)$hoy->diff($d)->days === $racha) {
            $racha++;
            $hoy->modify('-1 day');
        } else {
            break;
        }
    }
    return $racha;
}

/**
 * Extrae y cuenta los factores más frecuentes de psico_registro_animo.factores (JSON array).
 */
function calcularFactoresFrecuentes(PDO $pdo, int $idusuario, string $desde, string $hasta, int $top): array {
    $stmt = $pdo->prepare("
        SELECT factores
        FROM   psico_registro_animo
        WHERE  id_usuario = ?
          AND  fecha BETWEEN ? AND ?
          AND  factores IS NOT NULL
    ");
    $stmt->execute([$idusuario, $desde, $hasta]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $conteo = [];
    foreach ($rows as $raw) {
        $arr = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($arr)) continue;
        foreach ($arr as $factor) {
            $factor = trim((string)$factor);
            if ($factor === '') continue;
            $conteo[$factor] = ($conteo[$factor] ?? 0) + 1;
        }
    }
    arsort($conteo);
    $resultado = [];
    $i = 0;
    foreach ($conteo as $factor => $cantidad) {
        if ($i++ >= $top) break;
        $resultado[] = ['factor' => $factor, 'frecuencia' => $cantidad];
    }
    return $resultado;
}

/**
 * Agrega campo 'delta' a cada elemento (diferencia con el anterior).
 */
function agregarDeltas(array $tests): array {
    for ($i = 0; $i < count($tests); $i++) {
        $tests[$i]['delta'] = $i === 0
            ? null
            : $tests[$i]['puntaje_total'] - $tests[$i - 1]['puntaje_total'];
        $tests[$i]['puntaje_total'] = (int)$tests[$i]['puntaje_total'];
    }
    return $tests;
}

/**
 * Genera texto interpretativo del test según tipo y puntaje.
 */
function interpretarTest(string $tipo, int $puntaje, string $nivel): array {
    if ($tipo === 'PSS10') {
        $textos = [
            'bajo'     => 'Tu nivel de estrés percibido es bajo. Mantén tus hábitos actuales de autocuidado.',
            'moderado' => 'Presentas estrés moderado. Considera técnicas de manejo: respiración, ejercicio o hablar con alguien.',
            'alto'     => 'Tu nivel de estrés es alto. Te recomendamos agendar una cita psicológica pronto.',
        ];
        return [
            'descripcion' => $textos[$nivel] ?? '',
            'rango'       => 'Escala 0-40. Bajo: 0-13 | Moderado: 14-26 | Alto: 27-40',
            'alerta'      => $nivel === 'alto',
        ];
    }

    // PHQ-9
    $textos = [
        'minimo'           => 'Sin síntomas depresivos significativos. ¡Excelente!',
        'leve'             => 'Síntomas depresivos leves. Observa tu estado y practica autocuidado.',
        'moderado'         => 'Síntomas moderados. Se recomienda orientación profesional.',
        'moderado_severo'  => 'Síntomas moderado-severos. Agenda una cita con el área de psicología.',
        'severo'           => 'Síntomas severos. Es importante buscar apoyo profesional pronto.',
    ];
    return [
        'descripcion' => $textos[$nivel] ?? '',
        'rango'       => 'Escala 0-27. Mínimo: 0-4 | Leve: 5-9 | Moderado: 10-14 | Mod-severo: 15-19 | Severo: 20-27',
        'alerta'      => in_array($nivel, ['moderado_severo', 'severo']),
    ];
}

/**
 * Genera recomendaciones automáticas en base al estado del usuario.
 */
function generarRecomendaciones(float $promedio, string $tendencia, array $tests, int $totalCitas): array {
    $recs = [];

    // Por promedio de ánimo
    if ($promedio < 2.5) {
        $recs[] = [
            'tipo'     => 'urgente',
            'mensaje'  => 'Tu estado de ánimo general ha sido bajo. Considera agendar una cita psicológica.',
            'accion'   => 'agendar_cita',
        ];
    } elseif ($promedio < 3.5) {
        $recs[] = [
            'tipo'     => 'sugerencia',
            'mensaje'  => 'Registrar tu ánimo diariamente ayuda a identificar patrones. ¡Sigue así!',
            'accion'   => 'registrar_animo',
        ];
    }

    // Por tendencia
    if ($tendencia === 'baja') {
        $recs[] = [
            'tipo'    => 'atencion',
            'mensaje' => 'Tu ánimo ha bajado en la segunda mitad del período. ¿Algo ha cambiado?',
            'accion'  => 'chat_ia',
        ];
    }

    // Por tests
    foreach ($tests as $t) {
        if ($t['tipo_test'] === 'PSS10' && $t['nivel'] === 'alto') {
            $recs[] = [
                'tipo'    => 'urgente',
                'mensaje' => 'Tu último test PSS-10 indica estrés alto. Habla con un profesional.',
                'accion'  => 'agendar_cita',
            ];
        }
        if ($t['tipo_test'] === 'PHQ9' && in_array($t['nivel'], ['moderado_severo', 'severo'])) {
            $recs[] = [
                'tipo'    => 'urgente',
                'mensaje' => 'Tu PHQ-9 indica síntomas depresivos importantes. Por favor agenda una cita.',
                'accion'  => 'agendar_cita',
            ];
        }
    }

    // Si no hay citas en todo el período
    if ($totalCitas === 0) {
        $recs[] = [
            'tipo'    => 'info',
            'mensaje' => 'Recuerda que tienes acceso a citas psicológicas gratuitas en el campus.',
            'accion'  => 'agendar_cita',
        ];
    }

    // Si no hay recomendaciones urgentes
    if (empty(array_filter($recs, fn($r) => $r['tipo'] === 'urgente'))) {
        $recs[] = [
            'tipo'    => 'positivo',
            'mensaje' => 'Tu bienestar psicológico muestra un estado estable. ¡Sigue cuidándote!',
            'accion'  => null,
        ];
    }

    return $recs;
}
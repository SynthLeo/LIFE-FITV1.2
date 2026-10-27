<?php
// ============================================================
//  modules/clinico/index.php  — v2 (expandido)
//
//  ENDPOINTS EXISTENTES:
//  GET  ?accion=citas&id_usuario=X
//  GET  ?accion=signos&id_usuario=X&dias=30
//  GET  ?accion=evaluaciones&id_usuario=X
//  POST ?accion=cita
//  POST ?accion=signos      → signos vitales + interpretación
//  POST ?accion=sintomas    → semáforo de urgencia
//  PUT  ?accion=cancelar
//
//  NUEVOS ENDPOINTS:
//  GET  ?accion=estadisticas&id_usuario=X&dias=30
//       → Resumen ejecutivo: promedios de signos, alertas activas,
//         citas pendientes, última evaluación de síntomas,
//         tendencia de presión y FC
//
//  GET  ?accion=tendencia_signos&id_usuario=X&dias=30
//       → Serie de tiempo de cada signo vital (para gráficas multi-línea)
//         Con bandas de referencia normal
//
//  GET  ?accion=reporte&id_usuario=X&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
//       → Reporte clínico completo del período:
//         signos vitales + evaluaciones + citas + alertas acumuladas
//
//  GET  ?accion=alertas_activas&id_usuario=X
//       → Signos vitales de las últimas 24h con nivel de alerta
//         Devuelve solo los que NO son 'verde'
//
//  GET  ?accion=historial_sintomas&id_usuario=X&limite=20
//       → Últimas N evaluaciones de síntomas con nivel de urgencia
//         y porcentaje de cada nivel en el total
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
                SELECT * FROM clinico_cita
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC, hora DESC
                LIMIT  20
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Signos vitales ─────────────────────────
        case 'signos':
            $dias = (int)($_GET['dias'] ?? 30);
            $stmt = $pdo->prepare("
                SELECT * FROM clinico_signos_vitales
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                ORDER  BY fecha DESC, hora DESC
                LIMIT  50
            ");
            $stmt->execute([$idusuario, $dias]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Evaluaciones ───────────────────────────
        case 'evaluaciones':
            $stmt = $pdo->prepare("
                SELECT * FROM clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
                ORDER  BY created_at DESC
                LIMIT  10
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── NUEVO: Estadísticas generales ─────────────────────
        case 'estadisticas':
            $dias = max(7, min(90, (int)($_GET['dias'] ?? 30)));

            // 1. Promedios de signos vitales en el período
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)                        AS total_registros,
                    AVG(temperatura_c)              AS temp_promedio,
                    AVG(frecuencia_cardiaca)        AS fc_promedio,
                    AVG(presion_sistolica)          AS ps_promedio,
                    AVG(presion_diastolica)         AS pd_promedio,
                    AVG(saturacion_o2)              AS sat_promedio,
                    AVG(glucosa_mg_dl)              AS gluc_promedio,
                    MIN(presion_sistolica)          AS ps_min,
                    MAX(presion_sistolica)          AS ps_max,
                    MIN(frecuencia_cardiaca)        AS fc_min,
                    MAX(frecuencia_cardiaca)        AS fc_max
                FROM clinico_signos_vitales
                WHERE id_usuario = ?
                  AND fecha >= CURDATE() - INTERVAL ? DAY
            ");
            $stmt->execute([$idusuario, $dias]);
            $promediosSignos = $stmt->fetch();

            // 2. Último registro de signos con su interpretación
            $stmt = $pdo->prepare("
                SELECT * FROM clinico_signos_vitales
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC, hora DESC
                LIMIT  1
            ");
            $stmt->execute([$idusuario]);
            $ultimoSigno = $stmt->fetch();
            if ($ultimoSigno && $ultimoSigno['interpretacion']) {
                $ultimoSigno['interpretacion'] = json_decode($ultimoSigno['interpretacion'], true);
            }

            // 3. Alertas activas (signos NO verdes de los últimos 7 días)
            $stmt = $pdo->prepare("
                SELECT fecha, hora, temperatura_c, frecuencia_cardiaca,
                       presion_sistolica, presion_diastolica,
                       saturacion_o2, glucosa_mg_dl, interpretacion
                FROM   clinico_signos_vitales
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL 7 DAY
                ORDER  BY fecha DESC, hora DESC
                LIMIT  20
            ");
            $stmt->execute([$idusuario]);
            $signosRecientes = $stmt->fetchAll();
            $alertasActivas = contarAlertasActivas($signosRecientes);

            // 4. Distribución de evaluaciones de síntomas en el período
            $stmt = $pdo->prepare("
                SELECT nivel_urgencia, COUNT(*) AS total
                FROM   clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
                  AND  created_at >= NOW() - INTERVAL ? DAY
                GROUP  BY nivel_urgencia
            ");
            $stmt->execute([$idusuario, $dias]);
            $distUrgencia = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'emergencia' => 0];
            foreach ($stmt->fetchAll() as $row) {
                $distUrgencia[$row['nivel_urgencia']] = (int)$row['total'];
            }

            // 5. Citas pendientes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total, MIN(fecha) AS proxima
                FROM   clinico_cita
                WHERE  id_usuario = ?
                  AND  estado NOT IN ('cancelada','atendida')
                  AND  fecha >= CURDATE()
            ");
            $stmt->execute([$idusuario]);
            $citasPend = $stmt->fetch();

            // 6. Clasificar tendencia de presión
            $tendenciaPA = clasificarTendenciaPA(
                $promediosSignos['ps_promedio'],
                $promediosSignos['pd_promedio']
            );

            respuesta([
                'periodo_dias'    => $dias,
                'signos_vitales' => [
                    'total_registros' => (int)$promediosSignos['total_registros'],
                    'promedios'       => [
                        'temperatura'    => $promediosSignos['temp_promedio'] ? round((float)$promediosSignos['temp_promedio'], 1) : null,
                        'frecuencia_card'=> $promediosSignos['fc_promedio']   ? round((float)$promediosSignos['fc_promedio'])      : null,
                        'presion_sist'   => $promediosSignos['ps_promedio']   ? round((float)$promediosSignos['ps_promedio'])      : null,
                        'presion_diast'  => $promediosSignos['pd_promedio']   ? round((float)$promediosSignos['pd_promedio'])      : null,
                        'saturacion_o2'  => $promediosSignos['sat_promedio']  ? round((float)$promediosSignos['sat_promedio'], 1)  : null,
                        'glucosa'        => $promediosSignos['gluc_promedio'] ? round((float)$promediosSignos['gluc_promedio'])    : null,
                    ],
                    'rangos' => [
                        'fc_min'  => $promediosSignos['fc_min'] ? (int)$promediosSignos['fc_min'] : null,
                        'fc_max'  => $promediosSignos['fc_max'] ? (int)$promediosSignos['fc_max'] : null,
                        'ps_min'  => $promediosSignos['ps_min'] ? (int)$promediosSignos['ps_min'] : null,
                        'ps_max'  => $promediosSignos['ps_max'] ? (int)$promediosSignos['ps_max'] : null,
                    ],
                    'tendencia_pa'   => $tendenciaPA,
                    'ultimo_registro'=> $ultimoSigno,
                ],
                'alertas_activas_7d'    => $alertasActivas,
                'distribucion_urgencia' => $distUrgencia,
                'citas_pendientes'      => [
                    'total'   => (int)$citasPend['total'],
                    'proxima' => $citasPend['proxima'],
                ],
            ]);

        // ── NUEVO: Tendencia de signos vitales ────────────────
        case 'tendencia_signos':
            $dias = max(7, min(90, (int)($_GET['dias'] ?? 30)));

            // Un punto por día (el más reciente del día)
            $stmt = $pdo->prepare("
                SELECT
                    sv.fecha,
                    sv.temperatura_c,
                    sv.frecuencia_cardiaca,
                    sv.presion_sistolica,
                    sv.presion_diastolica,
                    sv.saturacion_o2,
                    sv.frecuencia_resp,
                    sv.glucosa_mg_dl
                FROM clinico_signos_vitales sv
                INNER JOIN (
                    SELECT fecha, MAX(hora) AS ultima_hora
                    FROM   clinico_signos_vitales
                    WHERE  id_usuario = ?
                      AND  fecha >= CURDATE() - INTERVAL ? DAY
                    GROUP  BY fecha
                ) sub ON sv.fecha = sub.fecha AND sv.hora = sub.ultima_hora
                WHERE sv.id_usuario = ?
                ORDER BY sv.fecha ASC
            ");
            $stmt->execute([$idusuario, $dias, $idusuario]);
            $serie = $stmt->fetchAll();

            // Tipificar y agregar semáforo por punto
            foreach ($serie as &$p) {
                $p = tiparSignos($p);
                $signosFiltrados = array_filter([
                    'temperatura_c'      => $p['temperatura_c'],
                    'frecuencia_cardiaca'=> $p['frecuencia_cardiaca'],
                    'presion_sistolica'  => $p['presion_sistolica'],
                    'presion_diastolica' => $p['presion_diastolica'],
                    'saturacion_o2'      => $p['saturacion_o2'],
                    'glucosa_mg_dl'      => $p['glucosa_mg_dl'],
                ], fn($v) => $v !== null);

                $interpretacion = interpretarSignosVitales($signosFiltrados);
                $nivelesPresentes = array_column($interpretacion, 'nivel');
                $p['nivel_global'] = calcularNivelGlobal($nivelesPresentes);
            }
            unset($p);

            // Bandas de referencia (para el frontend)
            $bandas = [
                'temperatura'       => ['min_normal' => 36.0, 'max_normal' => 37.4],
                'frecuencia_cardiaca'=> ['min_normal' => 60,   'max_normal' => 100],
                'presion_sistolica' => ['min_normal' => 90,   'max_normal' => 129],
                'presion_diastolica'=> ['min_normal' => 60,   'max_normal' => 79],
                'saturacion_o2'     => ['min_normal' => 95,   'max_normal' => 100],
                'glucosa_mg_dl'     => ['min_normal' => 70,   'max_normal' => 99],
            ];

            respuesta([
                'dias'   => $dias,
                'puntos' => count($serie),
                'serie'  => $serie,
                'bandas' => $bandas,
            ]);

        // ── NUEVO: Alertas activas (últimas 24h) ──────────────
        case 'alertas_activas':
            $stmt = $pdo->prepare("
                SELECT id_signo, fecha, hora, temperatura_c, frecuencia_cardiaca,
                       presion_sistolica, presion_diastolica,
                       saturacion_o2, frecuencia_resp, glucosa_mg_dl, interpretacion
                FROM   clinico_signos_vitales
                WHERE  id_usuario = ?
                  AND  created_at >= NOW() - INTERVAL 24 HOUR
                ORDER  BY created_at DESC
            ");
            $stmt->execute([$idusuario]);
            $signosRecientes = $stmt->fetchAll();

            $alertas = [];
            foreach ($signosRecientes as $s) {
                $interp = $s['interpretacion']
                    ? json_decode($s['interpretacion'], true)
                    : [];
                foreach ($interp as $tipo => $alerta) {
                    if ($alerta['nivel'] !== 'verde') {
                        $alertas[] = [
                            'id_signo'   => $s['id_signo'],
                            'fecha'      => $s['fecha'],
                            'hora'       => $s['hora'],
                            'tipo_signo' => $tipo,
                            'nivel'      => $alerta['nivel'],
                            'mensaje'    => $alerta['msg'],
                        ];
                    }
                }
            }

            // Ordenar por severidad
            $orden = ['emergencia' => 0, 'rojo' => 1, 'amarillo' => 2];
            usort($alertas, fn($a, $b) => ($orden[$a['nivel']] ?? 3) <=> ($orden[$b['nivel']] ?? 3));

            respuesta([
                'total_alertas' => count($alertas),
                'hay_emergencia'=> !empty(array_filter($alertas, fn($a) => $a['nivel'] === 'emergencia')),
                'alertas'       => $alertas,
            ]);

        // ── NUEVO: Historial de síntomas con distribución ─────
        case 'historial_sintomas':
            $limite = max(5, min(50, (int)($_GET['limite'] ?? 20)));
            $stmt   = $pdo->prepare("
                SELECT id_evaluacion, sintomas, dias_duracion,
                       nivel_urgencia, orientacion, created_at
                FROM   clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
                ORDER  BY created_at DESC
                LIMIT  ?
            ");
            $stmt->execute([$idusuario, $limite]);
            $evaluaciones = $stmt->fetchAll();

            // Decodificar síntomas
            foreach ($evaluaciones as &$e) {
                $e['sintomas'] = $e['sintomas']
                    ? json_decode($e['sintomas'], true)
                    : [];
            }
            unset($e);

            // Distribución por nivel de urgencia
            $stmt = $pdo->prepare("
                SELECT nivel_urgencia, COUNT(*) AS total
                FROM   clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
                GROUP  BY nivel_urgencia
            ");
            $stmt->execute([$idusuario]);
            $distribucion = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'emergencia' => 0];
            $totalEval    = 0;
            foreach ($stmt->fetchAll() as $row) {
                $distribucion[$row['nivel_urgencia']] = (int)$row['total'];
                $totalEval += (int)$row['total'];
            }

            // Síntomas más frecuentes de todas las evaluaciones
            $stmt = $pdo->prepare("
                SELECT sintomas
                FROM   clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
            ");
            $stmt->execute([$idusuario]);
            $todosLosRaw = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $conteoSintomas = [];
            foreach ($todosLosRaw as $raw) {
                $arr = json_decode($raw, true) ?? [];
                foreach ($arr as $s) {
                    $s = trim((string)$s);
                    if ($s) $conteoSintomas[$s] = ($conteoSintomas[$s] ?? 0) + 1;
                }
            }
            arsort($conteoSintomas);
            $topSintomas = array_slice(
                array_map(fn($s, $f) => ['sintoma' => $s, 'frecuencia' => $f],
                    array_keys($conteoSintomas), $conteoSintomas),
                0, 8
            );

            respuesta([
                'evaluaciones'      => $evaluaciones,
                'distribucion'      => $distribucion,
                'total_historial'   => $totalEval,
                'sintomas_frecuentes' => $topSintomas,
            ]);

        // ── NUEVO: Reporte clínico completo del período ───────
        case 'reporte':
            $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');
            if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

            // Signos del período
            $stmt = $pdo->prepare("
                SELECT fecha, hora, temperatura_c, frecuencia_cardiaca,
                       presion_sistolica, presion_diastolica,
                       saturacion_o2, frecuencia_resp, glucosa_mg_dl, interpretacion
                FROM   clinico_signos_vitales
                WHERE  id_usuario = ?
                  AND  fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC, hora ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $signosPeriodo = $stmt->fetchAll();
            foreach ($signosPeriodo as &$s) {
                $s = tiparSignos($s);
                $s['interpretacion'] = $s['interpretacion']
                    ? json_decode($s['interpretacion'], true)
                    : null;
            }
            unset($s);

            // Promedios del período
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)                    AS total,
                    AVG(temperatura_c)          AS temp_prom,
                    AVG(frecuencia_cardiaca)    AS fc_prom,
                    AVG(presion_sistolica)      AS ps_prom,
                    AVG(presion_diastolica)     AS pd_prom,
                    AVG(saturacion_o2)          AS sat_prom,
                    AVG(glucosa_mg_dl)          AS gluc_prom
                FROM clinico_signos_vitales
                WHERE id_usuario = ?
                  AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $prom = $stmt->fetch();

            // Evaluaciones del período
            $stmt = $pdo->prepare("
                SELECT nivel_urgencia, orientacion, created_at,
                       sintomas, dias_duracion
                FROM   clinico_evaluacion_sintomas
                WHERE  id_usuario = ?
                  AND  DATE(created_at) BETWEEN ? AND ?
                ORDER  BY created_at ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $evaluacionesPer = $stmt->fetchAll();
            foreach ($evaluacionesPer as &$e) {
                $e['sintomas'] = $e['sintomas'] ? json_decode($e['sintomas'], true) : [];
            }
            unset($e);

            // Citas del período
            $stmt = $pdo->prepare("
                SELECT id_cita, tipo_consulta, fecha, hora, estado, folio
                FROM   clinico_cita
                WHERE  id_usuario = ?
                  AND  fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $citasPer = $stmt->fetchAll();

            // Alertas generadas en el período
            $alertas = [];
            foreach ($signosPeriodo as $s) {
                if (!$s['interpretacion']) continue;
                foreach ($s['interpretacion'] as $tipo => $alerta) {
                    if ($alerta['nivel'] !== 'verde') {
                        $alertas[] = [
                            'fecha'      => $s['fecha'],
                            'hora'       => $s['hora'],
                            'tipo_signo' => $tipo,
                            'nivel'      => $alerta['nivel'],
                            'mensaje'    => $alerta['msg'],
                        ];
                    }
                }
            }

            respuesta([
                'periodo'    => ['desde' => $desde, 'hasta' => $hasta],
                'resumen'    => [
                    'total_signos'      => (int)$prom['total'],
                    'total_evaluaciones'=> count($evaluacionesPer),
                    'total_citas'       => count($citasPer),
                    'total_alertas'     => count($alertas),
                    'promedios'         => [
                        'temperatura'    => $prom['temp_prom'] ? round((float)$prom['temp_prom'], 1) : null,
                        'frecuencia_card'=> $prom['fc_prom']   ? round((float)$prom['fc_prom'])      : null,
                        'presion_sist'   => $prom['ps_prom']   ? round((float)$prom['ps_prom'])      : null,
                        'presion_diast'  => $prom['pd_prom']   ? round((float)$prom['pd_prom'])      : null,
                        'saturacion_o2'  => $prom['sat_prom']  ? round((float)$prom['sat_prom'], 1)  : null,
                        'glucosa'        => $prom['gluc_prom'] ? round((float)$prom['gluc_prom'])    : null,
                    ],
                ],
                'signos'      => $signosPeriodo,
                'evaluaciones'=> $evaluacionesPer,
                'citas'       => $citasPer,
                'alertas'     => $alertas,
            ]);

        default:
            error('Acción GET no reconocida.');
    }
}

// ── POST ─────────────────────────────────────────────────────
if ($metReq === 'POST') {
    $b   = body();
    $b['id_usuario'] = $ID_AUTENTICADO;
    $pdo = getDB();

    switch ($accion) {

        case 'cita':
            foreach (['id_usuario','tipo_consulta','fecha','hora'] as $campo) {
                if (empty($b[$campo])) error("Campo requerido: $campo");
            }
            $folio = generarFolio('CL');
            $stmt  = $pdo->prepare("
                INSERT INTO clinico_cita
                    (id_usuario, tipo_consulta, fecha, hora, notas_usuario, folio)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'], $b['tipo_consulta'],
                $b['fecha'], $b['hora'],
                $b['notas_usuario'] ?? null, $folio,
            ]);
            respuesta(['folio' => $folio, 'id_cita' => $pdo->lastInsertId()], 201);

        case 'signos':
            if (empty($b['id_usuario'])) error('id_usuario requerido.');
            $signos = [
                'temperatura_c'      => $b['temperatura_c']       ?? null,
                'frecuencia_cardiaca'=> $b['frecuencia_cardiaca']  ?? null,
                'presion_sistolica'  => $b['presion_sistolica']    ?? null,
                'presion_diastolica' => $b['presion_diastolica']   ?? null,
                'saturacion_o2'      => $b['saturacion_o2']        ?? null,
                'frecuencia_resp'    => $b['frecuencia_resp']      ?? null,
                'glucosa_mg_dl'      => $b['glucosa_mg_dl']        ?? null,
            ];
            $signosFiltrados = array_filter($signos, fn($v) => $v !== null);
            if (empty($signosFiltrados)) error('Ingresa al menos un signo vital.');

            $interpretacion = interpretarSignosVitales($signosFiltrados);

            $stmt = $pdo->prepare("
                INSERT INTO clinico_signos_vitales
                    (id_usuario, fecha, hora,
                     temperatura_c, frecuencia_cardiaca,
                     presion_sistolica, presion_diastolica,
                     saturacion_o2, frecuencia_resp, glucosa_mg_dl, interpretacion)
                VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'],
                $signos['temperatura_c'],
                $signos['frecuencia_cardiaca'],
                $signos['presion_sistolica'],
                $signos['presion_diastolica'],
                $signos['saturacion_o2'],
                $signos['frecuencia_resp'],
                $signos['glucosa_mg_dl'],
                json_encode($interpretacion),
            ]);

            // Nivel global de alerta
            $niveles     = array_column($interpretacion, 'nivel');
            $nivelGlobal = calcularNivelGlobal($niveles);

            respuesta([
                'id_signo'       => $pdo->lastInsertId(),
                'interpretacion' => $interpretacion,
                'nivel_global'   => $nivelGlobal,
            ], 201);

        case 'sintomas':
            if (empty($b['id_usuario']) || empty($b['sintomas']))
                error('id_usuario y sintomas requeridos.');
            $sintomas = $b['sintomas'];
            $dias     = $b['dias_duracion'] ?? 'Hoy';
            $urgencia = nivelUrgenciaSintomas($sintomas, $dias);

            $orientaciones = [
                'verde'      => '🟢 Puedes manejarlo en casa. Reposa, hidrátate y observa tu evolución. Si empeora, agenda una cita.',
                'amarillo'   => '🟡 Te recomendamos agendar una consulta médica esta semana para una evaluación presencial.',
                'rojo'       => '🔴 Acude al médico HOY. Estos síntomas requieren atención profesional pronto.',
                'emergencia' => '🚨 Ve a urgencias AHORA o llama al 911. No esperes.',
            ];
            $orientacion = $orientaciones[$urgencia];

            $stmt = $pdo->prepare("
                INSERT INTO clinico_evaluacion_sintomas
                    (id_usuario, sintomas, dias_duracion, nivel_urgencia, orientacion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'],
                json_encode($sintomas),
                $dias,
                $urgencia,
                $orientacion,
            ]);
            respuesta([
                'id_evaluacion'  => $pdo->lastInsertId(),
                'nivel_urgencia' => $urgencia,
                'orientacion'    => $orientacion,
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
            UPDATE clinico_cita
            SET    estado = 'cancelada', updated_at = NOW()
            WHERE  id_cita = ? AND id_usuario = ?
        ");
        $stmt->execute([$b['id_cita'], $b['id_usuario']]);
        if (!$stmt->rowCount()) error('Cita no encontrada.', 404);
        respuesta(['mensaje' => 'Cita cancelada.']);
    }
    error('Acción PUT no reconocida.');
}

error('Método no permitido.', 405);


// ════════════════════════════════════════════════════════════
//  FUNCIONES AUXILIARES
// ════════════════════════════════════════════════════════════

/**
 * Convierte strings de signos vitales a sus tipos numéricos correctos.
 */
function tiparSignos(array $s): array {
    foreach (['temperatura_c','saturacion_o2','frecuencia_resp'] as $f) {
        if (isset($s[$f])) $s[$f] = $s[$f] !== null ? (float)$s[$f] : null;
    }
    foreach (['frecuencia_cardiaca','presion_sistolica','presion_diastolica','glucosa_mg_dl'] as $f) {
        if (isset($s[$f])) $s[$f] = $s[$f] !== null ? (int)$s[$f] : null;
    }
    return $s;
}

/**
 * Determina el nivel global de alerta a partir de una lista de niveles individuales.
 * Prioridad: emergencia > rojo > amarillo > verde
 */
function calcularNivelGlobal(array $niveles): string {
    if (in_array('emergencia', $niveles)) return 'emergencia';
    if (in_array('rojo',       $niveles)) return 'rojo';
    if (in_array('amarillo',   $niveles)) return 'amarillo';
    return 'verde';
}

/**
 * Cuenta las alertas activas (no verdes) en un conjunto de signos recientes.
 */
function contarAlertasActivas(array $signos): array {
    $conteo = ['emergencia' => 0, 'rojo' => 0, 'amarillo' => 0, 'total' => 0];
    foreach ($signos as $s) {
        $interp = $s['interpretacion']
            ? json_decode($s['interpretacion'], true)
            : [];
        foreach ($interp as $alerta) {
            if ($alerta['nivel'] !== 'verde') {
                $conteo[$alerta['nivel']] = ($conteo[$alerta['nivel']] ?? 0) + 1;
                $conteo['total']++;
            }
        }
    }
    return $conteo;
}

/**
 * Clasifica la tendencia de presión arterial a partir de promedios.
 */
function clasificarTendenciaPA(?string $psSist, ?string $psDiast): ?array {
    if ($psSist === null || $psDiast === null) return null;
    $s = (int)$psSist;
    $d = (int)$psDiast;

    $categoria = match(true) {
        $s < 90 || $d < 60  => 'hipotension',
        $s < 120 && $d < 80 => 'optima',
        $s < 130 && $d < 80 => 'normal',
        $s < 140 || $d < 90 => 'normal_alta',
        $s < 180            => 'hipertension_I',
        default             => 'hipertension_II',
    };

    $mensajes = [
        'hipotension'    => 'Presión baja en promedio. Mantente hidratado.',
        'optima'         => 'Presión arterial óptima en el período.',
        'normal'         => 'Presión arterial normal.',
        'normal_alta'    => 'Presión normal-alta. Monitorea regularmente.',
        'hipertension_I' => 'Hipertensión grado I en promedio. Consulta con médico.',
        'hipertension_II'=> 'Hipertensión grado II. Atención médica recomendada.',
    ];

    return [
        'sistolica_prom'  => $s,
        'diastolica_prom' => $d,
        'categoria'       => $categoria,
        'mensaje'         => $mensajes[$categoria],
    ];
}
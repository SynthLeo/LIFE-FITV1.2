<?php
// ============================================================
//  modules/alimentacion/index.php  — v2 (expandido)
//
//  ENDPOINTS EXISTENTES:
//  GET  ?accion=citas&id_usuario=X
//  GET  ?accion=registros&id_usuario=X&dias=7
//  GET  ?accion=planes&id_usuario=X
//  GET  ?accion=imc&id_usuario=X
//  POST ?accion=cita
//  POST ?accion=registro
//  POST ?accion=plan
//  PUT  ?accion=cancelar
//
//  NUEVOS ENDPOINTS:
//  GET  ?accion=estadisticas&id_usuario=X&dias=30
//       → Resumen ejecutivo: promedios calóricos, macro promedio,
//         hidratación media, adherencia (días registrados vs total),
//         evolución de peso e IMC, racha de registros
//
//  GET  ?accion=tendencia_macros&id_usuario=X&dias=14
//       → Serie diaria de calorías, proteína, carbs, grasa, agua
//         (para gráfica de área apilada)
//
//  GET  ?accion=reporte&id_usuario=X&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
//       → Reporte completo del período:
//         resumen macro, evolución IMC/peso, citas, alertas
//
//  GET  ?accion=evolucion_peso&id_usuario=X&dias=60
//       → Serie de tiempo de peso_kg e IMC diarios
//
//  GET  ?accion=adherencia&id_usuario=X&dias=30
//       → % de días con registro, racha actual, días sin registrar
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
                SELECT * FROM nutri_cita
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC
                LIMIT  20
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Registros ──────────────────────────────
        case 'registros':
            $dias = (int)($_GET['dias'] ?? 7);
            $stmt = $pdo->prepare("
                SELECT * FROM nutri_registro_diario
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                ORDER  BY fecha DESC
            ");
            $stmt->execute([$idusuario, $dias]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: Planes ─────────────────────────────────
        case 'planes':
            $stmt = $pdo->prepare("
                SELECT * FROM nutri_plan
                WHERE  id_usuario = ? AND activo = 1
                ORDER  BY created_at DESC
                LIMIT  5
            ");
            $stmt->execute([$idusuario]);
            respuesta($stmt->fetchAll());

        // ── EXISTENTE: IMC + TMB ──────────────────────────────
        case 'imc':
            $stmt = $pdo->prepare("SELECT peso_kg, altura_cm, fecha_nacimiento, sexo_biologico FROM usuario WHERE id_usuario = ?");
            $stmt->execute([$idusuario]);
            $u = $stmt->fetch();
            if (!$u || !$u['peso_kg'] || !$u['altura_cm']) error('Datos de peso/estatura incompletos. Completa tu perfil.');

            $edad   = calcularEdad($u['fecha_nacimiento'] ?? null);
            $imc    = calcularIMC((float)$u['peso_kg'], (int)$u['altura_cm']);
            $catIMC = categoriaIMC($imc);
            $tmb    = calcularTMB((float)$u['peso_kg'], (int)$u['altura_cm'], $edad, $u['sexo_biologico'] ?? null);

            respuesta([
                'peso_kg'           => $u['peso_kg'],
                'altura_cm'         => $u['altura_cm'],
                'edad'              => $edad,
                'imc'               => $imc,
                'categoria'         => $catIMC,
                'tmb'               => round($tmb),
                'get_sedentario'    => round($tmb * 1.2),
                'get_ligero'        => round($tmb * 1.375),
                'get_moderado'      => round($tmb * 1.55),
                'get_activo'        => round($tmb * 1.725),
                'get_muy_activo'    => round($tmb * 1.9),
            ]);

        // ── NUEVO: Estadísticas generales ────────────────────
        case 'estadisticas':
            $dias = max(7, min(90, (int)($_GET['dias'] ?? 30)));

            // 1. Promedios de macros y agua en el período
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)                              AS dias_registrados,
                    AVG(calorias_estimadas)               AS cal_promedio,
                    AVG(proteina_g)                       AS prot_promedio,
                    AVG(carbohidratos_g)                  AS carb_promedio,
                    AVG(grasa_g)                          AS grasa_promedio,
                    AVG(agua_vasos)                       AS agua_promedio,
                    MAX(calorias_estimadas)               AS cal_max,
                    MIN(calorias_estimadas)               AS cal_min
                FROM nutri_registro_diario
                WHERE id_usuario = ?
                  AND fecha >= CURDATE() - INTERVAL ? DAY
            ");
            $stmt->execute([$idusuario, $dias]);
            $macros = $stmt->fetch();

            // 2. Porcentaje de adherencia (días con registro vs días transcurridos)
            $diasTranscurridos = $dias;
            $diasRegistrados   = (int)$macros['dias_registrados'];
            $adherencia        = $diasTranscurridos > 0
                ? round(($diasRegistrados / $diasTranscurridos) * 100, 1)
                : 0;

            // 3. Racha de registros consecutivos
            $stmt = $pdo->prepare("
                SELECT DISTINCT fecha
                FROM   nutri_registro_diario
                WHERE  id_usuario = ?
                ORDER  BY fecha DESC
                LIMIT  60
            ");
            $stmt->execute([$idusuario]);
            $fechasNutri = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $racha = calcularRachaDias($fechasNutri);

            // 4. Evolución de peso: primero y último peso registrado en el período
            $stmt = $pdo->prepare("
                SELECT peso_kg, imc, fecha
                FROM   nutri_registro_diario
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                  AND  peso_kg IS NOT NULL
                ORDER  BY fecha ASC
                LIMIT  1
            ");
            $stmt->execute([$idusuario, $dias]);
            $pesoInicio = $stmt->fetch();

            $stmt = $pdo->prepare("
                SELECT peso_kg, imc, fecha
                FROM   nutri_registro_diario
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                  AND  peso_kg IS NOT NULL
                ORDER  BY fecha DESC
                LIMIT  1
            ");
            $stmt->execute([$idusuario, $dias]);
            $pesoFin = $stmt->fetch();

            $deltaPeso = ($pesoInicio && $pesoFin)
                ? round((float)$pesoFin['peso_kg'] - (float)$pesoInicio['peso_kg'], 2)
                : null;

            // 5. Plan activo
            $stmt = $pdo->prepare("
                SELECT nombre, objetivo, calorias_objetivo
                FROM   nutri_plan
                WHERE  id_usuario = ? AND activo = 1
                ORDER  BY created_at DESC
                LIMIT  1
            ");
            $stmt->execute([$idusuario]);
            $planActivo = $stmt->fetch() ?: null;

            // 6. Meta calórica vs real (si hay plan)
            $brechaCalórica = null;
            if ($planActivo && $planActivo['calorias_objetivo'] && $macros['cal_promedio']) {
                $brechaCalórica = round((float)$macros['cal_promedio'] - (float)$planActivo['calorias_objetivo'], 0);
            }

            // 7. Citas nutricionistas pendientes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total, MIN(fecha) AS proxima
                FROM   nutri_cita
                WHERE  id_usuario = ?
                  AND  estado NOT IN ('cancelada','atendida')
                  AND  fecha >= CURDATE()
            ");
            $stmt->execute([$idusuario]);
            $citasPend = $stmt->fetch();

            respuesta([
                'periodo_dias'  => $dias,
                'macros' => [
                    'cal_promedio'   => $macros['cal_promedio']   ? round((float)$macros['cal_promedio'])   : null,
                    'prot_promedio'  => $macros['prot_promedio']  ? round((float)$macros['prot_promedio'],1): null,
                    'carb_promedio'  => $macros['carb_promedio']  ? round((float)$macros['carb_promedio'],1): null,
                    'grasa_promedio' => $macros['grasa_promedio'] ? round((float)$macros['grasa_promedio'],1): null,
                    'agua_promedio'  => $macros['agua_promedio']  ? round((float)$macros['agua_promedio'],1): null,
                    'cal_max'        => $macros['cal_max']        ? (int)$macros['cal_max']                 : null,
                    'cal_min'        => $macros['cal_min']        ? (int)$macros['cal_min']                 : null,
                ],
                'adherencia' => [
                    'dias_registrados'    => $diasRegistrados,
                    'dias_transcurridos'  => $diasTranscurridos,
                    'porcentaje'          => $adherencia,
                    'racha_dias'          => $racha,
                ],
                'peso' => [
                    'inicio'      => $pesoInicio,
                    'actual'      => $pesoFin,
                    'delta_kg'    => $deltaPeso,
                    'tendencia'   => $deltaPeso === null ? null
                        : ($deltaPeso > 0.5 ? 'subida' : ($deltaPeso < -0.5 ? 'bajada' : 'estable')),
                ],
                'plan_activo'       => $planActivo,
                'brecha_calorica'   => $brechaCalórica,
                'citas_pendientes'  => [
                    'total'   => (int)$citasPend['total'],
                    'proxima' => $citasPend['proxima'],
                ],
            ]);

        // ── NUEVO: Tendencia de macros (serie diaria) ─────────
        case 'tendencia_macros':
            $dias = max(7, min(60, (int)($_GET['dias'] ?? 14)));
            $stmt = $pdo->prepare("
                SELECT
                    fecha,
                    calorias_estimadas  AS calorias,
                    proteina_g          AS proteina,
                    carbohidratos_g     AS carbohidratos,
                    grasa_g             AS grasa,
                    agua_vasos          AS agua,
                    peso_kg,
                    imc
                FROM nutri_registro_diario
                WHERE id_usuario = ?
                  AND fecha >= CURDATE() - INTERVAL ? DAY
                ORDER BY fecha ASC
            ");
            $stmt->execute([$idusuario, $dias]);
            $serie = $stmt->fetchAll();

            // Calcular medias móviles de calorías (3 días)
            $cals = array_column($serie, 'calorias');
            foreach ($serie as $i => &$p) {
                $ventana = array_slice($cals, max(0, $i - 1), 3);
                $p['cal_media_movil'] = $ventana
                    ? round(array_sum(array_filter($ventana)) / count(array_filter($ventana)))
                    : null;
                // Tipificar valores numéricos
                foreach (['calorias','proteina','carbohidratos','grasa'] as $campo) {
                    $p[$campo] = $p[$campo] !== null ? (float)$p[$campo] : null;
                }
                $p['agua']    = (int)($p['agua'] ?? 0);
                $p['peso_kg'] = $p['peso_kg'] !== null ? (float)$p['peso_kg'] : null;
                $p['imc']     = $p['imc']     !== null ? (float)$p['imc']     : null;
            }
            unset($p);

            // Promedios del período
            $promedios = [];
            foreach (['calorias','proteina','carbohidratos','grasa','agua'] as $campo) {
                $vals = array_filter(array_column($serie, $campo), fn($v) => $v !== null);
                $promedios[$campo] = $vals ? round(array_sum($vals) / count($vals), 1) : null;
            }

            respuesta([
                'dias'      => $dias,
                'puntos'    => count($serie),
                'promedios' => $promedios,
                'serie'     => $serie,
            ]);

        // ── NUEVO: Evolución de peso e IMC ────────────────────
        case 'evolucion_peso':
            $dias = max(7, min(120, (int)($_GET['dias'] ?? 60)));
            $stmt = $pdo->prepare("
                SELECT fecha, peso_kg, imc
                FROM   nutri_registro_diario
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                  AND  peso_kg IS NOT NULL
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $dias]);
            $serie = $stmt->fetchAll();

            // Agregar categoría IMC por punto
            foreach ($serie as &$p) {
                $p['peso_kg']       = (float)$p['peso_kg'];
                $p['imc']           = $p['imc'] ? (float)$p['imc'] : null;
                $p['categoria_imc'] = $p['imc'] ? categoriaIMC($p['imc']) : null;
            }
            unset($p);

            $pesos = array_column($serie, 'peso_kg');
            respuesta([
                'dias'     => $dias,
                'puntos'   => count($serie),
                'peso_min' => $pesos ? min($pesos) : null,
                'peso_max' => $pesos ? max($pesos) : null,
                'serie'    => $serie,
            ]);

        // ── NUEVO: Adherencia al registro ─────────────────────
        case 'adherencia':
            $dias = max(7, min(90, (int)($_GET['dias'] ?? 30)));

            $stmt = $pdo->prepare("
                SELECT fecha
                FROM   nutri_registro_diario
                WHERE  id_usuario = ?
                  AND  fecha >= CURDATE() - INTERVAL ? DAY
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $dias]);
            $fechasReg = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $setFechas = array_flip($fechasReg);

            // Construir calendario del período
            $calendario = [];
            for ($i = $dias - 1; $i >= 0; $i--) {
                $fecha = date('Y-m-d', strtotime("-{$i} days"));
                $calendario[] = [
                    'fecha'       => $fecha,
                    'registrado'  => isset($setFechas[$fecha]),
                    'dia_semana'  => date('N', strtotime($fecha)), // 1=Lun, 7=Dom
                ];
            }

            $totalRegistrados = count($fechasReg);
            $racha = calcularRachaDias(array_reverse($fechasReg));

            // Mejor semana (7 días con más registros)
            $mejorRacha = 0;
            $rachaTemp  = 0;
            foreach ($calendario as $dia) {
                if ($dia['registrado']) {
                    $rachaTemp++;
                    $mejorRacha = max($mejorRacha, $rachaTemp);
                } else {
                    $rachaTemp = 0;
                }
            }

            respuesta([
                'dias'               => $dias,
                'dias_registrados'   => $totalRegistrados,
                'dias_sin_registro'  => $dias - $totalRegistrados,
                'porcentaje'         => round($totalRegistrados / $dias * 100, 1),
                'racha_actual'       => $racha,
                'mejor_racha'        => $mejorRacha,
                'calendario'         => $calendario,
            ]);

        // ── NUEVO: Reporte completo del período ───────────────
        case 'reporte':
            $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');
            if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

            // Macros del período
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)                    AS dias_registrados,
                    AVG(calorias_estimadas)     AS cal_promedio,
                    AVG(proteina_g)             AS prot_promedio,
                    AVG(carbohidratos_g)        AS carb_promedio,
                    AVG(grasa_g)                AS grasa_promedio,
                    AVG(agua_vasos)             AS agua_promedio,
                    MIN(peso_kg)                AS peso_min,
                    MAX(peso_kg)                AS peso_max,
                    MIN(imc)                    AS imc_min,
                    MAX(imc)                    AS imc_max
                FROM nutri_registro_diario
                WHERE id_usuario = ?
                  AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $resumenMacros = $stmt->fetch();

            // Registros individuales
            $stmt = $pdo->prepare("
                SELECT fecha, calorias_estimadas, proteina_g, carbohidratos_g,
                       grasa_g, agua_vasos, peso_kg, imc
                FROM   nutri_registro_diario
                WHERE  id_usuario = ? AND fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $registros = $stmt->fetchAll();

            // Citas del período
            $stmt = $pdo->prepare("
                SELECT id_cita, motivo, fecha, hora, estado, folio
                FROM   nutri_cita
                WHERE  id_usuario = ? AND fecha BETWEEN ? AND ?
                ORDER  BY fecha ASC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $citas = $stmt->fetchAll();

            // Planes creados en el período
            $stmt = $pdo->prepare("
                SELECT nombre, objetivo, calorias_objetivo, duracion_semanas, generado_ia, created_at
                FROM   nutri_plan
                WHERE  id_usuario = ? AND DATE(created_at) BETWEEN ? AND ?
                ORDER  BY created_at DESC
            ");
            $stmt->execute([$idusuario, $desde, $hasta]);
            $planes = $stmt->fetchAll();

            // Alertas automáticas
            $alertas = generarAlertasNutricionales($resumenMacros, $registros);

            // Días transcurridos del período
            $diasPeriodo = max(1, (int)((strtotime($hasta) - strtotime($desde)) / 86400) + 1);
            $adherencia  = $diasPeriodo > 0
                ? round((int)$resumenMacros['dias_registrados'] / $diasPeriodo * 100, 1)
                : 0;

            respuesta([
                'periodo'     => ['desde' => $desde, 'hasta' => $hasta, 'dias' => $diasPeriodo],
                'resumen' => [
                    'dias_registrados' => (int)$resumenMacros['dias_registrados'],
                    'adherencia_pct'   => $adherencia,
                    'cal_promedio'     => $resumenMacros['cal_promedio'] ? round((float)$resumenMacros['cal_promedio']) : null,
                    'prot_promedio'    => $resumenMacros['prot_promedio'] ? round((float)$resumenMacros['prot_promedio'], 1) : null,
                    'carb_promedio'    => $resumenMacros['carb_promedio'] ? round((float)$resumenMacros['carb_promedio'], 1) : null,
                    'grasa_promedio'   => $resumenMacros['grasa_promedio'] ? round((float)$resumenMacros['grasa_promedio'], 1) : null,
                    'agua_promedio'    => $resumenMacros['agua_promedio'] ? round((float)$resumenMacros['agua_promedio'], 1) : null,
                    'peso_min'         => $resumenMacros['peso_min'] ? (float)$resumenMacros['peso_min'] : null,
                    'peso_max'         => $resumenMacros['peso_max'] ? (float)$resumenMacros['peso_max'] : null,
                ],
                'registros' => $registros,
                'citas'     => $citas,
                'planes'    => $planes,
                'alertas'   => $alertas,
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
            foreach (['id_usuario','motivo','fecha','hora'] as $campo) {
                if (empty($b[$campo])) error("Campo requerido: $campo");
            }
            $folio = generarFolio('NU');
            $stmt  = $pdo->prepare("
                INSERT INTO nutri_cita
                    (id_usuario, motivo, fecha, hora, notas_usuario, folio)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'], $b['motivo'], $b['fecha'],
                $b['hora'], $b['notas_usuario'] ?? null, $folio,
            ]);
            respuesta(['folio' => $folio, 'id_cita' => $pdo->lastInsertId()], 201);

        case 'registro':
            if (empty($b['id_usuario'])) error('id_usuario requerido.');
            $imc  = null;
            $peso = $b['peso_kg'] ?? null;
            if ($peso) {
                $stmt = $pdo->prepare("SELECT altura_cm FROM usuario WHERE id_usuario = ?");
                $stmt->execute([$b['id_usuario']]);
                $u = $stmt->fetch();
                if ($u && $u['altura_cm']) $imc = calcularIMC((float)$peso, (int)$u['altura_cm']);
            }

            $stmt = $pdo->prepare("
                INSERT INTO nutri_registro_diario
                    (id_usuario, fecha, desayuno, comida, cena, snacks,
                     agua_vasos, calorias_estimadas, proteina_g,
                     carbohidratos_g, grasa_g, imc, peso_kg)
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    desayuno           = VALUES(desayuno),
                    comida             = VALUES(comida),
                    cena               = VALUES(cena),
                    snacks             = VALUES(snacks),
                    agua_vasos         = VALUES(agua_vasos),
                    calorias_estimadas = VALUES(calorias_estimadas),
                    proteina_g         = VALUES(proteina_g),
                    carbohidratos_g    = VALUES(carbohidratos_g),
                    grasa_g            = VALUES(grasa_g),
                    imc                = VALUES(imc),
                    peso_kg            = VALUES(peso_kg),
                    updated_at         = NOW()
            ");
            $stmt->execute([
                $b['id_usuario'],
                isset($b['desayuno']) ? json_encode($b['desayuno']) : null,
                isset($b['comida'])   ? json_encode($b['comida'])   : null,
                isset($b['cena'])     ? json_encode($b['cena'])     : null,
                isset($b['snacks'])   ? json_encode($b['snacks'])   : null,
                (int)($b['agua_vasos']       ?? 0),
                $b['calorias_estimadas']      ?? null,
                $b['proteina_g']              ?? null,
                $b['carbohidratos_g']         ?? null,
                $b['grasa_g']                 ?? null,
                $imc,
                $peso,
            ]);
            respuesta(['mensaje' => 'Registro guardado.', 'imc' => $imc], 201);

        case 'plan':
            foreach (['id_usuario','nombre','objetivo','contenido'] as $campo) {
                if (empty($b[$campo])) error("Campo requerido: $campo");
            }
            $pdo->prepare("UPDATE nutri_plan SET activo = 0 WHERE id_usuario = ?")
                ->execute([$b['id_usuario']]);

            $stmt = $pdo->prepare("
                INSERT INTO nutri_plan
                    (id_usuario, nombre, objetivo, calorias_objetivo,
                     duracion_semanas, contenido, generado_ia)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $b['id_usuario'], $b['nombre'], $b['objetivo'],
                $b['calorias_objetivo'] ?? null,
                $b['duracion_semanas']  ?? 4,
                json_encode($b['contenido']),
                (int)($b['generado_ia'] ?? 0),
            ]);
            respuesta(['id_plan' => $pdo->lastInsertId()], 201);

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
            UPDATE nutri_cita
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
 * Genera alertas automáticas a partir del resumen de macros del período.
 */
function generarAlertasNutricionales(array $resumen, array $registros): array {
    $alertas = [];

    // Hidratación baja
    $aguaPromedio = (float)($resumen['agua_promedio'] ?? 0);
    if ($aguaPromedio > 0 && $aguaPromedio < 6) {
        $alertas[] = [
            'tipo'    => 'hidratacion',
            'nivel'   => 'atencion',
            'mensaje' => "Promedio de {$aguaPromedio} vasos/día. Se recomiendan al menos 8 vasos (2L).",
        ];
    }

    // Proteína baja (< 0.8g/kg — estimación conservadora)
    $protPromedio = (float)($resumen['prot_promedio'] ?? 0);
    if ($protPromedio > 0 && $protPromedio < 40) {
        $alertas[] = [
            'tipo'    => 'proteina',
            'nivel'   => 'sugerencia',
            'mensaje' => "Consumo promedio de proteína bajo ({$protPromedio}g). Considera legumbres, huevo o pollo.",
        ];
    }

    // Calorías muy bajas (< 1200)
    $calPromedio = (float)($resumen['cal_promedio'] ?? 0);
    if ($calPromedio > 0 && $calPromedio < 1200) {
        $alertas[] = [
            'tipo'    => 'calorias',
            'nivel'   => 'urgente',
            'mensaje' => "Promedio calórico muy bajo ({$calPromedio} kcal). Consulta a un nutriólogo.",
        ];
    }

    // Pocos días de registro (baja adherencia)
    $diasReg = (int)($resumen['dias_registrados'] ?? 0);
    if ($diasReg < 5) {
        $alertas[] = [
            'tipo'    => 'adherencia',
            'nivel'   => 'info',
            'mensaje' => 'Registrar tus comidas diariamente mejora la precisión del seguimiento nutricional.',
        ];
    }

    if (empty($alertas)) {
        $alertas[] = [
            'tipo'    => 'general',
            'nivel'   => 'positivo',
            'mensaje' => '¡Sin alertas! Tu seguimiento nutricional del período se ve saludable.',
        ];
    }

    return $alertas;
}
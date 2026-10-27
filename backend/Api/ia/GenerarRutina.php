<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../ia/llm.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

// ── 1. Perfil del usuario ────────────────────────────────────
$stmt = $db->prepare('
    SELECT ch.*, u.nombre, u.peso_kg, u.altura_cm, u.objetivo,
           u.nivel_entrenamiento, u.equipo_disponible
    FROM cuestionario_habitos ch
    JOIN usuario u ON u.id_usuario = ch.id_usuario
    WHERE ch.id_usuario = ?
    ORDER BY ch.fecha_respuesta DESC
    LIMIT 1
');
$stmt->execute([$userId]);
$datos = $stmt->fetch();

if (!$datos) {
    http_response_code(422);
    echo json_encode(['error' => 'El usuario no ha completado el cuestionario.']);
    exit;
}

if (!llmPermitido((int) $userId, 'rutina')) {
    http_response_code(429);
    echo json_encode(['error' => 'Espera un minuto antes de generar otra rutina.']);
    exit;
}

// ── 2. Calcular días reales ──────────────────────────────────
$diasMap = ['nunca' => 2, '1_2' => 2, '3_4' => 3, '5_mas' => 5];
$diasSemana = $diasMap[$datos['frecuencia_semana'] ?? ''] ?? 3;

// PAR-Q+ con algún "Sí": rutina de baja intensidad hasta que lo vea un profesional
$parqConsultar = ($datos['parq_resultado'] ?? '') === 'consultar';
if ($parqConsultar) $diasSemana = min($diasSemana, 3);

// ── 3. Obtener ejercicios según equipo y nivel ───────────────
$equipo = strtolower($datos['equipo_disponible'] ?? 'ninguno');
$nivel  = $parqConsultar ? 'principiante' : ($datos['nivel_recomendado'] ?? 'principiante');

$nivelesMap = [
    'principiante' => ['principiante'],
    'intermedio'   => ['principiante', 'intermedio'],
    'avanzado'     => ['principiante', 'intermedio', 'avanzado'],
];
$nivelesPermitidos = $nivelesMap[$nivel] ?? ['principiante'];
$ph = implode(',', array_fill(0, count($nivelesPermitidos), '?'));

if (str_contains($equipo, 'gimnasio_completo')) {
    $equipoSQL    = '1=1';
    $equipoParams = [];
} else {
    $piezas = array_filter(array_map('trim', explode(',', $equipo)));
    $likes  = array_map(fn($e) => 'LOWER(equipo_necesario) LIKE ?', $piezas);
    $likes[] = "equipo_necesario = 'ninguno'";
    $likes[] = "equipo_necesario IS NULL";
    $equipoSQL    = '(' . implode(' OR ', $likes) . ')';
    $equipoParams = array_map(fn($e) => "%$e%", $piezas);
}

$stmt = $db->prepare("
    SELECT id_ejercicio, nombre, grupo_muscular, nivel_dificultad, equipo_necesario
    FROM ejercicio
    WHERE nivel_dificultad IN ($ph) AND $equipoSQL
    ORDER BY grupo_muscular, RAND()
");
$stmt->execute(array_merge($nivelesPermitidos, $equipoParams));
$ejerciciosDisponibles = $stmt->fetchAll();

if (empty($ejerciciosDisponibles)) {
    http_response_code(422);
    echo json_encode(['error' => 'No hay ejercicios disponibles para este perfil.']);
    exit;
}

// ── 4. Distribuir ejercicios por día en PHP ──────────────────
// Agrupar por grupo muscular
$porGrupo = [];
foreach ($ejerciciosDisponibles as $ej) {
    $porGrupo[$ej['grupo_muscular']][] = $ej;
}

// Shuffle dentro de cada grupo para variedad
foreach ($porGrupo as &$lista) shuffle($lista);
unset($lista);

$grupos      = array_keys($porGrupo);
$ejerciciosPorDia = ($nivel === 'principiante') ? 5 : ($nivel === 'intermedio' ? 6 : 8);

// Configuración según objetivo
$objetivoConfig = [
    'fuerza'       => ['series' => 4, 'reps' => 8,  'grupos_prioritarios' => ['pecho','espalda','piernas','hombros','brazos']],
    'resistencia'  => ['series' => 3, 'reps' => 15, 'grupos_prioritarios' => ['cardio','piernas','core','pecho','espalda']],
    'perdida_peso' => ['series' => 3, 'reps' => 15, 'grupos_prioritarios' => ['cardio','piernas','core','pecho','espalda']],
    'volumen'      => ['series' => 4, 'reps' => 10, 'grupos_prioritarios' => ['pecho','espalda','piernas','brazos','hombros']],
    'flexibilidad' => ['series' => 3, 'reps' => 12, 'grupos_prioritarios' => ['core','piernas','espalda','hombros','pecho']],
];

// usuario.objetivo usa los nombres del cuestionario; rutina.objetivo usa otros
$objetivoUsuario = $datos['objetivo_ejercicio'] ?? $datos['objetivo'] ?? 'fuerza';
$objetivo     = [
    'fuerza'        => 'fuerza',
    'masa_muscular' => 'volumen',
    'perdida_grasa' => 'perdida_peso',
    'resistencia'   => 'resistencia',
    'volumen'       => 'volumen',
    'flexibilidad'  => 'flexibilidad',
][$objetivoUsuario] ?? 'fuerza';
$config       = $objetivoConfig[$objetivo] ?? $objetivoConfig['fuerza'];
if ($parqConsultar) {   // menos series y más repeticiones con poco peso
    $config['series'] = min($config['series'], 2);
    $config['reps']   = max($config['reps'], 12);
}
$seriesBase   = $config['series'];
$repsBase     = $config['reps'];
$gruposPrio   = $config['grupos_prioritarios'];

// Ordenar grupos por prioridad
usort($grupos, function($a, $b) use ($gruposPrio) {
    $posA = array_search($a, $gruposPrio);
    $posB = array_search($b, $gruposPrio);
    $posA = ($posA === false) ? 99 : $posA;
    $posB = ($posB === false) ? 99 : $posB;
    return $posA - $posB;
});

// Distribuir grupos entre días (rotación)
$gruposPorDia = array_fill(1, $diasSemana, []);
$gi = 0;
foreach ($grupos as $grupo) {
    $dia = ($gi % $diasSemana) + 1;
    $gruposPorDia[$dia][] = $grupo;
    $gi++;
}

// Asignar ejercicios a cada día
$ejerciciosFinales = [];
$usadosPorDia = array_fill(1, $diasSemana, []);

for ($dia = 1; $dia <= $diasSemana; $dia++) {
    $orden = 1;
    $ejEnDia = 0;
    $gruposDelDia = $gruposPorDia[$dia];

    // Primera pasada: grupos asignados a este día
    foreach ($gruposDelDia as $grupo) {
        if (!isset($porGrupo[$grupo])) continue;
        foreach ($porGrupo[$grupo] as $ej) {
            if ($ejEnDia >= $ejerciciosPorDia) break;
            if (in_array($ej['id_ejercicio'], $usadosPorDia[$dia])) continue;
            $ejerciciosFinales[] = [
                'id_ejercicio'    => $ej['id_ejercicio'],
                'dia_semana'      => $dia,
                'orden'           => $orden++,
                'series_objetivo' => $seriesBase,
                'reps_objetivo'   => $repsBase,
                'peso_sugerido'   => null,
            ];
            $usadosPorDia[$dia][] = $ej['id_ejercicio'];
            $ejEnDia++;
        }
    }

    // Segunda pasada: rellenar con ejercicios de otros grupos si faltan
    if ($ejEnDia < $ejerciciosPorDia) {
        foreach ($ejerciciosDisponibles as $ej) {
            if ($ejEnDia >= $ejerciciosPorDia) break;
            if (in_array($ej['id_ejercicio'], $usadosPorDia[$dia])) continue;
            $ejerciciosFinales[] = [
                'id_ejercicio'    => $ej['id_ejercicio'],
                'dia_semana'      => $dia,
                'orden'           => $orden++,
                'series_objetivo' => $seriesBase,
                'reps_objetivo'   => $repsBase,
                'peso_sugerido'   => null,
            ];
            $usadosPorDia[$dia][] = $ej['id_ejercicio'];
            $ejEnDia++;
        }
    }
}

// ── 5. Usar la IA (LM Studio) SOLO para nombre y descripción ─
$nombrerutina  = 'rutina Personalizada';
$descripcion   = 'rutina generada según tu perfil y objetivos.';

$promptSimple = "Eres un entrenador personal. El usuario tiene nivel {$nivel}, objetivo {$objetivo}, entrena {$diasSemana} días/semana. Da un nombre creativo en español (máx 60 caracteres) y una descripción motivadora de 1 oración (máx 120 caracteres).";

$r = llmChat([['role' => 'user', 'content' => $promptSimple]], [
    'temperature'   => 0.5,
    'max_tokens'    => 200,
    'timeout'       => 120,
    'nombre_schema' => 'rutina',
    'json_schema'   => [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['nombre', 'descripcion'],
        'properties' => ['nombre' => ['type' => 'string'], 'descripcion' => ['type' => 'string']],
    ],
]);

if ($r['ok']) {
    $iaData = $r['json'] ?? llmExtraerJSON($r['content']);
    if (is_string($iaData['nombre'] ?? null) && trim($iaData['nombre']) !== '') {
        $nombrerutina = mb_substr(trim(strip_tags($iaData['nombre'])), 0, 80);
    }
    if (is_string($iaData['descripcion'] ?? null) && trim($iaData['descripcion']) !== '') {
        $descripcion = mb_substr(trim(strip_tags($iaData['descripcion'])), 0, 500);
    }
} else {
    error_log('[IA rutina] LM Studio: ' . $r['error']);   // la rutina se crea igual con nombre por defecto
}

if ($parqConsultar) {
    $descripcion = 'Rutina de baja intensidad: consulta a un profesional de la salud antes de aumentarla (PAR-Q+).';
}

// Mapear objetivo
$objetivosValidos = ['fuerza', 'resistencia', 'perdida_peso', 'volumen', 'flexibilidad'];
$objetivoFinal = in_array($objetivo, $objetivosValidos) ? $objetivo : 'fuerza';

// ── 6. Guardar en BD ─────────────────────────────────────────
try {
    $db->beginTransaction();

    $db->prepare('
        INSERT INTO rutina (id_usuario, nombre, descripcion, objetivo, nivel, dias_semana, activa, generada_ia)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ')->execute([$userId, $nombrerutina, $descripcion, $objetivoFinal, $nivel, $diasSemana]);

    $idrutina = (int) $db->lastInsertId();

    $stmtEj = $db->prepare('
        INSERT IGNORE INTO rutina_ejercicio
            (id_rutina, id_ejercicio, dia_semana, orden, series_objetivo, reps_objetivo, peso_sugerido)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($ejerciciosFinales as $ej) {
        $stmtEj->execute([
            $idrutina,
            (int)   $ej['id_ejercicio'],
            (int)   $ej['dia_semana'],
            (int)   $ej['orden'],
            (int)   $ej['series_objetivo'],
            (int)   $ej['reps_objetivo'],
            $ej['peso_sugerido'],
        ]);
    }

    $db->commit();

    // Devolver rutina completa
    $stmt = $db->prepare('
        SELECT re.id, re.id_ejercicio, re.dia_semana, re.orden,
               re.series_objetivo, re.reps_objetivo, re.peso_sugerido,
               e.nombre, e.grupo_muscular, e.nivel_dificultad, e.equipo_necesario
        FROM rutina_ejercicio re
        JOIN ejercicio e ON e.id_ejercicio = re.id_ejercicio
        WHERE re.id_rutina = ?
        ORDER BY re.dia_semana, re.orden
    ');
    $stmt->execute([$idrutina]);
    $ejerciciosGuardados = $stmt->fetchAll();

    echo json_encode([
        'ok'        => true,
        'id_rutina' => $idrutina,
        'rutina'    => [
            'id_rutina'   => $idrutina,
            'nombre'      => $nombrerutina,
            'descripcion' => $descripcion,
            'objetivo'    => $objetivoFinal,
            'nivel'       => $nivel,
            'dias_semana' => $diasSemana,
            'activa'      => true,
            'ejercicios'  => $ejerciciosGuardados,
        ],
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    error_log('[IA rutina] ' . $e->getMessage());
    echo json_encode(['error' => 'Error al guardar la rutina.']);
}
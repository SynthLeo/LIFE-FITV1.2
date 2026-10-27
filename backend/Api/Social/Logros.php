<?php
// ============================================================
//  GET Api/Social/Logros.php — logros y misiones reales (M5)
//  Usa las tablas existentes logro, mision, usuario_logro,
//  usuario_mision y salud_notificacion.
//  Cada llamada recalcula el progreso con los entrenamientos
//  reales, otorga los logros nuevos y avisa con una notificación.
//  Solo usa datos de ejercicio: nada de PHQ, GAD ni AUDIT.
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';

$uid = (int) $GLOBALS['userId'];

// Qué métrica mide cada logro / misión (por id, según los datos que ya trae la tabla).
// Si agregan logros o misiones nuevos, regístrenlos aquí.
const LOGRO_METRICA = [
    1 => ['sesiones', 1],
    2 => ['racha', 7],
    3 => ['racha', 30],
    4 => ['volumen', 1000],
    5 => ['volumen', 10000],
    6 => ['ejercicios_unicos', 10],
    7 => ['minutos', 1000],
    8 => ['avanzado', 1],
];
const MISION_METRICA = [
    1 => 'sesiones_hoy',
    2 => 'series_hoy',
    3 => 'sesiones_semana',
    4 => 'volumen_semana',
    5 => 'grupos_semana',
];

function uno(PDO $db, string $sql, array $p): float {
    $st = $db->prepare($sql);
    $st->execute($p);
    return (float) ($st->fetchColumn() ?: 0);
}

try {
    $db = getDB();
    $hoy    = date('Y-m-d');
    $lunes  = date('Y-m-d', strtotime('monday this week'));
    $domingo = date('Y-m-d', strtotime('sunday this week'));

    $st = $db->prepare('SELECT racha_actual, nivel_entrenamiento FROM usuario WHERE id_usuario = ?');
    $st->execute([$uid]);
    $u = $st->fetch() ?: [];

    $fin = "id_usuario = ? AND estado = 'finalizada'";
    $m = [
        'sesiones'          => uno($db, "SELECT COUNT(*) FROM sesion WHERE $fin", [$uid]),
        'racha'             => (float) ($u['racha_actual'] ?? 0),
        'volumen'           => uno($db, "SELECT SUM(volumen_total_kg) FROM sesion WHERE $fin", [$uid]),
        'minutos'           => uno($db, "SELECT SUM(duracion_minutos) FROM sesion WHERE $fin", [$uid]),
        'ejercicios_unicos' => uno($db, 'SELECT COUNT(DISTINCT id_ejercicio) FROM registro_entrenamiento WHERE id_usuario = ?', [$uid]),
        'avanzado'          => ($u['nivel_entrenamiento'] ?? '') === 'avanzado' ? 1 : 0,
        'sesiones_hoy'      => uno($db, "SELECT COUNT(*) FROM sesion WHERE $fin AND fecha = ?", [$uid, $hoy]),
        'series_hoy'        => uno($db, 'SELECT COUNT(*) FROM registro_entrenamiento WHERE id_usuario = ? AND DATE(created_at) = ?', [$uid, $hoy]),
        'sesiones_semana'   => uno($db, "SELECT COUNT(*) FROM sesion WHERE $fin AND fecha BETWEEN ? AND ?", [$uid, $lunes, $domingo]),
        'volumen_semana'    => uno($db, "SELECT SUM(volumen_total_kg) FROM sesion WHERE $fin AND fecha BETWEEN ? AND ?", [$uid, $lunes, $domingo]),
        'grupos_semana'     => uno($db, 'SELECT COUNT(DISTINCT e.grupo_muscular) FROM registro_entrenamiento r JOIN ejercicio e ON e.id_ejercicio = r.id_ejercicio
                                         WHERE r.id_usuario = ? AND DATE(r.created_at) BETWEEN ? AND ?', [$uid, $lunes, $domingo]),
    ];

    // ── Logros ──
    $obtenidos = $db->prepare('SELECT id_logro, fecha_obtenido FROM usuario_logro WHERE id_usuario = ?');
    $obtenidos->execute([$uid]);
    $obtenidos = $obtenidos->fetchAll(PDO::FETCH_KEY_PAIR);

    $logros = [];
    $nuevos = [];
    foreach ($db->query('SELECT id_logro, nombre, descripcion, icono, xp_recompensa FROM logro ORDER BY id_logro')->fetchAll() as $l) {
        $id = (int) $l['id_logro'];
        [$clave, $meta] = LOGRO_METRICA[$id] ?? [null, null];
        $valor = $clave ? $m[$clave] : null;
        if ($clave && !isset($obtenidos[$id]) && $valor >= $meta) {
            $ins = $db->prepare('INSERT IGNORE INTO usuario_logro (id_usuario, id_logro) VALUES (?, ?)');
            $ins->execute([$uid, $id]);
            if ($ins->rowCount()) {
                $obtenidos[$id] = date('Y-m-d H:i:s');
                $nuevos[] = $l['nombre'];
                $db->prepare("INSERT INTO salud_notificacion (id_usuario, modulo, tipo, nivel, titulo, mensaje) VALUES (?, 'general', 'logro', 'info', ?, ?)")
                   ->execute([$uid, mb_substr('🏆 Logro: ' . $l['nombre'], 0, 100), $l['descripcion'] ?? '']);
            }
        }
        $logros[] = [
            'id'          => $id,
            'nombre'      => $l['nombre'],
            'descripcion' => $l['descripcion'],
            'icono'       => $l['icono'],
            'xp'          => (int) $l['xp_recompensa'],
            'obtenido'    => isset($obtenidos[$id]),
            'fecha'       => $obtenidos[$id] ?? null,
            'progreso'    => $clave ? min(1, $valor / $meta) : null,
        ];
    }

    // ── Misiones del periodo actual ──
    $misiones = [];
    $upsert = $db->prepare('INSERT INTO usuario_mision (id_usuario, id_mision, progreso_actual, completada, fecha_inicio, fecha_fin)
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE progreso_actual = VALUES(progreso_actual), completada = GREATEST(completada, VALUES(completada))');
    foreach ($db->query('SELECT id_mision, nombre, descripcion, tipo, meta_cantidad, xp_recompensa FROM mision ORDER BY tipo, id_mision')->fetchAll() as $mi) {
        $id    = (int) $mi['id_mision'];
        $clave = MISION_METRICA[$id] ?? null;
        $meta  = max(1, (int) $mi['meta_cantidad']);
        $prog  = $clave ? (int) round($m[$clave]) : 0;
        [$ini, $finP] = $mi['tipo'] === 'semanal' ? [$lunes, $domingo] : [$hoy, $hoy];
        if ($clave) $upsert->execute([$uid, $id, min($prog, 65535), (int) ($prog >= $meta), $ini, $finP]);
        $misiones[] = [
            'id'          => $id,
            'nombre'      => $mi['nombre'],
            'descripcion' => $mi['descripcion'],
            'tipo'        => $mi['tipo'],
            'meta'        => $meta,
            'progreso'    => $clave ? $prog : null,
            'completada'  => $clave && $prog >= $meta,
            'xp'          => (int) $mi['xp_recompensa'],
        ];
    }

    $xp = uno($db, 'SELECT COALESCE(SUM(l.xp_recompensa), 0) FROM usuario_logro ul JOIN logro l ON l.id_logro = ul.id_logro WHERE ul.id_usuario = ?', [$uid])
        + uno($db, 'SELECT COALESCE(SUM(m.xp_recompensa), 0) FROM usuario_mision um JOIN mision m ON m.id_mision = um.id_mision WHERE um.id_usuario = ? AND um.completada = 1', [$uid]);
} catch (Throwable $e) {
    errorInterno('Social/Logros', $e);
}

salir(200, ['ok' => true, 'xp' => (int) $xp, 'logros' => $logros, 'misiones' => $misiones, 'nuevos' => $nuevos]);

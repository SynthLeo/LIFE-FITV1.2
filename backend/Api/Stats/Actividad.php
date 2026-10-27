<?php
// ============================================================
//  GET Api/Stats/Actividad.php — datos reales para Estadísticas
//  semanas:  últimas 8 semanas → { semana, sesiones, volumen }
//  heatmap:  últimos 84 días   → { fecha, activo, nivel 0-3 }
// ============================================================

require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';
require_once __DIR__ . '/../../utils/api.php';

$uid = (int) $GLOBALS['userId'];

try {
    $st = getDB()->prepare("SELECT fecha, COUNT(*) AS n, COALESCE(SUM(volumen_total_kg), 0) AS vol
                            FROM sesion
                            WHERE id_usuario = ? AND estado = 'finalizada' AND fecha >= CURDATE() - INTERVAL 83 DAY
                            GROUP BY fecha");
    $st->execute([$uid]);
    $porDia = [];
    foreach ($st->fetchAll() as $f) $porDia[$f['fecha']] = ['n' => (int) $f['n'], 'vol' => (float) $f['vol']];
} catch (Throwable $e) {
    errorInterno('Stats/Actividad', $e);
}

$hoy = new DateTimeImmutable('today');

$heatmap = [];
for ($i = 83; $i >= 0; $i--) {
    $f = $hoy->modify("-$i day")->format('Y-m-d');
    $n = $porDia[$f]['n'] ?? 0;
    $heatmap[] = ['fecha' => $f, 'activo' => $n > 0, 'nivel' => min($n, 3)];
}

$semanas = [];
for ($s = 7; $s >= 0; $s--) {
    $ini = $hoy->modify('monday this week')->modify("-$s week");
    $ses = 0; $vol = 0.0;
    for ($d = 0; $d < 7; $d++) {
        $f = $ini->modify("+$d day")->format('Y-m-d');
        $ses += $porDia[$f]['n'] ?? 0;
        $vol += $porDia[$f]['vol'] ?? 0;
    }
    $semanas[] = ['semana' => $ini->format('d/m'), 'sesiones' => $ses, 'volumen' => (int) round($vol)];
}

salir(200, ['ok' => true, 'semanas' => $semanas, 'heatmap' => $heatmap]);

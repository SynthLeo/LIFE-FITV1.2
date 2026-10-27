<?php
require_once __DIR__ . '/../../Seguridad/Cors.php';
require_once __DIR__ . '/../../Seguridad/Autenticacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$userId = $GLOBALS['userId'];
$db     = getDB();

$idejercicio  = (int) ($_GET['id']        ?? 0);
$busqueda     = trim($_GET['q']           ?? '');
$grupo        = trim($_GET['grupo']       ?? '');
$nivel        = trim($_GET['nivel']       ?? '');
$equipoFiltro = trim($_GET['equipo']      ?? '');
$soloFavs     = !empty($_GET['favoritos']);
$page         = max(1, (int)($_GET['page']  ?? 0));
$limit        = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$offset       = $page > 0 ? ($page - 1) * $limit : 0;

// Perfil del usuario
$stmt = $db->prepare('SELECT nivel_entrenamiento, equipo_disponible FROM usuario WHERE id_usuario = ?');
$stmt->execute([$userId]);
$perfil = $stmt->fetch();
$nivelusuario  = $perfil['nivel_entrenamiento'] ?? 'principiante';
$equipousuario = strtolower($perfil['equipo_disponible'] ?? '');

$nivelesPermitidos = ['principiante'];
if ($nivelusuario === 'intermedio') $nivelesPermitidos[] = 'intermedio';
if ($nivelusuario === 'avanzado')   { $nivelesPermitidos[] = 'intermedio'; $nivelesPermitidos[] = 'avanzado'; }

try {
    // Modo detalle
    if ($idejercicio > 0) {
        $ph = implode(',', array_fill(0, count($nivelesPermitidos), '?'));
        $stmt = $db->prepare("
            SELECT e.id_ejercicio, e.nombre, e.descripcion, e.grupo_muscular,
                   e.nivel_dificultad, e.equipo_necesario, e.imagen_url,
                   e.musculo_antagonista,
                   IF(f.id_favorito IS NOT NULL, 1, 0) AS es_favorito
            FROM ejercicio e
            LEFT JOIN favorito f ON f.id_ejercicio = e.id_ejercicio AND f.id_usuario = ?
            WHERE e.id_ejercicio = ? AND e.nivel_dificultad IN ($ph)
        ");
        $stmt->execute(array_merge([$userId, $idejercicio], $nivelesPermitidos));
        $ejercicio = $stmt->fetch();
        if (!$ejercicio) { http_response_code(404); echo json_encode(['error' => 'ejercicio no encontrado.']); exit; }
        $ejercicio['compatible'] = calcCompatibilidad($ejercicio['equipo_necesario'], $equipousuario);
        $ejercicio['es_favorito'] = (bool) $ejercicio['es_favorito'];
        echo json_encode(['ok' => true, 'ejercicio' => $ejercicio]);
        exit;
    }

    // Modo lista
    $ph     = implode(',', array_fill(0, count($nivelesPermitidos), '?'));
    $where  = ["e.nivel_dificultad IN ($ph)"];
    $params = $nivelesPermitidos;

    if ($busqueda !== '')  { $where[] = 'e.nombre LIKE ?'; $params[] = "%$busqueda%"; }
    if ($grupo !== '')     { $where[] = 'e.grupo_muscular = ?'; $params[] = $grupo; }
    if ($nivel !== '' && in_array($nivel, $nivelesPermitidos, true)) { $where[] = 'e.nivel_dificultad = ?'; $params[] = $nivel; }
    if ($equipoFiltro !== '') { $where[] = 'e.equipo_necesario LIKE ?'; $params[] = "%$equipoFiltro%"; }
    if ($soloFavs) $where[] = 'f.id_favorito IS NOT NULL';

    $whereSQL = implode(' AND ', $where);
    $joinFav  = "LEFT JOIN favorito f ON f.id_ejercicio = e.id_ejercicio AND f.id_usuario = $userId";

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM ejercicio e $joinFav WHERE $whereSQL");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $limitSQL = $page > 0 ? "LIMIT $limit OFFSET $offset" : '';
    $stmt = $db->prepare("
        SELECT e.id_ejercicio, e.nombre, e.descripcion, e.grupo_muscular,
               e.nivel_dificultad, e.equipo_necesario, e.imagen_url,
               e.musculo_antagonista,
               IF(f.id_favorito IS NOT NULL, 1, 0) AS es_favorito
        FROM ejercicio e $joinFav
        WHERE $whereSQL
        ORDER BY e.grupo_muscular, e.nombre $limitSQL
    ");
    $stmt->execute($params);
    $ejercicios = $stmt->fetchAll();

    foreach ($ejercicios as &$e) {
        $e['compatible']  = calcCompatibilidad($e['equipo_necesario'], $equipousuario);
        $e['es_favorito'] = (bool) $e['es_favorito'];
    }
    unset($e);

    $grupos  = $db->query('SELECT DISTINCT grupo_muscular FROM ejercicio ORDER BY grupo_muscular')->fetchAll(PDO::FETCH_COLUMN);
    $equipos = array_values(array_filter($db->query('SELECT DISTINCT equipo_necesario FROM ejercicio WHERE equipo_necesario IS NOT NULL ORDER BY equipo_necesario')->fetchAll(PDO::FETCH_COLUMN)));

    echo json_encode([
        'ok'          => true,
        'ejercicios'  => $ejercicios,
        'grupos'      => $grupos,
        'equipos'     => $equipos,
        'nivel_usuario' => $nivelusuario,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'pages'       => $page > 0 ? (int) ceil($total / $limit) : 1,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}

function calcCompatibilidad(?string $eq, string $usr): bool {
    if (!$eq || strtolower(trim($eq)) === 'ninguno') return true;
    if (str_contains($usr, 'gimnasio_completo')) return true;
    foreach (array_map('trim', explode(',', strtolower($eq))) as $e) {
        if ($e !== '' && str_contains($usr, $e)) return true;
    }
    return false;
}

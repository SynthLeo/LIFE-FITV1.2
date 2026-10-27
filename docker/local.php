<?php
// Configuración para Docker: toma los valores de las variables de entorno
// de docker-compose.yml. Se monta como backend/Configuracion/local.php.
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'JWT_SECRET', 'LLM_URL', 'LLM_MODEL'] as $k) {
    $v = getenv($k);
    if ($v !== false && $v !== '') define($k, $v);
}

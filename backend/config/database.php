<?php
// ============================================================
//  config/database.php
//  Antes tenía su propia copia de la conexión. Ahora toda la
//  configuración vive en Configuracion/Conexion.php para que
//  BD, JWT e IA no se desincronicen.
// ============================================================

require_once __DIR__ . '/../Configuracion/Conexion.php';

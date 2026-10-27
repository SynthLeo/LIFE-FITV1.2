# LIFE FIT — Backend PHP

PHP 8 + MariaDB (base `fitness`) + LM Studio para la IA.
Instalación completa y lista de cambios: ver `LEEME-PRIMERO.md` en el zip de entrega.

## Estructura
```
Configuracion/Conexion.php   ← ÚNICA configuración: BD, JWT, IA (LM Studio), CORS
Configuracion/local.php      ← (opcional, no se sube a git) tus propios define(...)
Configuracion/instrumentos.json ← opciones válidas de los cuestionarios (generado desde el frontend)
Seguridad/Sesion.php         ← quién hace la petición: JWT con firma verificada
Api/                         ← endpoints que usa el frontend
  Auth/                      ← Login, Registro, CompletarOnboarding
  Cuestionario/              ← Guardar*/ObtenerPerfil* (validan y recalculan puntajes)
  IA/                        ← GenerarRutina, GenerarDieta, Diagnostico
  ia/, modules/              ← puentes hacia ia/ y modules/
ia/                          ← chat, prompts, detección de crisis, cliente de LM Studio
modules/                     ← clínico, psicología, alimentación, perfil
utils/                       ← instrumentos.php (calificación), dieta.php, api.php, helpers.php
migraciones/                 ← SQL a ejecutar en orden
```

## Reglas
- El usuario sale SOLO de `usuarioAutenticadoId()` (Seguridad/Sesion.php). Nunca de la URL o el body.
- PHQ-8, GAD-7 y AUDIT-C son datos sensibles: nunca en ranking, feed, social ni exportaciones.
- Si cambias preguntas en el frontend (`src/modulos/cuestionarios/instrumentos.js`), regenera el JSON:
  `node scripts/exportar-instrumentos.mjs ../backend/Configuracion/instrumentos.json`

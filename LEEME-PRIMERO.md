# LIFE FIT: versión final

Trae todo lo de `TAREAS-LIFEFIT.md`.

| Archivo o carpeta | Qué es |
| --- | --- |
| `iniciar.bat` / `iniciar.sh` | **Levanta la app de un solo comando** |
| `frontend/` | La app React |
| `backend/` | El backend PHP corregido |
| `docker-compose.yml` + `docker/` | Opción con Docker |
| `cambios-backend.patch` | Todas las diferencias contra tu backend original |

---

## Instalación (solo la primera vez)

1. **Respaldo de la base:**
   ```bash
   mysqldump -u LEO -p -P 3307 fitness > respaldo_antes_final.sql
   ```
2. **Migraciones:** en phpMyAdmin (base `fitness`), pestaña SQL, ejecuta **en orden** las que no hayas corrido:
   - `backend/migraciones/001_cuestionarios_validados.sql`
   - `backend/migraciones/002_seguimiento_animo.sql`
   - `backend/migraciones/003_login_intentos.sql`
   - `backend/migraciones/004_privacidad_ranking.sql`

   Se pueden correr dos veces sin romper nada y ninguna borra registros.
3. **Backend:** copia `backend/` a `C:\xampp\htdocs\backend` y revisa `backend/Configuracion/Conexion.php`:
   - `JWT_SECRET`: **cámbialo** por una cadena larga y aleatoria.
   - `LLM_MODEL`: el identificador que muestra LM Studio.
4. **LM Studio:**
   - Usa **Qwen3 4B Instruct 2507** (`qwen/qwen3-4b-2507`) con Context Length = 8192.
   - En la pestaña Developer, dale Start Server.

## Para levantarla (todos los días)

**Windows:** doble clic en **`iniciar.bat`**. Mac o Linux: `./iniciar.sh`.

El script hace esto:
1. Revisa que respondan el backend de XAMPP y LM Studio; si algo falta, te dice qué prender.
2. Instala las dependencias (solo la primera vez).
3. Abre el navegador en http://localhost:5173.

Requisito: Node.js 20 o más nuevo.

**Con Docker (opcional):**
1. Pon tu respaldo en `docker/db/00_fitness.sql` y corre `docker compose up --build`. Levanta la base, el backend y el frontend, y aplica las migraciones solas.
2. ⚠️ El `docker-compose.yml` no se pudo probar aquí. El `iniciar.sh` sí se probó.

---

## Qué trae

### Seguridad
- Cerramos 3 agujeros:
  - Los módulos se podían leer sin login.
  - Se aceptaban tokens falsos.
  - El modelo de IA no tenía filtros.
- **La sesión va en una cookie httpOnly:**
  - El JavaScript ya no puede leer el token, así que un XSS no lo roba.
  - `SameSite=Lax` evita peticiones con tu sesión desde otros sitios.
  - Ya no se guarda el token en `localStorage`.
  - Endpoint nuevo: `Auth/Logout.php`.
- **Límites:**
  - Login: 5 fallos por correo o 20 por IP en 15 minutos.
  - Registro: 5 cuentas por IP por hora.
  - IA: 6 peticiones por minuto.
- **Consentimiento informado obligatorio:** el backend responde 403 sin él.
- **Datos sensibles:**
  - PHQ-8, GAD-7 y AUDIT-C nunca salen en el ranking, el feed, Social ni los PDF.
  - El chat ya no ejecuta HTML.

### IA (LM Studio)
- Todo pasa por el backend.
- Las crisis se detectan antes del modelo y responden con la Línea de la Vida (800 911 2000).
- Los prompts tienen reglas de seguridad: nada de dosis, de dietas de menos de 1,400 kcal ni de diagnósticos.
- **El chat escribe en vivo (*streaming*):** la respuesta aparece mientras el modelo la genera y los bloques `<think>` se filtran.
- La dieta y la rutina se validan; si la IA falla, hay un respaldo por reglas.

### Cuestionarios
- Tienen 61 preguntas validadas: PAR-Q+, IPAQ, STEPS, AUDIT-C, FINDRISC, PHQ-8, GAD-7 y CDMX.
- Se puntúan en el servidor.
- **Un solo componente para los 4** (`componentes/cuestionario/Cuestionario.jsx`):
  - Usa radios reales, así que se pueden contestar solo con el teclado (flechas, espacio y Enter).
  - Funciona con tema claro y oscuro.
  - Se quitaron los `@import` de Google Fonts.
- **Edad:**
  - Fecha de nacimiento en el onboarding.
  - Menores de 18: aviso para el tutor, IMC por percentiles (CDC) y sin dietas con déficit calórico.
- **Onboarding:**
  - Se puede pausar y retomar, y muestra el tiempo restante.
  - **Clínico, nutrición y psicología se pueden dejar para después**; ejercicio es obligatorio.
- **Seguimiento cada 2 semanas:** gráfica privada del PHQ-8 y GAD-7. Si empeora, sugiere cita.

### Lo demás
- **Descargas:**
  - Dieta en PDF y CSV.
  - Rutina en PDF.
  - En Mi salud, **"Descargar mi plan"**: rutina y dieta en un solo PDF.
- **Social con datos reales:**
  - Ranking y feed reales.
  - Pestaña nueva de **Logros y misiones**: se calculan con tus entrenamientos y dan XP y notificaciones. Usa las tablas `logro`, `mision`, `usuario_logro`, `usuario_mision` y `salud_notificacion`.
  - **Opción para ocultarte del ranking.**
- **Estadísticas:** gráficas reales.
- **Código:**
  - Un solo cliente HTTP.
  - Las páginas pesadas cargan solo al abrirlas (`React.lazy`).
  - Contorno visible al navegar con teclado.
- **Pruebas:** `npm test` corre **34 pruebas** de los puntajes: bordes del PHQ-8, cortes de AUDIT-C, IPAQ, PAR-Q+, alimentación, IMC y preguntas condicionales.

---

## Lo único que no se hizo
- **Registro de errores con Sentry:** necesita crear una cuenta. Por ahora los errores del backend van al log de PHP (`error_log`).
- **Las metas del M7** (lista de compras): no estaban en las entregas.

## Cómo lo probamos
- PHP 8.3 y MariaDB con su respaldo, más un LM Studio simulado (con *streaming*).
- Los endpoints, con curl.
- La app, en un navegador real:
  - Login con cookie.
  - Chat en vivo.
  - Los 4 cuestionarios contestados solo con teclado.
  - Logros y privacidad del ranking.
  - PDF.
- `npm test`, `npm run build` y `iniciar.sh` desde una copia limpia.

No se probó con el modelo real (aquí no hay GPU) ni con Docker. Cuando conecten LM Studio, corran las 5 pruebas de aceptación de `TAREAS-LIFEFIT.md`.

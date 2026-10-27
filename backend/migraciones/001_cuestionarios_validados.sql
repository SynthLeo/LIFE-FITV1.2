-- ════════════════════════════════════════════════════════════════════
-- LIFE FIT — Migración 001: cuestionarios validados + IA
-- Base de datos: fitness  (MariaDB 10.3+; probado en 10.11 y pensado para 13.0)
--
-- ANTES DE CORRER:
--   mysqldump -u LEO -p -P 3307 fitness > respaldo_antes_001.sql
--
-- Se puede correr más de una vez sin romper nada (IF NOT EXISTS).
-- No borra columnas ni datos: las columnas viejas se quedan, solo dejan de ser obligatorias.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── 1. EJERCICIO: cuestionario_habitos ───────────────────────────────
-- Columnas viejas que el cuestionario nuevo ya no pregunta: ahora pueden quedar vacías.
ALTER TABLE cuestionario_habitos
  MODIFY minutos_semana    VARCHAR(20) NULL COMMENT 'calculado del IPAQ: menos_75 | 75_150 | 150_300 | mas_300',
  MODIFY frecuencia_semana VARCHAR(10) NULL COMMENT 'calculado del IPAQ: nunca | 1_2 | 3_4 | 5_mas',
  MODIFY tipo_actividad    VARCHAR(20) NULL,
  MODIFY lugar_ejercicio   VARCHAR(20) NULL COMMENT 'casa | gimnasio | exterior | ninguno',
  MODIFY motivacion        VARCHAR(20) NULL COMMENT 'salud | estetica | rendimiento | medico',
  MODIFY barrera           VARCHAR(20) NULL,
  MODIFY condicion_fisica  VARCHAR(15) NULL COMMENT 'calculado del IPAQ',
  MODIFY horas_sedentario  VARCHAR(10) NULL COMMENT 'calculado del IPAQ',
  MODIFY condicion_salud   VARCHAR(15) NULL COMMENT 'calculado del PAR-Q+',
  MODIFY nutricion         VARCHAR(25) NULL,
  MODIFY calentamiento     VARCHAR(15) NULL,
  MODIFY entorno_social    VARCHAR(15) NULL;

ALTER TABLE cuestionario_habitos
  ADD COLUMN IF NOT EXISTS parq_corazon       VARCHAR(2)  NULL COMMENT 'PAR-Q+ 1: no | si',
  ADD COLUMN IF NOT EXISTS parq_dolor_pecho   VARCHAR(2)  NULL COMMENT 'PAR-Q+ 2',
  ADD COLUMN IF NOT EXISTS parq_mareo         VARCHAR(2)  NULL COMMENT 'PAR-Q+ 3',
  ADD COLUMN IF NOT EXISTS parq_cronica       VARCHAR(2)  NULL COMMENT 'PAR-Q+ 4',
  ADD COLUMN IF NOT EXISTS parq_medicamento   VARCHAR(2)  NULL COMMENT 'PAR-Q+ 5',
  ADD COLUMN IF NOT EXISTS parq_huesos        VARCHAR(2)  NULL COMMENT 'PAR-Q+ 6',
  ADD COLUMN IF NOT EXISTS parq_supervision   VARCHAR(2)  NULL COMMENT 'PAR-Q+ 7',
  ADD COLUMN IF NOT EXISTS parq_resultado     VARCHAR(10) NULL COMMENT 'apto | consultar',
  ADD COLUMN IF NOT EXISTS ipaq_vig_dias      VARCHAR(10) NULL COMMENT '0 | 1_2 | 3_4 | 5_6 | 7',
  ADD COLUMN IF NOT EXISTS ipaq_vig_min       VARCHAR(10) NULL COMMENT '10_19 | 20_29 | 30_59 | 60_89 | 90_mas',
  ADD COLUMN IF NOT EXISTS ipaq_mod_dias      VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS ipaq_mod_min       VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS ipaq_cam_dias      VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS ipaq_cam_min       VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS ipaq_sentado       VARCHAR(10) NULL COMMENT 'menos_4 | 4_6 | 7_9 | 10_mas',
  ADD COLUMN IF NOT EXISTS ipaq_met_min       INT         NULL COMMENT 'MET-min/semana (protocolo IPAQ 2005)',
  ADD COLUMN IF NOT EXISTS ipaq_categoria     VARCHAR(10) NULL COMMENT 'bajo | moderado | alto',
  ADD COLUMN IF NOT EXISTS equipo             VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS objetivo_ejercicio VARCHAR(30) NULL;

-- ── 2. CLÍNICO: cuestionarios_clinicos ───────────────────────────────
ALTER TABLE cuestionarios_clinicos
  ADD COLUMN IF NOT EXISTS srh_salud            VARCHAR(12) NULL COMMENT 'Salud autopercibida OMS: muy_buena…muy_mala',
  ADD COLUMN IF NOT EXISTS tabaco_actual        VARCHAR(12) NULL COMMENT 'STEPS: no | no_diario | diario',
  ADD COLUMN IF NOT EXISTS vapeo_actual         VARCHAR(12) NULL COMMENT 'no | ocasional | diario',
  ADD COLUMN IF NOT EXISTS audit_frecuencia     VARCHAR(15) NULL COMMENT 'AUDIT-C 1',
  ADD COLUMN IF NOT EXISTS audit_cantidad       VARCHAR(10) NULL COMMENT 'AUDIT-C 2',
  ADD COLUMN IF NOT EXISTS audit_atracon        VARCHAR(15) NULL COMMENT 'AUDIT-C 3',
  ADD COLUMN IF NOT EXISTS audit_c_total        TINYINT UNSIGNED NULL COMMENT '0-12 (dato sensible)',
  ADD COLUMN IF NOT EXISTS presion_dx           VARCHAR(10) NULL COMMENT 'STEPS: nunca | normal | alta',
  ADD COLUMN IF NOT EXISTS glucosa_dx           VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS colesterol_dx        VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS antecedente_diabetes VARCHAR(15) NULL COMMENT 'FINDRISC: no | segundo_grado | primer_grado',
  ADD COLUMN IF NOT EXISTS sueno_horas          VARCHAR(10) NULL COMMENT 'menos_6 | 6_7 | 7_8 | 8_10 | mas_10',
  ADD COLUMN IF NOT EXISTS chequeo_ultimo       VARCHAR(15) NULL;

-- ── 3. PSICOLOGÍA: cuestionarios_psicologicos ────────────────────────
-- PHQ-8 y GAD-7: cada respuesta 0-3. DATOS SENSIBLES: nunca en ranking, feed ni exportaciones.
ALTER TABLE cuestionarios_psicologicos
  ADD COLUMN IF NOT EXISTS phq_interes       TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_animo         TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_sueno         TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_energia       TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_apetito       TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_autoestima    TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_concentracion TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq_movimiento    TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_nervios       TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_control       TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_preocupacion  TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_relajarse     TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_inquietud     TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_irritable     TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS gad_miedo         TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS phq8_total        TINYINT UNSIGNED NULL COMMENT '0-24',
  ADD COLUMN IF NOT EXISTS gad7_total        TINYINT UNSIGNED NULL COMMENT '0-21';

-- ── 4. ALIMENTACIÓN: cuestionarios_alimentacion ──────────────────────
ALTER TABLE cuestionarios_alimentacion
  ADD COLUMN IF NOT EXISTS verduras_dias     VARCHAR(10) NULL COMMENT 'CDMX 2023: 0_2 | 3_4 | 5_6 | diario',
  ADD COLUMN IF NOT EXISTS frutas_dias       VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS lacteos_dias      VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS agua_vasos        VARCHAR(10) NULL COMMENT '0_2 | 3_4 | 5_6 | 7_mas',
  ADD COLUMN IF NOT EXISTS carnes_procesadas VARCHAR(10) NULL COMMENT '5_mas | 3_4 | 1_2 | ninguno',
  ADD COLUMN IF NOT EXISTS comida_rapida     VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS dulces            VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS pan_dulce         VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS botanas           VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS refrescos_dias    VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS refrescos_vasos   VARCHAR(10) NULL COMMENT '4_mas | 2_3 | 1',
  ADD COLUMN IF NOT EXISTS desayuno_dias     VARCHAR(10) NULL COMMENT '0_1 | 2_3 | 4_5 | 6_7';

-- plan_semanal ya es LONGTEXT. Se valida que sea JSON.
ALTER TABLE cuestionarios_alimentacion
  MODIFY plan_semanal LONGTEXT NULL COMMENT 'Plan semanal generado por IA (JSON)'
    CHECK (plan_semanal IS NULL OR JSON_VALID(plan_semanal));

-- ── 5. IA: límite de peticiones por usuario (protege la GPU de la laptop) ──
CREATE TABLE IF NOT EXISTS ia_uso (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT UNSIGNED NOT NULL,
  tipo       VARCHAR(20)  NOT NULL COMMENT 'chat | dieta | rutina | diagnostico',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ia_uso_usuario_fecha (id_usuario, created_at),
  CONSTRAINT fk_ia_uso_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Verificación rápida ──────────────────────────────────────────────
SELECT 'cuestionario_habitos'       AS tabla, COUNT(*) AS columnas FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cuestionario_habitos'
UNION ALL SELECT 'cuestionarios_clinicos',     COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cuestionarios_clinicos'
UNION ALL SELECT 'cuestionarios_psicologicos', COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cuestionarios_psicologicos'
UNION ALL SELECT 'cuestionarios_alimentacion', COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cuestionarios_alimentacion';

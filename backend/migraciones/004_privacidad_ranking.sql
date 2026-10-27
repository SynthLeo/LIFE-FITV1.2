-- ============================================================
--  004 — Opción para ocultarse del ranking y del feed (M5)
--  Se puede correr dos veces sin romper nada.
-- ============================================================
ALTER TABLE usuario
  ADD COLUMN IF NOT EXISTS ocultar_ranking TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = no aparece en ranking ni feed de otros usuarios';

-- Evita duplicar un logro para el mismo usuario
ALTER TABLE usuario_logro ADD UNIQUE INDEX IF NOT EXISTS uq_usuario_logro (id_usuario, id_logro);
-- Un registro de misión por usuario, misión y periodo
ALTER TABLE usuario_mision ADD UNIQUE INDEX IF NOT EXISTS uq_usuario_mision_periodo (id_usuario, id_mision, fecha_inicio);

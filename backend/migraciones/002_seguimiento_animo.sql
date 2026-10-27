-- ============================================================
--  002 — Seguimiento de PHQ-8 y GAD-7 cada 2 semanas (M3)
--  Cada medición es un registro nuevo (no se sobrescribe).
--  Datos SENSIBLES: nunca se usan en ranking, feed ni social.
--  Se puede correr dos veces sin romper nada.
-- ============================================================

CREATE TABLE IF NOT EXISTS seguimiento_animo (
  id_seguimiento INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  id_usuario     INT UNSIGNED     NOT NULL,
  phq8_total     TINYINT UNSIGNED NOT NULL COMMENT '0-24',
  gad7_total     TINYINT UNSIGNED NOT NULL COMMENT '0-21',
  respuestas     JSON             NOT NULL COMMENT '15 respuestas 0-3',
  created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_seguimiento),
  KEY idx_seguimiento_usuario_fecha (id_usuario, created_at),
  CONSTRAINT fk_seguimiento_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  CONSTRAINT chk_seguimiento_json CHECK (JSON_VALID(respuestas))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

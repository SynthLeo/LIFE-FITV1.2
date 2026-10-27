-- ============================================================
--  003 — Límite de intentos de login (fuerza bruta)
--  Se puede correr dos veces sin romper nada.
-- ============================================================
CREATE TABLE IF NOT EXISTS login_intento (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45)  NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_email_fecha (email, created_at),
  KEY idx_login_ip_fecha (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

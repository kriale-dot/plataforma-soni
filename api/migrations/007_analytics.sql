-- SONI | 007 | Estatísticas próprias, sem dados pessoais de visitantes.
-- A contagem começa após a aplicação desta migração.
ALTER TABLE site_settings
  ADD COLUMN show_visit_counter TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS analytics_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_key CHAR(64) NOT NULL,
  started_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  pageviews INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_soni_analytics_session (session_key),
  KEY idx_soni_analytics_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_pageviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  page_path VARCHAR(90) NOT NULL,
  viewed_at DATETIME NOT NULL,
  KEY idx_soni_analytics_view_date (viewed_at),
  KEY idx_soni_analytics_page_date (page_path, viewed_at),
  KEY idx_soni_analytics_dedupe (session_id, page_path, viewed_at),
  CONSTRAINT fk_soni_analytics_session
    FOREIGN KEY (session_id) REFERENCES analytics_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

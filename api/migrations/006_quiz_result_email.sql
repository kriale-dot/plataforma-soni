-- Uma solicitação de envio por tentativa (proteção básica contra abuso).
CREATE TABLE IF NOT EXISTS quiz_result_email_sends (
  attempt_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  email VARCHAR(254) NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_soni_result_email_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

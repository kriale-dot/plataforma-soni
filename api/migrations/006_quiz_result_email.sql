-- Uma solicitação de envio por tentativa; apagada em cascata com a tentativa.
CREATE TABLE IF NOT EXISTS quiz_result_emails (
  attempt_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  recipient_email VARCHAR(254) NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_soni_result_email_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

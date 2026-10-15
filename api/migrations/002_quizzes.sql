-- SONI - Migração 002 - Teste seus Conhecimentos
-- Banco existente preservado. Questões e alternativas aceitam texto, imagem e áudio.

CREATE TABLE IF NOT EXISTS quiz_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quizzes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description VARCHAR(1000) NULL,
  publication_status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_quizzes_public (publication_status, category_id, id),
  CONSTRAINT fk_soni_quiz_category FOREIGN KEY (category_id) REFERENCES quiz_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  prompt_text TEXT NULL,
  image_media_id BIGINT UNSIGNED NULL,
  audio_media_id BIGINT UNSIGNED NULL,
  explanation TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_quiz_question_order (quiz_id, sort_order, id),
  CONSTRAINT fk_soni_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_soni_qq_image FOREIGN KEY (image_media_id) REFERENCES site_media(id) ON DELETE SET NULL,
  CONSTRAINT fk_soni_qq_audio FOREIGN KEY (audio_media_id) REFERENCES site_media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  option_text TEXT NULL,
  image_media_id BIGINT UNSIGNED NULL,
  audio_media_id BIGINT UNSIGNED NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_quiz_options_question (question_id, sort_order, id),
  CONSTRAINT fk_soni_qo_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_soni_qo_image FOREIGN KEY (image_media_id) REFERENCES site_media(id) ON DELETE SET NULL,
  CONSTRAINT fk_soni_qo_audio FOREIGN KEY (audio_media_id) REFERENCES site_media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  total_questions INT UNSIGNED NOT NULL,
  correct_answers INT UNSIGNED NOT NULL,
  score_percent DECIMAL(5,2) NOT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_quiz_attempts_quiz (quiz_id, submitted_at),
  CONSTRAINT fk_soni_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempt_answers (
  attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NOT NULL,
  was_correct TINYINT(1) NOT NULL,
  PRIMARY KEY (attempt_id, question_id),
  CONSTRAINT fk_soni_aa_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_soni_aa_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_soni_aa_option FOREIGN KEY (option_id) REFERENCES quiz_options(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categoria inicial, sem conteúdo inventado nem teste publicado automaticamente.
INSERT INTO quiz_categories (title, description, sort_order)
SELECT 'Fundamentos de Sonorização', 'Microfones, mixagem, ganho, acústica e operação de som nas igrejas.', 10
WHERE NOT EXISTS (SELECT 1 FROM quiz_categories WHERE title = 'Fundamentos de Sonorização');

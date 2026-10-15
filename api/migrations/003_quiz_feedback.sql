-- SONI - 003: faixas de devolutiva personalizada por teste.
-- Não altera as tabelas antigas nem apaga tentativas.

CREATE TABLE IF NOT EXISTS quiz_feedback_ranges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quiz_id BIGINT UNSIGNED NOT NULL,
  min_percent DECIMAL(5,2) NOT NULL,
  max_percent DECIMAL(5,2) NOT NULL,
  label VARCHAR(120) NOT NULL,
  feedback_text TEXT NOT NULL,
  study_recommendation TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_soni_feedback_quiz (quiz_id, min_percent),
  CONSTRAINT fk_soni_feedback_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  CONSTRAINT chk_soni_feedback_percent CHECK (min_percent >= 0 AND max_percent <= 100 AND min_percent <= max_percent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempt_feedback (
  attempt_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  feedback_text TEXT NOT NULL,
  study_recommendation TEXT NULL,
  min_percent DECIMAL(5,2) NOT NULL,
  max_percent DECIMAL(5,2) NOT NULL,
  CONSTRAINT fk_soni_attempt_feedback FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Padrões apenas para testes existentes; podem ser editados individualmente no painel.
INSERT INTO quiz_feedback_ranges (quiz_id, min_percent, max_percent, label, feedback_text, study_recommendation, sort_order)
SELECT q.id, defaults.min_percent, defaults.max_percent, defaults.label,
       defaults.feedback_text, defaults.study_recommendation, defaults.sort_order
FROM quizzes q
CROSS JOIN (
  SELECT 0.00 AS min_percent, 39.99 AS max_percent, 'Conhecimento inicial' AS label,
    'Você está começando sua jornada. Os fundamentos ainda precisam de atenção.' AS feedback_text,
    'Revise os conceitos básicos e refaça o teste depois de estudar.' AS study_recommendation, 10 AS sort_order
  UNION ALL
  SELECT 40.00, 59.99, 'Em desenvolvimento',
    'Você reconhece alguns conceitos, mas ainda há conhecimentos importantes a fortalecer.',
    'Releia as explicações das questões e pratique os assuntos em que errou.', 20
  UNION ALL
  SELECT 60.00, 79.99, 'Bom desempenho',
    'Você demonstra boa compreensão dos assuntos avaliados.',
    'Aprofunde os temas em que ainda teve dificuldade.', 30
  UNION ALL
  SELECT 80.00, 94.99, 'Muito bom',
    'Você apresenta conhecimento consistente sobre este conteúdo.',
    'Continue praticando para consolidar os detalhes técnicos.', 40
  UNION ALL
  SELECT 95.00, 100.00, 'Excelente',
    'Parabéns! Você demonstrou excelente domínio do conteúdo avaliado.',
    'Mantenha a prática e compartilhe os conhecimentos com sua equipe.', 50
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM quiz_feedback_ranges x WHERE x.quiz_id = q.id);

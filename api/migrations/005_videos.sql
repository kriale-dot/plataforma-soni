-- SONI 005 — Biblioteca de vídeos (YouTube e MP4/WebM)
-- Não altera dados das versões 001 a 004.
CREATE TABLE IF NOT EXISTS video_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  source_type ENUM('YOUTUBE','UPLOAD') NOT NULL,
  youtube_id CHAR(11) NULL,
  media_id BIGINT UNSIGNED NULL,
  publication_status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_soni_videos_media (media_id),
  INDEX idx_soni_videos_public (publication_status, is_visible, category_id, id),
  CONSTRAINT fk_soni_video_category FOREIGN KEY (category_id) REFERENCES video_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_soni_video_media FOREIGN KEY (media_id) REFERENCES site_media(id) ON DELETE RESTRICT,
  CONSTRAINT chk_soni_video_source CHECK (
    (source_type = 'YOUTUBE' AND youtube_id IS NOT NULL AND media_id IS NULL) OR
    (source_type = 'UPLOAD' AND youtube_id IS NULL AND media_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO video_categories (title, description, sort_order)
SELECT 'Aulas de Sonorização', 'Vídeos técnicos e demonstrações práticas para equipes de som.', 10
WHERE NOT EXISTS (SELECT 1 FROM video_categories WHERE title = 'Aulas de Sonorização');

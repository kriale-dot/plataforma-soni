-- SONI v0.8.0 — Biblioteca pública de Documentos (PDF e PNG).
-- Mantém intactas as tabelas e o conteúdo das versões anteriores.
ALTER TABLE site_media MODIFY kind ENUM('IMAGE','AUDIO','VIDEO','DOCUMENT') NOT NULL;

CREATE TABLE IF NOT EXISTS document_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_soni_document_category_order (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  media_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  publication_status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_soni_doc_media (media_id),
  INDEX idx_soni_doc_public (publication_status, is_visible, category_id, id),
  CONSTRAINT fk_soni_doc_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_soni_doc_media FOREIGN KEY (media_id) REFERENCES site_media(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_categories (title, description, sort_order)
SELECT 'Materiais de Sonorização', 'Guias, diagramas e conteúdos para equipes de som das igrejas.', 10
WHERE NOT EXISTS (SELECT 1 FROM document_categories WHERE title = 'Materiais de Sonorização');

-- SONI | 001 | MariaDB 11.4 e MySQL 8.
-- Criar apenas o banco dedicado plataforma_soni; não executar contra outros projetos.

CREATE TABLE IF NOT EXISTS site_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('IMAGE','AUDIO','VIDEO') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(600) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  site_title VARCHAR(160) NOT NULL,
  site_description TEXT NULL,
  meta_title VARCHAR(160) NULL,
  meta_description VARCHAR(300) NULL,
  contact_email VARCHAR(200) NULL,
  contact_phone VARCHAR(70) NULL,
  logo_media_id BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_soni_site_logo FOREIGN KEY (logo_media_id) REFERENCES site_media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type ENUM('HERO','TITULO','TEXTO','IMAGEM','TEXTO_IMAGEM','VIDEO','AUDIO','CTA','LINK','TESTES','DOCUMENTOS','VIDEOS') NOT NULL,
  title VARCHAR(255) NULL,
  subtitle VARCHAR(300) NULL,
  body_text TEXT NULL,
  media_id BIGINT UNSIGNED NULL,
  media_url VARCHAR(1000) NULL,
  alt_text VARCHAR(350) NULL,
  button_label VARCHAR(120) NULL,
  button_url VARCHAR(1000) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  publication_status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_soni_site_public (publication_status, is_visible, sort_order, id),
  CONSTRAINT fk_soni_block_media FOREIGN KEY (media_id) REFERENCES site_media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (id, site_title, site_description, meta_title, meta_description)
VALUES (1, 'SONI | Sonorização de Igrejas',
  'Capacitação e conhecimento técnico para quem serve na sonorização de igrejas.',
  'SONI | Sonorização de Igrejas',
  'Teste seus conhecimentos e explore materiais educativos sobre sonorização de igrejas.')
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO site_blocks (type, title, subtitle, body_text, button_label, button_url, sort_order, publication_status)
SELECT 'HERO', 'Técnica para servir. Som para conectar.', 'CONHECIMENTO QUE TRANSFORMA O SOM',
  'Aprenda sonorização de igrejas com recursos para estudo e avaliações interativas.',
  'Conheça os testes', '/testes', 10, 'PUBLISHED'
WHERE NOT EXISTS (SELECT 1 FROM site_blocks WHERE type = 'HERO' AND sort_order = 10);

INSERT INTO site_blocks (type, title, body_text, button_label, button_url, sort_order, publication_status)
SELECT 'TESTES', 'Teste seus conhecimentos', 'Desafios sobre microfones, mesas de som, equalização e acústica.',
  'Ver testes', '/testes', 20, 'PUBLISHED'
WHERE NOT EXISTS (SELECT 1 FROM site_blocks WHERE type = 'TESTES' AND sort_order = 20);

INSERT INTO site_blocks (type, title, body_text, button_label, button_url, sort_order, publication_status)
SELECT 'DOCUMENTOS', 'Biblioteca de documentos', 'Guias e materiais em PDF e PNG.',
  'Ver documentos', '/documentos', 30, 'PUBLISHED'
WHERE NOT EXISTS (SELECT 1 FROM site_blocks WHERE type = 'DOCUMENTOS' AND sort_order = 30);

INSERT INTO site_blocks (type, title, body_text, button_label, button_url, sort_order, publication_status)
SELECT 'VIDEOS', 'Biblioteca de vídeos', 'Aulas e conteúdos educativos do YouTube e da SONI.',
  'Ver vídeos', '/videos', 40, 'PUBLISHED'
WHERE NOT EXISTS (SELECT 1 FROM site_blocks WHERE type = 'VIDEOS' AND sort_order = 40);

INSERT INTO site_blocks (type, title, body_text, sort_order, publication_status)
SELECT 'TEXTO', 'Mais do que operar equipamentos.',
  'Facilitamos o aprendizado técnico para apoiar equipes e melhorar a clareza da sonorização.',
  50, 'PUBLISHED'
WHERE NOT EXISTS (SELECT 1 FROM site_blocks WHERE type = 'TEXTO' AND sort_order = 50);

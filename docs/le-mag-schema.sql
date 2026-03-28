CREATE TABLE IF NOT EXISTS blog_categories (
  id VARCHAR(120) PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  excerpt TEXT NOT NULL,
  content_html LONGTEXT NOT NULL,
  category_id VARCHAR(120) NOT NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  author_name VARCHAR(100) NOT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description TEXT NULL,
  cta_variant ENUM('contact', 'visit') NOT NULL DEFAULT 'contact',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_blog_posts_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_blog_posts_status_date (status, published_at),
  INDEX idx_blog_posts_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_text TEXT NOT NULL,
  person_name VARCHAR(160) NOT NULL,
  person_role VARCHAR(160) NULL,
  area_label VARCHAR(160) NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  consent_publication TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_testimonials_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_post_testimonials (
  post_id INT NOT NULL,
  testimonial_id INT NOT NULL,
  position INT NOT NULL DEFAULT 1,
  PRIMARY KEY (post_id, testimonial_id),
  CONSTRAINT fk_pt_post FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_testimonial FOREIGN KEY (testimonial_id) REFERENCES blog_testimonials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO blog_categories (id, name, description, sort_order)
VALUES
  ('colocation-senior', 'Colocation senior', 'Comprendre le modèle de maison partagée.', 1),
  ('bien-vieillir', 'Bien vieillir', 'Préserver autonomie et lien social.', 2),
  ('conseils-aux-familles', 'Conseils aux familles', 'Repères pour accompagner un proche.', 3),
  ('vie-en-maison-partagee', 'Vie en maison partagée', 'Le quotidien dans les maisons.', 4),
  ('actualites-maison-melina', 'Actualités Maison Mélina', 'Temps forts et nouveautés.', 5)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  sort_order = VALUES(sort_order);

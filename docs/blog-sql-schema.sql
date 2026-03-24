CREATE TABLE IF NOT EXISTS blog_categories (
  id VARCHAR(120) PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
  id CHAR(36) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  excerpt TEXT NOT NULL,
  content LONGTEXT NOT NULL,
  category_id VARCHAR(120) NOT NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  author VARCHAR(150) NOT NULL,
  image_url VARCHAR(500) NULL,
  image_alt VARCHAR(255) NULL,
  seo_title VARCHAR(255) NULL,
  seo_description TEXT NULL,
  cta_label VARCHAR(255) NULL,
  cta_url VARCHAR(500) NULL,
  reading_time_minutes INT NOT NULL DEFAULT 1,
  related_slugs JSON NULL,
  CONSTRAINT fk_blog_posts_category FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_blog_posts_status_date (status, published_at),
  INDEX idx_blog_posts_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

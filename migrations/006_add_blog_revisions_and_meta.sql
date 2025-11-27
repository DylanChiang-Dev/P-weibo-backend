-- Migration: Add Blog Article Revisions and Auto-save
-- Description: Add version history and meta tags for blog articles
-- Author: System
-- Date: 2025-11-27

-- 1. Article Revisions Table (历史版本)
CREATE TABLE IF NOT EXISTS blog_article_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    revision_number INT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES blog_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_article_id (article_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Update blog_articles table for auto-save and meta tags
ALTER TABLE blog_articles 
ADD COLUMN IF NOT EXISTS last_auto_saved_at DATETIME,
ADD COLUMN IF NOT EXISTS seo_title VARCHAR(255),
ADD COLUMN IF NOT EXISTS seo_description VARCHAR(500),
ADD COLUMN IF NOT EXISTS seo_keywords VARCHAR(255);

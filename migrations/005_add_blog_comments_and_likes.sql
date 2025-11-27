-- Migration: Add Blog Comments and Likes
-- Description: Add independent comment system with moderation and like functionality
-- Author: System
-- Date: 2025-11-27

-- 1. Blog Comments Table (独立评论系统)
CREATE TABLE IF NOT EXISTS blog_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    user_id INT NULL,                    -- NULL = guest comment
    author_name VARCHAR(100),            -- Guest name
    author_email VARCHAR(255),           -- Guest email
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES blog_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_article_id (article_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Blog Article Likes Table (点赞功能)
CREATE TABLE IF NOT EXISTS blog_article_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    user_id INT NULL,                    -- NULL = guest like
    ip_address VARCHAR(45),              -- For guest deduplication
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES blog_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_like (article_id, user_id),
    UNIQUE KEY unique_ip_like (article_id, ip_address),
    INDEX idx_article_id (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Update blog_articles table to add counters and search index
ALTER TABLE blog_articles 
ADD COLUMN IF NOT EXISTS like_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS comment_count INT DEFAULT 0;

-- Add fulltext index for search (if not exists)
-- Note: Check if index exists before creating
ALTER TABLE blog_articles 
ADD FULLTEXT INDEX idx_fulltext_search (title, content);

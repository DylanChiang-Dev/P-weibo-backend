-- Migration: Add Media Metadata P0 - Core Fields
-- Description: Add title, original_title, cover_image_cdn, cover_image_local to all media tables
-- Priority: P0 - Fixes "Untitled" and blank image issues
-- Date: 2024-12-05

-- ============================================
-- 1. user_movies - 新增所有字段
-- ============================================
ALTER TABLE user_movies 
ADD COLUMN title VARCHAR(500) NULL AFTER tmdb_id,
ADD COLUMN original_title VARCHAR(500) NULL AFTER title,
ADD COLUMN cover_image_cdn TEXT NULL AFTER original_title,
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn,
ADD INDEX idx_movies_title (title(255));

-- ============================================
-- 2. user_tv_shows - 新增所有字段
-- ============================================
ALTER TABLE user_tv_shows 
ADD COLUMN title VARCHAR(500) NULL AFTER tmdb_id,
ADD COLUMN original_title VARCHAR(500) NULL AFTER title,
ADD COLUMN cover_image_cdn TEXT NULL AFTER original_title,
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn,
ADD INDEX idx_tv_title (title(255));

-- ============================================
-- 3. user_documentaries - 新增所有字段
-- ============================================
ALTER TABLE user_documentaries 
ADD COLUMN title VARCHAR(500) NULL AFTER tmdb_id,
ADD COLUMN original_title VARCHAR(500) NULL AFTER title,
ADD COLUMN cover_image_cdn TEXT NULL AFTER original_title,
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn,
ADD INDEX idx_docs_title (title(255));

-- ============================================
-- 4. user_books - 新增 title 和圖片字段
-- ============================================
ALTER TABLE user_books 
ADD COLUMN title VARCHAR(500) NULL AFTER isbn,
ADD COLUMN original_title VARCHAR(500) NULL AFTER title,
ADD COLUMN cover_image_cdn TEXT NULL AFTER original_title,
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn,
ADD INDEX idx_books_title (title(255));

-- ============================================
-- 5. user_anime - 已有 title, cover_url；添加 cover_image_local, original_title
-- ============================================
ALTER TABLE user_anime 
ADD COLUMN original_title VARCHAR(500) NULL AFTER title,
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn;

-- ============================================
-- 6. user_games - 已有 name, cover_url；添加 cover_image_local
-- ============================================
ALTER TABLE user_games 
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn;

-- ============================================
-- 7. user_podcasts - 已有 title, artwork_url；添加 cover_image_local
-- ============================================
ALTER TABLE user_podcasts 
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn;

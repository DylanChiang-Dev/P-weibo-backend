-- Migration: Add Media-Specific Fields
-- Description: Add type-specific metadata fields for each media type
-- Priority: P2 - Enhanced feature experience
-- Date: 2024-12-05

-- ============================================
-- 1. user_movies - 電影特定字段
-- ============================================
ALTER TABLE user_movies 
ADD COLUMN runtime INT NULL COMMENT '片長（分鐘）' AFTER backdrop_image_local,
ADD COLUMN tagline VARCHAR(500) NULL COMMENT '電影標語' AFTER runtime,
ADD COLUMN director VARCHAR(255) NULL COMMENT '導演' AFTER tagline,
ADD COLUMN cast JSON NULL COMMENT '主要演員列表' AFTER director;

-- ============================================
-- 2. user_tv_shows - 電視劇特定字段
-- ============================================
ALTER TABLE user_tv_shows 
ADD COLUMN number_of_seasons INT NULL COMMENT '總季數' AFTER backdrop_image_local,
ADD COLUMN number_of_episodes INT NULL COMMENT '總集數' AFTER number_of_seasons,
ADD COLUMN episode_runtime INT NULL COMMENT '單集時長（分鐘）' AFTER number_of_episodes,
ADD COLUMN networks JSON NULL COMMENT '播出平台/電視台' AFTER episode_runtime;

-- ============================================
-- 3. user_documentaries - 紀錄片特定字段 (與電視劇類似)
-- ============================================
ALTER TABLE user_documentaries 
ADD COLUMN number_of_seasons INT NULL COMMENT '總季數' AFTER backdrop_image_local,
ADD COLUMN number_of_episodes INT NULL COMMENT '總集數' AFTER number_of_seasons,
ADD COLUMN episode_runtime INT NULL COMMENT '單集時長（分鐘）' AFTER number_of_episodes,
ADD COLUMN networks JSON NULL COMMENT '播出平台/電視台' AFTER episode_runtime;

-- ============================================
-- 4. user_anime - 動畫特定字段
-- ============================================
ALTER TABLE user_anime 
ADD COLUMN format VARCHAR(50) NULL COMMENT '類型 (TV/MOVIE/OVA/ONA/SPECIAL)' AFTER backdrop_image_local,
ADD COLUMN season_info VARCHAR(50) NULL COMMENT '播出季度，如 WINTER 2024' AFTER format,
ADD COLUMN studio VARCHAR(255) NULL COMMENT '製作公司' AFTER season_info,
ADD COLUMN source VARCHAR(50) NULL COMMENT '原作類型 (MANGA/NOVEL/ORIGINAL 等)' AFTER studio;
-- Note: episodes column may already exist as total_episodes, keeping separate for clarity

-- ============================================
-- 5. user_books - 書籍特定字段
-- ============================================
ALTER TABLE user_books 
ADD COLUMN authors JSON NULL COMMENT '作者列表' AFTER external_rating,
ADD COLUMN publisher VARCHAR(255) NULL COMMENT '出版社' AFTER authors,
ADD COLUMN published_date DATE NULL COMMENT '出版日期' AFTER publisher,
ADD COLUMN page_count INT NULL COMMENT '頁數' AFTER published_date,
ADD COLUMN isbn_10 VARCHAR(13) NULL COMMENT 'ISBN-10' AFTER page_count,
ADD COLUMN isbn_13 VARCHAR(17) NULL COMMENT 'ISBN-13' AFTER isbn_10,
ADD COLUMN language VARCHAR(10) NULL COMMENT '語言代碼，如 zh, en' AFTER isbn_13;

-- ============================================
-- 6. user_games - 遊戲特定字段
-- ============================================
ALTER TABLE user_games 
ADD COLUMN platforms JSON NULL COMMENT '平台列表，如 ["PC", "PS5", "Switch"]' AFTER backdrop_image_local,
ADD COLUMN developers JSON NULL COMMENT '開發商列表' AFTER platforms,
ADD COLUMN publishers JSON NULL COMMENT '發行商列表' AFTER developers,
ADD COLUMN game_modes JSON NULL COMMENT '遊戲模式，如 ["單人", "多人"]' AFTER publishers;

-- ============================================
-- 7. user_podcasts - 播客特定字段
-- ============================================
ALTER TABLE user_podcasts 
ADD COLUMN artist_name VARCHAR(255) NULL COMMENT '主播/創作者名稱' AFTER external_rating,
ADD COLUMN feed_url TEXT NULL COMMENT 'RSS 訂閱源 URL' AFTER artist_name,
ADD COLUMN episode_count INT NULL COMMENT '節目集數' AFTER feed_url,
ADD COLUMN explicit BOOLEAN DEFAULT FALSE COMMENT '是否包含成人內容' AFTER episode_count;

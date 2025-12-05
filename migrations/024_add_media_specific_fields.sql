-- Migration: Add Media-Specific Fields
-- Description: Add type-specific metadata fields for each media type
-- Priority: P2 - Enhanced feature experience
-- Date: 2024-12-05

-- ============================================
-- 1. user_movies - 電影特定字段
-- ============================================
ALTER TABLE user_movies 
ADD COLUMN runtime INT NULL AFTER backdrop_image_local COMMENT '片長（分鐘）',
ADD COLUMN tagline VARCHAR(500) NULL AFTER runtime COMMENT '電影標語',
ADD COLUMN director VARCHAR(255) NULL AFTER tagline COMMENT '導演',
ADD COLUMN cast JSON NULL AFTER director COMMENT '主要演員列表';

-- ============================================
-- 2. user_tv_shows - 電視劇特定字段
-- ============================================
ALTER TABLE user_tv_shows 
ADD COLUMN number_of_seasons INT NULL AFTER backdrop_image_local COMMENT '總季數',
ADD COLUMN number_of_episodes INT NULL AFTER number_of_seasons COMMENT '總集數',
ADD COLUMN episode_runtime INT NULL AFTER number_of_episodes COMMENT '單集時長（分鐘）',
ADD COLUMN networks JSON NULL AFTER episode_runtime COMMENT '播出平台/電視台';

-- ============================================
-- 3. user_documentaries - 紀錄片特定字段 (與電視劇類似)
-- ============================================
ALTER TABLE user_documentaries 
ADD COLUMN number_of_seasons INT NULL AFTER backdrop_image_local COMMENT '總季數',
ADD COLUMN number_of_episodes INT NULL AFTER number_of_seasons COMMENT '總集數',
ADD COLUMN episode_runtime INT NULL AFTER number_of_episodes COMMENT '單集時長（分鐘）',
ADD COLUMN networks JSON NULL AFTER episode_runtime COMMENT '播出平台/電視台';

-- ============================================
-- 4. user_anime - 動畫特定字段
-- ============================================
ALTER TABLE user_anime 
ADD COLUMN format VARCHAR(50) NULL AFTER backdrop_image_local COMMENT '類型 (TV/MOVIE/OVA/ONA/SPECIAL)',
ADD COLUMN season_info VARCHAR(50) NULL AFTER format COMMENT '播出季度，如 WINTER 2024',
ADD COLUMN studio VARCHAR(255) NULL AFTER season_info COMMENT '製作公司',
ADD COLUMN source VARCHAR(50) NULL AFTER studio COMMENT '原作類型 (MANGA/NOVEL/ORIGINAL 等)';
-- Note: episodes column may already exist as total_episodes, keeping separate for clarity

-- ============================================
-- 5. user_books - 書籍特定字段
-- ============================================
ALTER TABLE user_books 
ADD COLUMN authors JSON NULL AFTER external_rating COMMENT '作者列表',
ADD COLUMN publisher VARCHAR(255) NULL AFTER authors COMMENT '出版社',
ADD COLUMN published_date DATE NULL AFTER publisher COMMENT '出版日期',
ADD COLUMN page_count INT NULL AFTER published_date COMMENT '頁數',
ADD COLUMN isbn_10 VARCHAR(13) NULL AFTER page_count COMMENT 'ISBN-10',
ADD COLUMN isbn_13 VARCHAR(17) NULL AFTER isbn_10 COMMENT 'ISBN-13',
ADD COLUMN language VARCHAR(10) NULL AFTER isbn_13 COMMENT '語言代碼，如 zh, en';

-- ============================================
-- 6. user_games - 遊戲特定字段
-- ============================================
ALTER TABLE user_games 
ADD COLUMN platforms JSON NULL AFTER backdrop_image_local COMMENT '平台列表，如 ["PC", "PS5", "Switch"]',
ADD COLUMN developers JSON NULL AFTER platforms COMMENT '開發商列表',
ADD COLUMN publishers JSON NULL AFTER developers COMMENT '發行商列表',
ADD COLUMN game_modes JSON NULL AFTER publishers COMMENT '遊戲模式，如 ["單人", "多人"]';

-- ============================================
-- 7. user_podcasts - 播客特定字段
-- ============================================
ALTER TABLE user_podcasts 
ADD COLUMN artist_name VARCHAR(255) NULL AFTER external_rating COMMENT '主播/創作者名稱',
ADD COLUMN feed_url TEXT NULL AFTER artist_name COMMENT 'RSS 訂閱源 URL',
ADD COLUMN episode_count INT NULL AFTER feed_url COMMENT '節目集數',
ADD COLUMN explicit BOOLEAN DEFAULT FALSE AFTER episode_count COMMENT '是否包含成人內容';

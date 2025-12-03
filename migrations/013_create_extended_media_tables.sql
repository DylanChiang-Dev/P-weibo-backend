-- Migration: Create Extended Media Library Tables
-- Description: Add support for Podcasts, Documentaries, and Anime
-- Date: 2024-12-04

-- 1. Podcasts Table
CREATE TABLE IF NOT EXISTS user_podcasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  podcast_id VARCHAR(100),  -- External API ID (e.g., Listen Notes)
  
  -- Basic info
  title VARCHAR(255),
  host VARCHAR(255),
  rss_feed VARCHAR(500),
  
  -- Personal tracking
  my_rating DECIMAL(3,1),
  my_review TEXT,
  episodes_listened INT DEFAULT 0,
  total_episodes INT,
  status ENUM('listening', 'completed', 'dropped', 'plan_to_listen') DEFAULT 'listening',
  
  -- Time info
  first_release_date DATE,
  completed_date DATE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Documentaries Table (类似电影结构)
CREATE TABLE IF NOT EXISTS user_documentaries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tmdb_id INT,  -- 使用TMDB作为数据源
  
  -- Personal info
  my_rating DECIMAL(3,1),
  my_review TEXT,
  status ENUM('want_to_watch', 'watching', 'watched') DEFAULT 'watched',
  
  -- Time info
  release_date DATE,
  completed_date DATE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_documentary (user_id, tmdb_id),
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Anime Table
CREATE TABLE IF NOT EXISTS user_anime (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  anime_id INT NOT NULL,  -- MyAnimeList or Anilist ID
  
  -- Personal tracking
  my_rating DECIMAL(3,1),
  my_review TEXT,
  episodes_watched INT DEFAULT 0,
  total_episodes INT,
  status ENUM('watching', 'completed', 'dropped', 'plan_to_watch', 'on_hold') DEFAULT 'watching',
  
  -- Time info
  first_air_date DATE,
  completed_date DATE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_anime (user_id, anime_id),
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_first_air_date (first_air_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

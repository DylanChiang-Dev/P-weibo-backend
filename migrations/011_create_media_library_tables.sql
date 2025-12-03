-- Migration: Create Media Library Tables
-- Description: Add tables for personal media library (movies, TV shows, books, games)
-- Date: 2024-12-03

-- 1. User Movies Table
CREATE TABLE IF NOT EXISTS user_movies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tmdb_id INT NOT NULL,
  
  -- Personal info
  my_rating DECIMAL(2,1),
  my_review TEXT,
  status ENUM('want_to_watch', 'watching', 'watched') DEFAULT 'watched',
  
  -- Time info
  release_date DATE,           -- Movie release date
  completed_date DATE,         -- Date I finished watching
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_movie (user_id, tmdb_id),
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User TV Shows Table
CREATE TABLE IF NOT EXISTS user_tv_shows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tmdb_id INT NOT NULL,
  
  -- Personal info
  my_rating DECIMAL(2,1),
  my_review TEXT,
  current_season INT,
  current_episode INT,
  status ENUM('want_to_watch', 'watching', 'watched', 'on_hold') DEFAULT 'watching',
  
  -- Time info
  first_air_date DATE,         -- First air date
  completed_date DATE,         -- Date I finished watching
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_tv (user_id, tmdb_id),
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_first_air_date (first_air_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. User Books Table
CREATE TABLE IF NOT EXISTS user_books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  google_books_id VARCHAR(50),
  isbn VARCHAR(20),
  
  -- Personal info
  my_rating DECIMAL(2,1),
  my_review TEXT,
  status ENUM('want_to_read', 'reading', 'read') DEFAULT 'read',
  
  -- Time info
  publication_date DATE,       -- Publication date
  completed_date DATE,         -- Date I finished reading
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_publication_date (publication_date),
  INDEX idx_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User Games Table
CREATE TABLE IF NOT EXISTS user_games (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  rawg_id INT NOT NULL,
  
  -- Personal info
  my_rating DECIMAL(2,1),
  my_review TEXT,
  playtime_hours INT,
  platform VARCHAR(100),
  status ENUM('want_to_play', 'playing', 'played', 'dropped') DEFAULT 'played',
  
  -- Time info
  release_date DATE,           -- Game release date
  completed_date DATE,         -- Date I finished/completed
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_game (user_id, rawg_id),
  INDEX idx_user_status (user_id, status),
  INDEX idx_completed_date (completed_date),
  INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

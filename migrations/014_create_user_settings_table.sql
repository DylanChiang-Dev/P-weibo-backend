-- Migration: Create User Settings Table
-- Description: Store user-specific API keys and configurations
-- Date: 2024-12-04

CREATE TABLE IF NOT EXISTS user_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  
  -- API Keys (stored as JSON TEXT)
  -- Format: {"tmdb_api_key": "...", "rawg_api_key": "...", "google_books_api_key": "..."}
  api_keys TEXT,
  
  -- Additional settings (reserved for future use)
  preferences TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

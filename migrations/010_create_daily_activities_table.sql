-- Migration: Create Daily Activities Table
-- Description: Add daily activities tracking for exercise, reading, and Duolingo
-- Date: 2024-12-03

CREATE TABLE IF NOT EXISTS daily_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  activity_type ENUM('exercise', 'reading', 'duolingo') NOT NULL,
  activity_date DATE NOT NULL,
  
  -- Quantitative metrics (different fields used based on activity type)
  duration_minutes INT,          -- Exercise/Reading duration
  pages_read INT,                -- Pages read (for reading)
  xp_earned INT,                 -- Duolingo XP earned
  courses_completed INT,         -- Duolingo courses completed
  
  -- Additional info
  notes TEXT,                    -- User notes
  intensity ENUM('low', 'medium', 'high'),  -- Exercise intensity
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_activity_date (user_id, activity_type, activity_date),
  INDEX idx_user_type_date (user_id, activity_type, activity_date),
  INDEX idx_date (activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

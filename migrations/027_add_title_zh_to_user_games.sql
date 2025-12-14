-- Migration: Add title_zh to user_games
-- Description: Optional Chinese title for display (title_zh ?? name)
-- Date: 2025-12-14

ALTER TABLE user_games
ADD COLUMN title_zh VARCHAR(255) NULL AFTER name;


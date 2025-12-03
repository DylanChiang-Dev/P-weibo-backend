-- Migration: Add IGDB Support to Games
-- Description: Add igdb_id column to user_games table for IGDB integration
-- Date: 2024-12-04

ALTER TABLE user_games 
ADD COLUMN igdb_id INT NULL AFTER rawg_id,
ADD INDEX idx_igdb_id (igdb_id);

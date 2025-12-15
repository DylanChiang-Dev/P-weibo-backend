-- Migration: Add source/source_id to user_games (manual/igdb/rawg)
-- Description: Support manual-added games without faking rawg_id, and record the canonical external source.
-- Date: 2025-12-15

ALTER TABLE user_games
  ADD COLUMN IF NOT EXISTS source ENUM('manual','igdb','rawg') NOT NULL DEFAULT 'manual' AFTER igdb_id;

ALTER TABLE user_games
  ADD COLUMN IF NOT EXISTS source_id INT NULL AFTER source;

ALTER TABLE user_games
  ADD INDEX idx_user_games_source (source, source_id);

-- Backfill source/source_id for existing rows
UPDATE user_games SET source = 'igdb', source_id = igdb_id WHERE igdb_id IS NOT NULL;
UPDATE user_games SET source = 'rawg', source_id = rawg_id WHERE igdb_id IS NULL AND rawg_id IS NOT NULL;

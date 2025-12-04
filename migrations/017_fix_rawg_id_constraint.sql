-- Migration: Fix rawg_id constraint for IGDB migration
-- Description: Allow rawg_id to be NULL and update unique constraint
-- Date: 2024-12-04

-- Step 1: Drop the existing unique constraint
ALTER TABLE user_games DROP INDEX unique_user_game;

-- Step 2: Make rawg_id nullable
ALTER TABLE user_games MODIFY rawg_id INT NULL;

-- Step 3: Add new unique constraints for both ID types
ALTER TABLE user_games 
ADD UNIQUE INDEX unique_user_igdb (user_id, igdb_id),
ADD UNIQUE INDEX unique_user_rawg (user_id, rawg_id);

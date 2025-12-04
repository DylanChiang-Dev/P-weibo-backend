-- Migration: Add missing fields for games
-- Description: Add name, cover_url, and rename review field to match frontend
-- Date: 2024-12-04

-- Add name field for storing game title
ALTER TABLE user_games 
ADD COLUMN name VARCHAR(255) NULL AFTER igdb_id;

-- Add cover_url for storing cover image
ALTER TABLE user_games 
ADD COLUMN cover_url TEXT NULL AFTER name;

-- Note: my_review already exists, frontend sends 'review' but controller maps it
-- Note: completed_date exists, frontend sends 'date' but controller maps it

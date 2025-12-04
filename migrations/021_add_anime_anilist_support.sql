-- Migration: Add Anilist support for Anime
-- Description: Add anilist_id, title, cover_url, release_date fields
-- Date: 2024-12-04

-- Add anilist_id column
ALTER TABLE user_anime 
ADD COLUMN anilist_id INT NULL AFTER anime_id,
ADD INDEX idx_anilist_id (anilist_id);

-- Add title and cover_url for local storage
ALTER TABLE user_anime 
ADD COLUMN title VARCHAR(255) NULL AFTER anilist_id,
ADD COLUMN cover_url TEXT NULL AFTER title;

-- Add release_date for frontend compatibility
ALTER TABLE user_anime 
ADD COLUMN release_date DATE NULL AFTER first_air_date;

-- Make anime_id nullable (can use anilist_id instead)
ALTER TABLE user_anime 
MODIFY COLUMN anime_id INT NULL;

-- Drop the NOT NULL constraint by recreating the unique key
ALTER TABLE user_anime DROP INDEX unique_user_anime;
ALTER TABLE user_anime 
ADD UNIQUE INDEX unique_user_anilist (user_id, anilist_id);

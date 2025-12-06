-- Migration: Fix user_games missing columns
-- Description: Add cover_image_cdn and other missing columns to user_games table
-- Date: 2024-12-06

-- Add cover_image_cdn column
ALTER TABLE user_games 
ADD COLUMN cover_image_cdn TEXT NULL AFTER name;

-- Add cover_image_local column
ALTER TABLE user_games 
ADD COLUMN cover_image_local TEXT NULL AFTER cover_image_cdn;

-- Add overview column
ALTER TABLE user_games 
ADD COLUMN overview TEXT NULL AFTER cover_image_local;

-- Add genres column
ALTER TABLE user_games 
ADD COLUMN genres JSON NULL AFTER overview;

-- Add external_rating column
ALTER TABLE user_games 
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres;

-- Add backdrop_image_cdn column
ALTER TABLE user_games 
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating;

-- Add backdrop_image_local column
ALTER TABLE user_games 
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- Migrate existing data from cover_url to cover_image_cdn
UPDATE user_games SET cover_image_cdn = cover_url WHERE cover_url IS NOT NULL AND cover_image_cdn IS NULL;

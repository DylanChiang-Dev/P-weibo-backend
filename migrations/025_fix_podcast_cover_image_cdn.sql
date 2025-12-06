-- Migration: Fix podcast cover_image_cdn column
-- Description: Add missing cover_image_cdn column to user_podcasts table
-- This was missed in 022_add_media_metadata_p0.sql which assumed the column already existed
-- Date: 2024-12-06

-- Add cover_image_cdn column (was never created, only artwork_url exists)
ALTER TABLE user_podcasts 
ADD COLUMN cover_image_cdn TEXT NULL AFTER artwork_url;

-- Migrate existing data from artwork_url to cover_image_cdn
UPDATE user_podcasts SET cover_image_cdn = artwork_url WHERE artwork_url IS NOT NULL AND cover_image_cdn IS NULL;

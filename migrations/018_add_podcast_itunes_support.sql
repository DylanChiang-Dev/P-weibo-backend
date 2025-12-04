-- Migration: Add iTunes support for Podcasts
-- Description: Add itunes_id and artwork_url fields, support frontend format
-- Date: 2024-12-04

-- Add itunes_id for iTunes/Apple Podcasts integration
ALTER TABLE user_podcasts 
ADD COLUMN itunes_id BIGINT NULL AFTER podcast_id,
ADD INDEX idx_itunes_id (itunes_id);

-- Add artwork_url for podcast cover image
ALTER TABLE user_podcasts 
ADD COLUMN artwork_url TEXT NULL AFTER title;

-- Add release_date for compatibility with frontend
ALTER TABLE user_podcasts 
ADD COLUMN release_date DATE NULL AFTER first_release_date;

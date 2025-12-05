-- Migration: Add Media Metadata P1 - Extended Fields
-- Description: Add overview, genres, external_rating, backdrop images
-- Priority: P1 - Supports detail page display
-- Date: 2024-12-05

-- ============================================
-- 1. user_movies
-- ============================================
ALTER TABLE user_movies 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres,
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating,
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- ============================================
-- 2. user_tv_shows
-- ============================================
ALTER TABLE user_tv_shows 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres,
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating,
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- ============================================
-- 3. user_documentaries
-- ============================================
ALTER TABLE user_documentaries 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres,
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating,
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- ============================================
-- 4. user_anime
-- ============================================
ALTER TABLE user_anime 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres,
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating,
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- ============================================
-- 5. user_books
-- ============================================
ALTER TABLE user_books 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres;
-- Books typically don't have backdrop images

-- ============================================
-- 6. user_games
-- ============================================
ALTER TABLE user_games 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres,
ADD COLUMN backdrop_image_cdn TEXT NULL AFTER external_rating,
ADD COLUMN backdrop_image_local TEXT NULL AFTER backdrop_image_cdn;

-- ============================================
-- 7. user_podcasts
-- ============================================
ALTER TABLE user_podcasts 
ADD COLUMN overview TEXT NULL AFTER cover_image_local,
ADD COLUMN genres JSON NULL AFTER overview,
ADD COLUMN external_rating DECIMAL(3,1) NULL AFTER genres;
-- Podcasts typically don't have backdrop images

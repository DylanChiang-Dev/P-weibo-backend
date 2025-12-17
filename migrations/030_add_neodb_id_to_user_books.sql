-- Migration: Add neodb_id to user_books
-- Description: Support NeoDB as an alternative book data source (migration from Google Books)
-- Date: 2024-12-17

-- Add neodb_id column (NeoDB uses UUID format)
ALTER TABLE user_books 
ADD COLUMN neodb_id VARCHAR(100) NULL AFTER google_books_id;

-- Add index for efficient lookups
CREATE INDEX idx_neodb_id ON user_books(neodb_id);

-- Migration: Add neodb_id to user_books
-- Description: Support NeoDB as an alternative book data source (migration from Google Books)
-- Date: 2024-12-17

ALTER TABLE user_books ADD COLUMN neodb_id VARCHAR(100) NULL AFTER google_books_id;

CREATE INDEX idx_neodb_id ON user_books(neodb_id);

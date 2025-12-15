-- Add revoked_at to refresh_tokens for better refresh-token rotation safety
-- This migration is idempotent (runner replays all .sql files each time).

ALTER TABLE refresh_tokens
  ADD COLUMN IF NOT EXISTS revoked_at TIMESTAMP NULL DEFAULT NULL;


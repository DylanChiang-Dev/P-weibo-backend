-- Migration: Add cumulative_xp to daily_activities
-- Description: Add cumulative_xp field for Duolingo total XP tracking
-- Date: 2024-12-29

ALTER TABLE daily_activities ADD COLUMN cumulative_xp INT DEFAULT NULL;

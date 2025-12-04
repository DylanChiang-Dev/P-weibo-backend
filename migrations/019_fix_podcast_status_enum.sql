-- Migration: Fix podcast status enum
-- Description: Add 'listened' to status values for frontend compatibility
-- Date: 2024-12-04

ALTER TABLE user_podcasts 
MODIFY COLUMN status ENUM('listening', 'listened', 'completed', 'dropped', 'plan_to_listen') DEFAULT 'listening';

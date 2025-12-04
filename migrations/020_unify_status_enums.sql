-- Migration: Unify all media status enums
-- Description: Ensure all media tables support consistent status values
-- Date: 2024-12-04

-- =============================================
-- 1. 播客 (podcasts): listened, listening, want_to_listen, completed, dropped
-- =============================================
ALTER TABLE user_podcasts 
MODIFY COLUMN status ENUM(
    'listened', 'listening', 'want_to_listen',
    'completed', 'dropped', 'plan_to_listen'
) DEFAULT 'listening';

-- =============================================
-- 2. 游戏 (games): played, playing, want_to_play, completed, dropped
-- =============================================
ALTER TABLE user_games 
MODIFY COLUMN status ENUM(
    'played', 'playing', 'want_to_play',
    'completed', 'dropped'
) DEFAULT 'played';

-- =============================================
-- 3. 书籍 (books): read, reading, want_to_read, completed, dropped
-- =============================================
ALTER TABLE user_books 
MODIFY COLUMN status ENUM(
    'read', 'reading', 'want_to_read',
    'completed', 'dropped'
) DEFAULT 'read';

-- =============================================
-- 4. 电影 (movies): watched, watching, want_to_watch, completed, dropped
-- =============================================
ALTER TABLE user_movies 
MODIFY COLUMN status ENUM(
    'watched', 'watching', 'want_to_watch',
    'completed', 'dropped'
) DEFAULT 'watched';

-- =============================================
-- 5. 电视剧 (tv_shows): watched, watching, want_to_watch, on_hold, dropped
-- =============================================
ALTER TABLE user_tv_shows 
MODIFY COLUMN status ENUM(
    'watched', 'watching', 'want_to_watch',
    'on_hold', 'completed', 'dropped'
) DEFAULT 'watching';

-- =============================================
-- 6. 纪录片 (documentaries): watched, watching, want_to_watch, dropped
-- =============================================
ALTER TABLE user_documentaries 
MODIFY COLUMN status ENUM(
    'watched', 'watching', 'want_to_watch',
    'completed', 'dropped'
) DEFAULT 'watched';

-- =============================================
-- 7. 动画 (anime): watched, watching, want_to_watch, on_hold, dropped
-- =============================================
ALTER TABLE user_anime 
MODIFY COLUMN status ENUM(
    'watched', 'watching', 'want_to_watch',
    'on_hold', 'completed', 'dropped', 'plan_to_watch'
) DEFAULT 'watching';

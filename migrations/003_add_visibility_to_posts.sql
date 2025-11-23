-- 為 posts 表添加 visibility 字段
ALTER TABLE posts ADD COLUMN visibility ENUM('public', 'private') NOT NULL DEFAULT 'public' AFTER content;

-- 添加索引以提升查詢性能
CREATE INDEX idx_posts_visibility ON posts(visibility);
CREATE INDEX idx_posts_user_visibility ON posts(user_id, visibility);

-- 為現有貼文設置默認值（確保向後兼容）
UPDATE posts SET visibility = 'public' WHERE visibility IS NULL;

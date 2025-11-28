-- =====================================================
-- 媒体管理表迁移
-- 用于统一管理用户上传的图片等媒体资源
-- =====================================================

CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL COMMENT '服务器上的实际文件路径，用于删除',
    size INT COMMENT '文件大小（字节）',
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 为现有的 post_images 表添加索引以便迁移数据
-- （如果需要从现有上传记录迁移到 media 表）

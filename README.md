# P-Weibo Backend API

個人朋友圈後端 API - 基於 PHP 8.2 + MySQL + Nginx 的單用戶微博系統

## 📋 項目概述

這是一個功能完整的個人朋友圈後端 API，採用單用戶模式設計，支持管理員發布內容和遊客互動。

### 核心特性

- ✅ **JWT 認證系統** - Access Token + Refresh Token (HttpOnly Cookie)
- ✅ **單用戶管理員模式** - 嚴格的權限控制，僅管理員可發帖
- ✅ **統一響應格式** - 標準化的 JSON API 回應結構
- ✅ **貼文管理** - 創建、刪除、置頂貼文 (Admin Only)
- ✅ **混合媒體** - 支持圖片（10MB）和視頻（100MB）上傳
- ✅ **互動功能** - 點贊、評論（支持遊客評論）
- ✅ **用戶資料** - 頭像、暱稱自定義
- ✅ **Token 刷新** - 自動輪轉 Refresh Token

### 技術特性

- 📦 **無依賴** - 純 PHP 實現，無需 Composer
- 🔒 **安全性** - 密碼哈希、JWT、SQL 注入防護、CORS 支持
- 🛡️ **權限系統** - 基於 Middleware 的角色權限控制 (AdminMiddleware)
- 🚀 **性能優化** - QueryBuilder、OpCache、Nginx 靜態文件服務
- 📝 **完整日誌** - 錯誤追蹤和審計日誌

## 🚀 快速開始

### 開發環境（Docker）

```bash
# 1. 克隆項目
git clone <repository-url>
cd p-weibo-backend

# 2. 配置環境變量
cp .env.example .env
# 編輯 .env，設置數據庫和 JWT 密鑰

# 3. 啟動服務
docker-compose up -d

# 4. 運行數據庫遷移
docker-compose exec php php scripts/migrate.php
docker-compose exec php php scripts/migration_guest_comments.php
docker-compose exec php php scripts/migration_post_videos.php
docker-compose exec php php scripts/migration_pin_posts.php

# 5. 初始化管理員帳號
docker-compose exec php php scripts/init_admin.php

# 6. 訪問 API
curl http://localhost:8080/api/posts
```

### 生產環境（寶塔面板）

詳細部署指南請參考：[DEPLOYMENT.md](./DEPLOYMENT.md)

**快速步驟**：
1. 安裝 PHP 8.2 + MySQL + Nginx
2. 創建網站並配置運行目錄為 `/public`
3. 配置 `.env` 文件
4. 運行數據庫遷移
5. 配置 SSL 證書
6. 完成！

## 📁 目錄結構

```
p-weibo-backend/
├── app/
│   ├── Controllers/      # 控制器層
│   ├── Models/          # 數據模型層
│   ├── Services/        # 業務邏輯層
│   └── Core/            # 核心組件
├── config/              # 配置文件
├── public/              # Web 根目錄
│   └── index.php        # 入口文件
├── scripts/             # 遷移和工具腳本
├── storage/             # 文件存儲
│   └── uploads/         # 上傳文件
├── logs/                # 日誌文件
├── migrations/          # 數據庫結構
├── docker/              # Docker 配置
├── .env.example         # 環境變量示例
├── DEPLOYMENT.md        # 部署指南
└── PRODUCTION_CHECKLIST.md  # 生產環境檢查清單
```

## 🔧 環境配置

### 必需的環境變量

```env
# 應用配置
APP_ENV=production
APP_URL=https://api.yourdomain.com
FRONTEND_ORIGIN=https://yourdomain.com

# 數據庫
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_user
DB_PASS=your_password

# JWT 密鑰（使用強隨機字符串）
JWT_ACCESS_SECRET=your-64-char-random-string
JWT_REFRESH_SECRET=your-64-char-random-string

# 管理員帳號
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=your-strong-password
ADMIN_DISPLAY_NAME=Admin

# 文件上傳
MAX_IMAGE_MB=10
MAX_VIDEO_MB=100
```

**生成強隨機密鑰**：
```bash
openssl rand -base64 64
```

## 📚 API 文檔

完整 API 文檔請參考：[api_documentation.md](./api_documentation.md)

### 主要端點

| 端點 | 方法 | 認證 | 說明 |
|------|------|------|------|
| `/api/login` | POST | ❌ | 管理員登錄 |
| `/api/token/refresh` | POST | Cookie | 刷新 Token |
| `/api/logout` | POST | ✅ | 登出 |
| `/api/posts` | GET | ❌ | 獲取貼文列表 |
| `/api/posts` | POST | ✅ | 創建貼文（支持圖片/視頻） |
| `/api/posts/{id}` | GET | ❌ | 獲取單個貼文 |
| `/api/posts/{id}` | DELETE | ✅ | 刪除貼文 |
| `/api/posts/{id}/pin` | POST | ✅ | 置頂貼文 |
| `/api/posts/{id}/unpin` | POST | ✅ | 取消置頂 |
| `/api/posts/{id}/like` | POST | ✅ | 點贊貼文 |
| `/api/posts/{id}/comments` | GET | ❌ | 獲取評論 |
| `/api/posts/{id}/comments` | POST | ❌ | 發表評論（支持遊客） |
| `/api/users/me` | POST | ✅ | 更新個人資料 |
| `/api/users/{email}` | GET | ❌ | 獲取用戶資料 |

### API 示例

**登錄**：
```bash
curl -X POST https://api.yourdomain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your-password"}'
```

**創建貼文（帶圖片）**：
```bash
curl -X POST https://api.yourdomain.com/api/posts \
  -H "Authorization: Bearer <access_token>" \
  -F "content=Hello World" \
  -F "images[]=@photo.jpg"
```

**遊客評論**：
```bash
curl -X POST https://api.yourdomain.com/api/posts/1/comments \
  -H "Content-Type: application/json" \
  -d '{"content":"Nice post!","authorName":"Guest"}'
```

## 🗄️ 數據庫遷移

### 遷移順序

1. **基礎結構**: `migrations/schema.sql`
2. **遊客評論**: `scripts/migration_guest_comments.php`
3. **視頻支持**: `scripts/migration_post_videos.php`
4. **置頂功能**: `scripts/migration_pin_posts.php`
5. **管理員初始化**: `scripts/init_admin.php`

### 執行遷移

```bash
# Docker 環境
docker-compose exec php php scripts/migration_guest_comments.php
docker-compose exec php php scripts/migration_post_videos.php
docker-compose exec php php scripts/migration_pin_posts.php
docker-compose exec php php scripts/init_admin.php

# 生產環境
php scripts/migration_guest_comments.php
php scripts/migration_post_videos.php
php scripts/migration_pin_posts.php
php scripts/init_admin.php
```

## 🔒 安全性

### 已實施的安全措施

- ✅ PDO Prepared Statements（防 SQL 注入）
- ✅ 密碼哈希（`password_hash` / `password_verify`）
- ✅ JWT 簽名驗證
- ✅ Refresh Token 輪轉和重用檢測
- ✅ 文件類型驗證（圖片/視頻）
- ✅ CORS 配置
- ✅ HttpOnly Cookie（Refresh Token）
- ✅ 速率限制（登錄端點）

### 生產環境建議

- 使用 HTTPS（必須）
- 使用強隨機 JWT 密鑰（64+ 字符）
- 使用強管理員密碼（12+ 字符）
- 定期備份數據庫和上傳文件
- 監控磁盤空間（視頻文件較大）
- 啟用 OpCache

## 🧪 測試

### 開發測試

```bash
# 測試 QueryBuilder
docker-compose exec php php tests/test_query_builder.php

# 測試 Services
docker-compose exec php php tests/test_services.php
```

### API 測試

使用提供的測試頁面：`http://localhost:8080/api_test.html`

或使用 cURL / Postman 測試各個端點。

## 📦 部署

### 開發環境

```bash
docker-compose up -d
```

### 生產環境

詳細步驟請參考：
- [DEPLOYMENT.md](./DEPLOYMENT.md) - 完整部署指南
- [PRODUCTION_CHECKLIST.md](./PRODUCTION_CHECKLIST.md) - 生產環境檢查清單

## 🛠️ 技術棧

- **後端**: PHP 8.2
- **數據庫**: MySQL 5.7+ / MySQL 8.0
- **Web 服務器**: Nginx
- **容器化**: Docker + Docker Compose（開發環境）
- **認證**: JWT (HS256)
- **圖片處理**: GD Extension
- **視頻處理**: FFmpeg（可選，用於縮圖生成）

## 📝 開發日誌

### 已完成功能

- [x] JWT 認證系統（Access + Refresh Token）
- [x] 單用戶模式轉換
- [x] 貼文 CRUD
- [x] 圖片上傳（多圖支持）
- [x] 視頻上傳（100MB 限制）
- [x] 點贊功能
- [x] 評論功能（支持遊客）
- [x] 置頂貼文
- [x] 用戶資料更新（頭像、暱稱）
- [x] Token 刷新機制
- [x] QueryBuilder 抽象層
- [x] 服務層架構
- [x] 完整 API 文檔
- [x] 部署指南

## 🤝 貢獻

歡迎提交 Issue 和 Pull Request！

## 📄 授權

MIT License

## 📞 支持

如有問題，請查看：
- [部署指南](./DEPLOYMENT.md)
- [API 文檔](./api_documentation.md)
- [生產環境檢查清單](./PRODUCTION_CHECKLIST.md)

---

**最後更新**: 2025-11-20  
**版本**: 1.0.0
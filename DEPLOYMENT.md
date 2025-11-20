# P-Weibo Backend - 部署指南

個人朋友圈後端 API，基於 PHP 8.2 + MySQL + Nginx 構建的單用戶微博系統。

## 📋 目錄

- [系統要求](#系統要求)
- [功能特性](#功能特性)
- [寶塔面板部署](#寶塔面板部署)
- [環境配置](#環境配置)
- [數據庫遷移](#數據庫遷移)
- [生產環境檢查清單](#生產環境檢查清單)
- [API 文檔](#api-文檔)

## 系統要求

- **PHP**: 8.2 或更高版本
- **MySQL**: 5.7 或更高版本
- **Nginx**: 1.18 或更高版本
- **PHP 擴展**:
  - `pdo_mysql`
  - `gd` (圖片處理)
  - `opcache` (性能優化)
  - `fileinfo` (文件類型檢測)

## 功能特性

### 核心功能
- ✅ **JWT 認證系統** - Access Token + Refresh Token (HttpOnly Cookie)
- ✅ **單用戶模式** - 管理員帳號自動初始化
- ✅ **貼文管理** - 創建、刪除、置頂貼文
- ✅ **混合媒體** - 支持圖片和視頻上傳
- ✅ **互動功能** - 點贊、評論（支持遊客評論）
- ✅ **用戶資料** - 頭像、暱稱自定義

### 技術特性
- 📦 **無依賴** - 純 PHP 實現，無需 Composer
- 🔒 **安全性** - 密碼哈希、JWT、SQL 注入防護
- 🚀 **性能優化** - QueryBuilder、OpCache、Nginx 靜態文件服務
- 📝 **完整日誌** - 錯誤追蹤和審計日誌

## 寶塔面板部署

### 1. 環境準備

#### 1.1 安裝軟件
在寶塔面板中安裝以下軟件：
- **PHP 8.2** (極速安裝)
- **MySQL 5.7+** 或 **MySQL 8.0**
- **Nginx 1.18+**

#### 1.2 PHP 配置
進入 **軟件商店 → PHP 8.2 → 設置**：

**安裝擴展**：
- `opcache` ✅
- `gd` ✅
- `fileinfo` ✅

**修改 php.ini**：
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
```

### 2. 創建網站

#### 2.1 添加站點
1. 進入 **網站 → 添加站點**
2. 填寫域名（例如：`api.yourdomain.com`）
3. 選擇 **PHP 版本**: `PHP-82`
4. 創建數據庫（記住數據庫名、用戶名、密碼）

#### 2.2 上傳代碼
1. 刪除網站根目錄下的默認文件
2. 上傳項目代碼到網站根目錄
3. 確保目錄結構如下：
```
/www/wwwroot/api.yourdomain.com/
├── app/
├── config/
├── public/          # 網站運行目錄
├── scripts/
├── storage/
├── .env.example
└── README.md
```

#### 2.3 設置運行目錄
1. 進入 **網站設置 → 網站目錄**
2. 將 **運行目錄** 設置為 `/public`
3. 取消勾選 **防跨站攻擊**（重要！）

#### 2.4 配置 Nginx
進入 **網站設置 → 配置文件**，添加以下配置：

```nginx
# 在 server 塊中添加
client_max_body_size 100M;

# 靜態文件服務（在 location / 之前添加）
location /uploads/ {
    alias /www/wwwroot/api.yourdomain.com/storage/uploads/;
    try_files $uri $uri/ =404;
    access_log off;
    expires max;
}

# 禁止解析 uploads 目錄中的 PHP
location ^~ /storage/uploads/ {
    types { }
    default_type application/octet-stream;
    autoindex off;
}

# 修改現有的 location / 塊
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# 確保 PHP 處理塊存在
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_read_timeout 300;
}
```

### 3. 配置環境變量

#### 3.1 創建 .env 文件
```bash
cd /www/wwwroot/api.yourdomain.com
cp .env.example .env
```

#### 3.2 編輯 .env
```bash
nano .env  # 或使用寶塔文件管理器編輯
```

**必須修改的配置**：
```env
# 生產環境
APP_ENV=production
APP_URL=https://api.yourdomain.com
FRONTEND_ORIGIN=https://yourdomain.com

# 數據庫（使用寶塔創建的數據庫信息）
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

# JWT 密鑰（生成強隨機字符串）
JWT_ACCESS_SECRET=your-very-long-random-access-secret-key-here
JWT_REFRESH_SECRET=your-very-long-random-refresh-secret-key-here

# 管理員帳號（首次部署）
ADMIN_EMAIL=your-email@example.com
ADMIN_PASSWORD=your-strong-password-here
ADMIN_DISPLAY_NAME=Your Name

# 上傳路徑（寶塔環境）
UPLOAD_PATH=/www/wwwroot/api.yourdomain.com/storage/uploads
LOG_PATH=/www/wwwroot/api.yourdomain.com/logs
```

**生成強隨機密鑰**：
```bash
# 在終端執行
openssl rand -base64 64
```

### 4. 設置文件權限

```bash
cd /www/wwwroot/api.yourdomain.com

# 創建必要目錄
mkdir -p storage/uploads logs

# 設置權限（www 是寶塔的 PHP 運行用戶）
chown -R www:www storage logs
chmod -R 755 storage logs
```

### 5. 數據庫初始化

#### 5.1 導入數據庫結構
在寶塔面板 **數據庫 → phpMyAdmin** 中：
1. 選擇您的數據庫
2. 導入 `migrations/schema.sql`

或使用命令行：
```bash
mysql -u your_user -p your_database < migrations/schema.sql
```

#### 5.2 運行數據庫遷移
```bash
cd /www/wwwroot/api.yourdomain.com

# 按順序執行所有遷移
php scripts/migration_guest_comments.php
php scripts/migration_post_videos.php
php scripts/migration_pin_posts.php
```

#### 5.3 初始化管理員帳號
```bash
php scripts/init_admin.php
```

### 6. SSL 證書配置

#### 6.1 申請 SSL 證書
1. 進入 **網站設置 → SSL**
2. 選擇 **Let's Encrypt** 免費證書
3. 勾選域名，點擊申請

#### 6.2 強制 HTTPS
1. 開啟 **強制 HTTPS**
2. 確保 `.env` 中的 `APP_URL` 使用 `https://`

### 7. 配置 CORS（跨域）

如果前端和後端域名不同，需要配置 CORS。

在 Nginx 配置中添加（已在代碼中處理，無需額外配置）：
```nginx
# 代碼已自動處理 CORS，確保 .env 中 FRONTEND_ORIGIN 正確即可
```

### 8. 驗證部署

#### 8.1 健康檢查
訪問：`https://api.yourdomain.com/api/posts`

應該返回：
```json
{
  "success": true,
  "data": {
    "items": [],
    "next_cursor": null
  }
}
```

#### 8.2 管理員登錄測試
```bash
curl -X POST https://api.yourdomain.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your-email@example.com","password":"your-password"}'
```

應該返回 access_token。

## 環境配置

### 開發環境 vs 生產環境

| 配置項 | 開發環境 | 生產環境 |
|--------|---------|---------|
| `APP_ENV` | `development` | `production` |
| `APP_URL` | `http://localhost:8080` | `https://api.yourdomain.com` |
| `DB_HOST` | `mysql` (Docker) | `localhost` |
| `JWT_*_SECRET` | 簡單字符串 | 64+ 字符強隨機字符串 |
| HTTPS | 可選 | **必須** |

## 數據庫遷移

### 遷移文件列表

按順序執行以下遷移：

1. **基礎結構**: `migrations/schema.sql` - 創建所有表
2. **遊客評論**: `scripts/migration_guest_comments.php` - 支持遊客評論
3. **視頻上傳**: `scripts/migration_post_videos.php` - 添加視頻支持
4. **置頂貼文**: `scripts/migration_pin_posts.php` - 添加置頂功能

### 執行遷移

```bash
# 1. 導入基礎結構
mysql -u user -p database < migrations/schema.sql

# 2. 運行 PHP 遷移腳本
php scripts/migration_guest_comments.php
php scripts/migration_post_videos.php
php scripts/migration_pin_posts.php

# 3. 初始化管理員
php scripts/init_admin.php
```

## 生產環境檢查清單

### 安全性 ✅

- [ ] 修改 `.env` 中的所有密鑰和密碼
- [ ] JWT 密鑰使用 64+ 字符強隨機字符串
- [ ] 管理員密碼足夠強（12+ 字符，包含大小寫、數字、符號）
- [ ] 啟用 HTTPS（強制）
- [ ] `.env` 文件權限設置為 600
- [ ] 禁用 PHP 錯誤顯示（生產環境）

### 性能優化 ✅

- [ ] 啟用 OpCache
- [ ] Nginx 靜態文件緩存配置
- [ ] 數據庫索引已創建（遷移腳本自動處理）
- [ ] 文件上傳限制已設置（100MB）

### 功能驗證 ✅

- [ ] 管理員登錄成功
- [ ] 創建貼文（文字、圖片、視頻）
- [ ] 遊客評論功能正常
- [ ] Token 刷新功能正常
- [ ] 置頂貼文功能正常
- [ ] 靜態文件（圖片/視頻）可訪問

### 監控和維護 ✅

- [ ] 設置日誌輪轉（寶塔自動處理）
- [ ] 監控磁盤空間（視頻文件較大）
- [ ] 定期備份數據庫
- [ ] 定期備份 `storage/uploads` 目錄

## API 文檔

完整 API 文檔請參考：[API Documentation](./api_documentation.md)

### 主要端點

| 端點 | 方法 | 認證 | 說明 |
|------|------|------|------|
| `/api/login` | POST | ❌ | 管理員登錄 |
| `/api/token/refresh` | POST | Cookie | 刷新 Token |
| `/api/posts` | GET | ❌ | 獲取貼文列表 |
| `/api/posts` | POST | ✅ | 創建貼文 |
| `/api/posts/{id}/pin` | POST | ✅ | 置頂貼文 |
| `/api/posts/{id}/comments` | POST | ❌ | 發表評論（支持遊客） |
| `/api/users/me` | POST | ✅ | 更新個人資料 |

## 故障排除

### 常見問題

#### 1. 500 Internal Server Error
- 檢查 PHP 錯誤日誌：`/www/wwwroot/api.yourdomain.com/logs/app.log`
- 檢查 Nginx 錯誤日誌：寶塔面板 → 網站 → 日誌
- 確認文件權限正確

#### 2. 圖片/視頻無法訪問
- 檢查 Nginx 配置中的 `/uploads/` location 塊
- 確認 `storage/uploads` 目錄權限為 755
- 確認文件確實存在

#### 3. CORS 錯誤
- 檢查 `.env` 中的 `FRONTEND_ORIGIN` 是否正確
- 確認前端域名與配置一致（包括協議和端口）

#### 4. Token 刷新失敗
- 檢查瀏覽器是否支持 HttpOnly Cookie
- 確認 Cookie 的 `SameSite` 設置（生產環境為 `None`，需要 HTTPS）

## 技術支持

- **項目倉庫**: [GitHub](https://github.com/yourusername/p-weibo-backend)
- **問題反饋**: [Issues](https://github.com/yourusername/p-weibo-backend/issues)

## 授權

MIT License

---

**最後更新**: 2025-11-20

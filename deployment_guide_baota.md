# 寶塔面板 (Baota/aaPanel) 部署指南

本指南將協助您將 P-Weibo Backend 部署到使用寶塔面板的 Linux 服務器上。

## 1. 環境準備 (軟件商店)

請在寶塔面板的「軟件商店」中安裝以下環境：

- **Nginx**: 1.20 或更高版本
- **MySQL**: 8.0 (推薦) 或 5.7
- **PHP**: 8.2 (必須安裝)
- **phpMyAdmin**: 用於管理數據庫 (可選)

### PHP 擴展安裝
在 PHP 8.2 的設置中，確保安裝了以下擴展 (通常默認已安裝)：
- `fileinfo`
- `pdo_mysql`
- `gd` (用於圖片處理)
- `openssl`
- `mbstring`

## 2. 創建網站

1. 進入「網站」菜單，點擊「添加站點」。
2. **域名**: 填寫您的域名或服務器 IP。
3. **數據庫**: 選擇「MySQL」，設置用戶名和密碼 (記下這些信息)。
4. **PHP 版本**: 選擇 `PHP-82`。
5. 提交創建。

## 3. 代碼上傳

1. 進入網站根目錄 (通常是 `/www/wwwroot/您的域名/`)。
2. 刪除默認生成的 `index.html` 和 `404.html`。
3. 將本地項目文件打包上傳並解壓，或使用 Git 拉取代碼。
   - **注意**: 確保 `public`, `app`, `config`, `vendor` 等目錄在網站根目錄下。
   - 如果您沒有上傳 `vendor` 目錄，需要通過 SSH 進入目錄執行 `composer install --no-dev --optimize-autoloader`。

## 4. 網站目錄配置

1. 點擊網站列表中的「設置」。
2. 進入「網站目錄」標籤頁。
3. **運行目錄**: 選擇 `/public`。
4. 點擊「保存」。
   - *這一步非常重要，確保只有 public 目錄對外公開，保護源代碼安全。*

## 5. 偽靜態配置 (Nginx Rewrite)

為了讓 API 路由正常工作，需要配置 Nginx 重寫規則。

1. 在網站設置中，進入「偽靜態」標籤頁。
2. 選擇 `0` (空白) 或直接輸入以下規則：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

3. 點擊「保存」。

## 6. 數據庫配置

1. 進入「數據庫」菜單，點擊您創建的數據庫的「管理」按鈕 (phpMyAdmin)。
2. 導入項目中的 SQL 文件，順序如下：
   1. `migrations/001_schema.sql`
   2. `migrations/002_add_role_to_users.sql`
   3. `migrations/003_add_visibility_to_posts.sql`

## 7. 環境變量配置

1. 在網站根目錄下，將 `.env.example` 複製為 `.env`。
2. 編輯 `.env` 文件，填入您的數據庫信息：

```ini
DB_HOST=127.0.0.1
DB_NAME=您的數據庫名
DB_USER=您的數據庫用戶名
DB_PASS=您的數據庫密碼

# 修改為您的生產環境域名 (前端訪問地址)
FRONTEND_ORIGIN=http://您的前端域名
```

## 8. 權限設置

確保以下目錄具有寫入權限 (通常是 `755` 或 `777`，所有者為 `www`)：

- `/public/uploads` (用於存儲上傳的圖片和影片)

## 9. 創建管理員帳號

您可以通過 SSH 運行腳本來創建管理員：

```bash
cd /www/wwwroot/您的域名/
php scripts/create_admin.php
```

或者直接在數據庫 `users` 表中插入數據，並將 `role` 設為 `admin`。

## 常見問題

- **404 Not Found**: 檢查「偽靜態」是否配置正確，以及「運行目錄」是否設為 `/public`。
- **500 Internal Server Error**: 
  - 檢查 `.env` 文件是否存在且配置正確。
  - 檢查 `public/uploads` 是否有寫入權限。
  - 查看網站日誌 (網站設置 -> 網站日誌 -> 錯誤日誌) 獲取詳細報錯。
- **上傳失敗**: 檢查 PHP 設置中的 `upload_max_filesize` 和 `post_max_size` 是否足夠大 (建議設為 100M+)。

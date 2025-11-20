# 生產環境部署檢查清單

## 🔒 安全性檢查

### 環境配置
- [ ] `.env` 文件已創建並配置完成
- [ ] `.env` 文件權限設置為 `600` (僅所有者可讀寫)
- [ ] `.env` 文件已添加到 `.gitignore`（不提交到版本控制）
- [ ] `APP_ENV` 設置為 `production`

### 密鑰和密碼
- [ ] `JWT_ACCESS_SECRET` 使用 64+ 字符強隨機字符串
- [ ] `JWT_REFRESH_SECRET` 使用 64+ 字符強隨機字符串（與 ACCESS 不同）
- [ ] `ADMIN_PASSWORD` 使用強密碼（12+ 字符，包含大小寫、數字、符號）
- [ ] 數據庫密碼足夠強
- [ ] 所有默認密碼已修改

**生成強隨機密鑰命令**：
```bash
openssl rand -base64 64
```

### HTTPS 和域名
- [ ] SSL 證書已安裝並有效
- [ ] 強制 HTTPS 已啟用
- [ ] `APP_URL` 使用 `https://` 協議
- [ ] `FRONTEND_ORIGIN` 配置正確（用於 CORS）
- [ ] Cookie `SameSite=None` 配置（需要 HTTPS）

### PHP 配置
- [ ] PHP 錯誤顯示已禁用（`display_errors = Off`）
- [ ] 錯誤日誌已啟用（`log_errors = On`）
- [ ] 文件上傳限制已設置（`upload_max_filesize = 100M`）
- [ ] 執行時間限制已設置（`max_execution_time = 300`）
- [ ] 內存限制已設置（`memory_limit = 256M`）

### 文件權限
- [ ] `storage/` 目錄權限為 `755`，所有者為 `www`
- [ ] `logs/` 目錄權限為 `755`，所有者為 `www`
- [ ] `storage/uploads/` 目錄權限為 `755`
- [ ] PHP 文件權限為 `644`
- [ ] 目錄權限為 `755`

### Nginx 配置
- [ ] `client_max_body_size` 設置為 `100M`
- [ ] `/uploads/` 靜態文件服務已配置
- [ ] 禁止執行 `/storage/uploads/` 中的 PHP 文件
- [ ] 運行目錄設置為 `/public`
- [ ] 防跨站攻擊已禁用（允許 API 訪問）

## 🗄️ 數據庫檢查

### 初始化
- [ ] 數據庫已創建
- [ ] 數據庫用戶已創建並授權
- [ ] `migrations/schema.sql` 已導入
- [ ] 所有遷移腳本已執行：
  - [ ] `migration_guest_comments.php`
  - [ ] `migration_post_videos.php`
  - [ ] `migration_pin_posts.php`
- [ ] 管理員帳號已初始化（`init_admin.php`）

### 備份
- [ ] 數據庫自動備份已設置（寶塔面板）
- [ ] 備份頻率：每日
- [ ] 備份保留時間：至少 7 天

## 📁 文件和存儲

### 目錄結構
- [ ] `storage/uploads/` 目錄已創建
- [ ] `storage/uploads/avatars/` 目錄已創建
- [ ] `logs/` 目錄已創建
- [ ] 所有必要目錄的所有者為 `www:www`

### 備份
- [ ] `storage/uploads/` 定期備份已設置
- [ ] 備份存儲位置與服務器分離（異地備份）

## ⚡ 性能優化

### PHP
- [ ] OpCache 已啟用
- [ ] OpCache 配置已優化：
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=10000
  opcache.revalidate_freq=60
  ```

### Nginx
- [ ] Gzip 壓縮已啟用
- [ ] 靜態文件緩存已配置（`expires max`）
- [ ] 訪問日誌對靜態文件已禁用（`access_log off`）

### 數據庫
- [ ] 索引已創建（遷移腳本自動處理）
- [ ] 查詢性能已測試

## 🧪 功能驗證

### API 端點測試
- [ ] `GET /api/posts` - 獲取貼文列表
- [ ] `POST /api/login` - 管理員登錄
- [ ] `POST /api/token/refresh` - Token 刷新
- [ ] `POST /api/posts` - 創建貼文（需認證）
- [ ] `POST /api/posts/{id}/comments` - 發表評論（遊客）
- [ ] `POST /api/posts/{id}/pin` - 置頂貼文（需認證）
- [ ] `POST /api/users/me` - 更新個人資料（需認證）

### 媒體上傳測試
- [ ] 圖片上傳成功（≤10MB）
- [ ] 視頻上傳成功（≤100MB）
- [ ] 頭像上傳成功
- [ ] 上傳的文件可通過 URL 訪問

### 認證和授權
- [ ] JWT Token 正常簽發
- [ ] Refresh Token 正常輪轉
- [ ] Cookie 正確設置（HttpOnly, Secure, SameSite）
- [ ] 未認證用戶無法訪問受保護端點
- [ ] 遊客可以訪問公開端點

### CORS 測試
- [ ] 前端可以成功調用 API
- [ ] OPTIONS 預檢請求正常
- [ ] Cookie 可以跨域傳遞

## 📊 監控和日誌

### 日誌配置
- [ ] 應用日誌路徑正確（`LOG_PATH`）
- [ ] 日誌輪轉已配置（防止日誌文件過大）
- [ ] 錯誤日誌可訪問並可讀

### 監控指標
- [ ] 磁盤空間監控（視頻文件會佔用大量空間）
- [ ] 數據庫連接數監控
- [ ] PHP-FPM 進程監控
- [ ] Nginx 訪問日誌分析

## 🔄 維護計劃

### 定期任務
- [ ] 每日數據庫備份
- [ ] 每週檢查磁盤空間
- [ ] 每月檢查日誌文件大小
- [ ] 每月檢查 SSL 證書有效期

### 更新策略
- [ ] PHP 安全更新計劃
- [ ] MySQL 更新計劃
- [ ] Nginx 更新計劃
- [ ] 代碼更新和部署流程

## 📝 文檔

### 必備文檔
- [ ] `DEPLOYMENT.md` - 部署指南
- [ ] `README.md` - 項目說明
- [ ] `api_documentation.md` - API 文檔
- [ ] `.env.example` - 環境變量示例

### 運維文檔
- [ ] 故障排除指南
- [ ] 備份恢復流程
- [ ] 緊急聯繫人信息

## ✅ 最終檢查

### 上線前
- [ ] 所有上述檢查項已完成
- [ ] 在測試環境中完整測試
- [ ] 備份當前生產環境（如果是更新）
- [ ] 準備回滾計劃

### 上線後
- [ ] 監控錯誤日誌（前 24 小時）
- [ ] 監控性能指標
- [ ] 測試關鍵功能
- [ ] 通知相關人員上線完成

---

**檢查日期**: _______________  
**檢查人員**: _______________  
**部署環境**: _______________  
**備註**: _______________

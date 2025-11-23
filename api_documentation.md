# P-Weibo Backend API 文檔

## 1. 基礎信息

- **Base URL**: `http://localhost:8080` (開發環境) / `https://api.yourdomain.com` (生產環境)
- **Content-Type**: `application/json` (除非特別說明，如文件上傳)
- **認證方式**: Bearer Token (JWT)

## 2. 統一響應格式

所有 API 回應均遵循以下統一格式：

### 成功響應 (2xx)
```json
{
  "success": true,
  "data": {
    // 具體數據
  }
}
```

### 錯誤響應 (4xx, 5xx)
```json
{
  "success": false,
  "error": "錯誤描述信息",
  "code": 400, // 可選的錯誤代碼
  "details": { ... } // 可選的詳細錯誤信息（如驗證錯誤）
}
```

## 3. 認證與權限

系統採用 **單用戶管理員模式**：
- **Admin (管理員)**: 擁有所有權限（發帖、刪除、置頂）。
- **User (普通用戶/遊客)**: 僅限瀏覽、點贊、評論。

### 權限標識
- 🔓 **Public**: 無需認證
- 🔐 **Auth**: 需要登錄 (User 或 Admin)
- 🛡️ **Admin**: 僅限管理員

---

## 4. API 端點詳解

### 4.1 認證 (Auth)

#### 管理員登錄 🔓
- **URL**: `/api/login`
- **Method**: `POST`
- **Body**:
  ```json
  {
    "email": "admin@example.com",
    "password": "password"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "user": { "id": 1, "username": "admin", "email": "...", "role": "admin" },
      "access_token": "eyJ...",
      "expires_in": 3600
    }
  }
  ```
- **Note**: Refresh Token 會自動寫入 HttpOnly Cookie。

#### 刷新 Token 🔓
- **URL**: `/api/token/refresh`
- **Method**: `POST`
- **Cookie**: 需要包含 `refresh_token`
- **Response**: 返回新的 Access Token。

#### 獲取當前用戶信息 🔐
- **URL**: `/api/me`
- **Method**: `GET`
- **Response**: 返回當前登錄用戶的詳細資料。

#### 登出 🔐
- **URL**: `/api/logout`
- **Method**: `POST`
- **Note**: 會清除 Refresh Token Cookie。

---

### 4.2 貼文 (Posts)

#### 獲取貼文列表 🔓
- **URL**: `/api/posts`
- **Method**: `GET`
- **Query Params**:
  - `limit`: 每頁數量 (默認 20)
  - `cursor`: 分頁游標 (上一頁返回的 `next_cursor`)
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "items": [ ... ],
      "next_cursor": "2023-11-23 10:00:00",
      "has_more": true
    }
  }
  ```

#### 獲取單個貼文 🔓
- **URL**: `/api/posts/{id}`
- **Method**: `GET`

#### 創建貼文 🛡️ (Admin Only)
- **URL**: `/api/posts`
- **Method**: `POST`
- **Content-Type**: `multipart/form-data`
- **Body**:
  - `content`: 文本內容 (Required)
  - `images[]`: 圖片文件 (Optional, Max 9, <10MB)
  - `videos[]`: 視頻文件 (Optional, Max 1, <100MB)

#### 刪除貼文 🛡️ (Admin Only)
- **URL**: `/api/posts/{id}`
- **Method**: `DELETE`

#### 更新貼文 🛡️ (Admin Only)
- **URL**: `/api/posts/{id}`
- **Method**: `PATCH`
- **Content-Type**: 
  - `application/json` (僅更新文本/時間)
  - `multipart/form-data` (包含媒體操作)

**JSON 格式 (僅文本/時間)**:
```json
{
  "content": "更新後的內容",           // [可選] 更新貼文內容
  "created_at": "2023-12-25T10:00:00Z" // [可選] 更新創建時間 (ISO 8601 格式)
}
```

**FormData 格式 (包含媒體)**:
```
content: "更新後的內容"                    // [可選] 文本
created_at: "2023-12-25T10:00:00Z"        // [可選] 時間
delete_images[]: [1, 3]                   // [可選] 要刪除的圖片 ID 數組
delete_videos[]: [2]                      // [可選] 要刪除的影片 ID 數組
images[]: File                            // [可選] 新增的圖片文件
videos[]: File                            // [可選] 新增的影片文件
```

- **Note**: 
  - 支持同時刪除舊媒體和上傳新媒體
  - 刪除媒體時會同時刪除文件系統中的文件
  - 時間格式支持 ISO 8601 (如 `2023-12-25T10:00:00Z`)。

#### 置頂貼文 🛡️ (Admin Only)
- **URL**: `/api/posts/{id}/pin`
- **Method**: `POST`

#### 取消置頂 🛡️ (Admin Only)
- **URL**: `/api/posts/{id}/unpin`
- **Method**: `POST`

#### 點贊貼文 🔐
- **URL**: `/api/posts/{id}/like`
- **Method**: `POST`
- **Response**: 返回最新的點贊數。

---

### 4.3 評論 (Comments)

#### 獲取評論列表 🔓
- **URL**: `/api/posts/{id}/comments`
- **Method**: `GET`

#### 發表評論 🔓 (支持遊客)
- **URL**: `/api/posts/{id}/comments`
- **Method**: `POST`
- **Body**:
  ```json
  {
    "content": "評論內容",
    "authorName": "遊客暱稱 (未登錄時必填)"
  }
  ```

---

### 4.4 用戶 (Users)

#### 獲取用戶資料 🔓
- **URL**: `/api/users/{email}`
- **Method**: `GET`

#### 更新個人資料 🔐
- **URL**: `/api/users/me`
- **Method**: `POST`
- **Content-Type**: `multipart/form-data`
- **Body**:
  - `displayName`: 顯示名稱
  - `avatar`: 頭像文件

---

## 5. 錯誤代碼參考

| HTTP Code | 描述 | 常見原因 |
|-----------|------|----------|
| 200 | OK | 請求成功 |
| 201 | Created | 資源創建成功 |
| 400 | Bad Request | 參數錯誤、驗證失敗 |
| 401 | Unauthorized | 未登錄、Token 過期 |
| 403 | Forbidden | 權限不足 (如普通用戶嘗試發帖) |
| 404 | Not Found | 資源不存在 |
| 429 | Too Many Requests | 請求過於頻繁 |
| 500 | Internal Server Error | 服務器內部錯誤 |

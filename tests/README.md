# 測試說明文檔

## 測試概覽

本專案包含以下測試：

### 原有測試（3 個）
1. **test_query_builder.php** - QueryBuilder 基礎功能測試
2. **test_services.php** - Service 層集成測試
3. **test_token.php** - Token 相關測試

### 新增測試（3 個）- 針對架構優化
4. **test_exceptions.php** - 異常處理系統測試
5. **test_querybuilder_enhanced.php** - QueryBuilder 增強功能測試
6. **test_middleware.php** - Middleware 系統測試

---

## 快速運行所有測試

```bash
# 運行所有測試
./tests/run_all_tests.sh
```

---

## 單獨運行測試

### 1. 異常處理測試
```bash
php tests/test_exceptions.php
```

**測試內容：**
- ValidationException (400)
- NotFoundException (404)
- UnauthorizedException (401)
- ForbiddenException (403)
- 異常詳細信息傳遞

---

### 2. 增強 QueryBuilder 測試
```bash
php tests/test_querybuilder_enhanced.php
```

**測試內容：**
- `whereIn()` - IN 查詢
- `whereNotIn()` - NOT IN 查詢
- `orWhere()` - OR 條件
- `offset()` - 分頁偏移
- `transaction()` - 事務提交
- `transaction()` - 事務回滾
- 空數組處理

---

### 3. Middleware 系統測試
```bash
php tests/test_middleware.php
```

**測試內容：**
- Middleware 接口實現驗證
- Middleware Pipeline 執行順序
- AuthMiddleware 錯誤處理

---

### 4. 原有 QueryBuilder 測試
```bash
php tests/test_query_builder.php
```

---

### 5. Service 集成測試
```bash
php tests/test_services.php
```

---

### 6. Token 測試
```bash
php tests/test_token.php
```

---

## 測試要求

### 環境準備
1. **數據庫** - 需要配置好的 MySQL 數據庫
2. **環境變量** - `.env` 文件已配置
3. **PHP 版本** - PHP 8.2+

### 運行前準備
```bash
# 確保數據庫已運行（如果使用 Docker）
docker-compose up -d mysql

# 或確保本地 MySQL 已運行
```

---

## 預期輸出

### 成功示例
```
Running Exception Tests...

Test 1: ValidationException... PASS
Test 2: NotFoundException... PASS
Test 3: UnauthorizedException... PASS
Test 4: ForbiddenException... PASS
Test 5: Exception with error details... PASS

All Exception Tests Passed!
```

### 完整測試套件輸出
```bash
./tests/run_all_tests.sh

================================
P-Weibo Backend Test Suite
================================

Running: Exception Handling Tests
----------------------------------------
...
✓ Exception Handling Tests passed

Running: Enhanced QueryBuilder Tests
----------------------------------------
...
✓ Enhanced QueryBuilder Tests passed

Running: Middleware Tests
----------------------------------------
...
✓ Middleware Tests passed

================================
Test Summary
================================
Passed: 6
Failed: 0

All tests passed! ✓
```

---

## 測試覆蓋範圍

### 異常處理系統 ✅
- [x] 所有自定義異常類
- [x] 狀態碼正確性
- [x] 錯誤詳情傳遞

### QueryBuilder 增強 ✅
- [x] whereIn/whereNotIn
- [x] orWhere
- [x] offset
- [x] transaction 提交和回滾
- [x] 邊界情況（空數組等）

### Middleware 系統 ✅
- [x] 接口實現
- [x] Pipeline 執行順序
- [x] AuthMiddleware 行為

### API 回應格式
- [ ] 待添加（可選）- ApiResponse 單元測試

### Router Middleware Integration
- [ ] 待添加（可選）- 集成測試

---

## CI/CD 集成建議

如果需要集成到 CI/CD pipeline：

```yaml
# .github/workflows/test.yml 示例
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: weibo_clone
          MYSQL_ROOT_PASSWORD: rootpass
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Setup environment
        run: cp .env.example .env
      
      - name: Run tests
        run: ./tests/run_all_tests.sh
```

---

## 故障排除

### 數據庫連接錯誤
```bash
# 檢查 .env 配置
cat .env | grep DB_

# 確保數據庫正在運行
docker-compose ps
# 或
mysql -u root -p -e "SHOW DATABASES;"
```

### 權限錯誤
```bash
# 確保測試腳本可執行
chmod +x tests/run_all_tests.sh
```

### 測試數據殘留
測試會自動清理測試數據，但如果測試中斷：
```sql
-- 手動清理測試數據
DELETE FROM users WHERE username LIKE 'test_%' OR username LIKE 'qb_test_%' OR username LIKE 'svc_test_%';
```

---

## 下一步

建議添加的測試（可選）：
1. **API 端點集成測試** - 使用 HTTP 請求測試完整流程
2. **ApiResponse 單元測試** - 驗證回應格式
3. **ExceptionHandler 集成測試** - 驗證異常轉換為 JSON 回應
4. **Router Middleware 集成測試** - 驗證完整的請求處理流程

---

## 總結

- ✅ **6 個測試文件** - 涵蓋核心功能和新增優化
- ✅ **統一測試腳本** - 一鍵運行所有測試
- ✅ **完整文檔** - 清晰的使用說明
- ✅ **自動清理** - 測試後自動清理數據

所有測試都是獨立的集成測試，可以單獨運行或批量運行。

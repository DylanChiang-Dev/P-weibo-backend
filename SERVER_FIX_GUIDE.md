# Media API 服务器修复指南

本文档提供给**后端/运维团队**使用，用于修复 Media API 500 错误。

## 问题诊断

Media API (`POST /api/media`) 返回 500 Internal Server Error，所有上传请求都失败。

### 根本原因

经过代码审查，发现以下可能的原因（按优先级排序）：

1. ✅ **uploads 目录不存在或无写入权限**
2. ✅ **数据库 `media` 表不存在**
3. ⚠️ **代码未正确部署到服务器**
4. ⚠️ **PHP-FPM 缓存了旧代码，未重启**

## 快速修复步骤

### 方法 1: 使用自动诊断脚本（推荐）

1. **访问诊断脚本**
   ```bash
   # 在浏览器访问
   https://pyqapi.3331322.xyz/setup_media.php
   
   # 或在命令行运行
   cd /www/wwwroot/pyqapi.3331322.xyz
   php public/setup_media.php
   ```

2. **按照脚本提示修复问题**
   - 脚本会自动创建必要的目录
   - 自动创建数据库表
   - 检查所有依赖条件
   - 提供详细的诊断报告

3. **重启 PHP-FPM**
   ```bash
   /etc/init.d/php-fpm-82 restart
   ```

### 方法 2: 手动修复

#### 步骤 1: 确认代码已部署

```bash
cd /www/wwwroot/pyqapi.3331322.xyz
git log -1 --oneline
git status
```

**期望结果**: 应该看到最新的提交（包含 MediaController 修复）

如果不是最新代码：
```bash
git pull origin main  # 或您的主分支名称
```

#### 步骤 2: 检查 uploads 目录

**重要**: 上传目录应该在 `public/uploads`

```bash
# 检查目录
ls -la /www/wwwroot/pyqapi.3331322.xyz/public/uploads

# 如果不存在，创建它
mkdir -p /www/wwwroot/pyqapi.3331322.xyz/public/uploads

# 设置所有者（www 是 nginx/php-fpm 用户）
chown -R www:www /www/wwwroot/pyqapi.3331322.xyz/public/uploads

# 设置权限
chmod -R 775 /www/wwwroot/pyqapi.3331322.xyz/public/uploads
```

**验证**:
```bash
ls -la /www/wwwroot/pyqapi.3331322.xyz/public/uploads
```

应该看到:
```
drwxrwxr-x 2 www www 4096 ... .
```

#### 步骤 3: 创建数据库表

**方法 A**: 在浏览器访问
```
https://pyqapi.3331322.xyz/migrate_media.php
```

**方法 B**: 使用命令行
```bash
cd /www/wwwroot/pyqapi.3331322.xyz

# 执行迁移脚本
mysql -u 数据库用户 -p 数据库名 < migrations/008_create_media_table.sql
```

**方法 C**: 使用 PHP 脚本
```bash
php -r "
require 'config/config.php';
require 'app/Core/Database.php';
\App\Core\Database::init(config()['db']);
\$pdo = \App\Core\Database::getPdo();
\$sql = file_get_contents('migrations/008_create_media_table.sql');
\$pdo->exec(\$sql);
echo 'Media table created successfully\n';
"
```

**验证**:
```bash
mysql -u 用户名 -p -e "DESCRIBE 数据库名.media"
```

应该看到表结构:
```
+------------+--------------+------+-----+-------------------+----------------+
| Field      | Type         | Null | Key | Default           | Extra          |
+------------+--------------+------+-----+-------------------+----------------+
| id         | int          | NO   | PRI | NULL              | auto_increment |
| user_id    | bigint unsigned | NO | MUL | NULL              |                |
| url        | varchar(500) | NO   |     | NULL              |                |
| filename   | varchar(255) | NO   |     | NULL              |                |
| filepath   | varchar(500) | NO   |     | NULL              |                |
| size       | int          | YES  |     | NULL              |                |
| mime_type  | varchar(100) | YES  |     | NULL              |                |
| created_at | timestamp    | YES  |     | CURRENT_TIMESTAMP |                |
+------------+--------------+------+-----+-------------------+----------------+
```

#### 步骤 4: 检查 .env 配置

```bash
cat /www/wwwroot/pyqapi.3331322.xyz/.env | grep UPLOAD
```

确认 `UPLOAD_PATH` 配置正确:
```env
UPLOAD_PATH=/www/wwwroot/pyqapi.3331322.xyz/public/uploads
```

如果没有此配置，添加它:
```bash
echo "UPLOAD_PATH=/www/wwwroot/pyqapi.3331322.xyz/public/uploads" >> .env
```

#### 步骤 5: 重启 PHP-FPM

```bash
/etc/init.d/php-fpm-82 restart
```

**验证重启成功**:
```bash
/etc/init.d/php-fpm-82 status
```

#### 步骤 6: 检查错误日志

```bash
# PHP-FPM 日志
tail -100 /www/server/php/82/var/log/php-fpm.log

# 应用日志（如果有）
tail -100 /www/wwwroot/pyqapi.3331322.xyz/logs/*.log
```

查找任何与 Media API 相关的错误。

## 测试上传功能

### 测试 1: 检查路由是否存在

```bash
curl -i https://pyqapi.3331322.xyz/api/media
```

**期望结果**:
- `405 Method Not Allowed` - 说明路由存在，但不接受 GET 请求（正常）
- `404 Not Found` - 说明路由不存在（**异常**）

### 测试 2: 测试实际上传

```bash
# 1. 获取认证 token
TOKEN=$(curl -s -X POST https://pyqapi.3331322.xyz/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"您的邮箱","password":"您的密码"}' \
  | jq -r '.data.access_token')

echo "Token: $TOKEN"

# 2. 创建测试文件
echo "test content" > /tmp/test-upload.txt

# 3. 测试上传
curl -v -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@/tmp/test-upload.txt" \
  https://pyqapi.3331322.xyz/api/media
```

**期望的成功响应**:
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "url": "https://pyqapi.3331322.xyz/uploads/abc123...jpg",
        "filename": "test-upload.txt",
        "size": 12,
        "mime_type": "text/plain",
        "created_at": "2025-11-29 14:30:00"
      }
    ]
  }
}
```

**如果仍然 500 错误**:
- 检查 PHP 错误日志（上面的命令）
- 确认文件确实被创建在 uploads 目录
- 检查数据库是否有新记录

### 测试 3: 测试图片上传

```bash
# 下载一个测试图片
curl -o /tmp/test-image.jpg "https://via.placeholder.com/150"

# 上传图片
curl -v -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@/tmp/test-image.jpg" \
  https://pyqapi.3331322.xyz/api/media
```

## 常见问题排查

### 问题 1: "Permission denied" 错误

**原因**: uploads 目录权限不足

**解决**:
```bash
chmod -R 775 /www/wwwroot/pyqapi.3331322.xyz/public/uploads
chown -R www:www /www/wwwroot/pyqapi.3331322.xyz/public/uploads
```

### 问题 2: "Table 'media' doesn't exist"

**原因**: 数据库表未创建

**解决**: 执行上面的"步骤 3: 创建数据库表"

### 问题 3: "SQLSTATE[23000]: Foreign key constraint fails"

**原因**: user_id 不存在于 users 表

**解决**: 确保使用的 token 对应的用户存在于数据库

### 问题 4: 仍然返回 500 但无具体错误信息

**原因**: 生产环境配置

**解决**: 临时修改配置以查看详细错误
```bash
# 编辑 .env
vi /www/wwwroot/pyqapi.3331322.xyz/.env

# 将 APP_ENV 改为 development（临时）
APP_ENV=development

# 重启 PHP-FPM
/etc/init.d/php-fpm-82 restart

# 重新测试，会看到详细错误
# 修复后，记得改回 production
```

## 验证清单

- [ ] uploads 目录 (`public/uploads`) 已创建且有正确权限
- [ ] media 数据库表已创建
- [ ] .env 配置正确
- [ ] PHP-FPM 已重启
- [ ] 路由测试返回 405（而非 404）
- [ ] 测试上传成功返回 201
- [ ] uploads 目录中有上传的文件
- [ ] 数据库 media 表有新记录

## 提供测试证据

修复完成后，请提供以下信息：

1. **成功的上传测试**:
   ```bash
   curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -F "files[]=@test.jpg" \
     https://pyqapi.3331322.xyz/api/media
   ```
   
   粘贴完整的响应。

2. **文件列表**:
   ```bash
   ls -la /www/wwwroot/pyqapi.3331322.xyz/public/uploads/
   ```

3. **数据库记录**:
   ```bash
   mysql -u 用户 -p -e "SELECT * FROM 数据库名.media LIMIT 5"
   ```

## 安全提示

修复完成后，删除以下诊断脚本:
```bash
rm /www/wwwroot/pyqapi.3331322.xyz/public/setup_media.php
rm /www/wwwroot/pyqapi.3331322.xyz/public/diagnose_media.php
rm /www/wwwroot/pyqapi.3331322.xyz/public/migrate_media.php
```

这些脚本可能暴露系统信息，不应保留在生产环境。

## 联系支持

如果按照以上步骤仍无法解决，请提供:
1. PHP 错误日志（最后 50 行）
2. 应用日志（如果有）
3. 测试上传的完整 curl 输出（带 `-v` 参数）
4. `setup_media.php` 的诊断报告截图

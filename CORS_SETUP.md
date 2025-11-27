# CORS 配置指南

## 问题现象

前端访问 API 时出现 CORS 错误：
```
Access to XMLHttpRequest at 'https://pyqapi.3331322.xyz/api/blog/articles/2' 
from origin 'http://localhost:4321' has been blocked by CORS policy
```

## 解决方案

### 1. 修改服务器上的 `.env` 文件

编辑 `/www/wwwroot/pyqapi.3331322.xyz/.env`，找到 `FRONTEND_ORIGIN` 配置项。

**开发环境（推荐）**：
```bash
# 允许开发环境 localhost 访问
FRONTEND_ORIGIN=http://localhost:4321,http://localhost:3000
```

**生产环境**：
```bash
# 仅允许生产域名
FRONTEND_ORIGIN=https://blog.yourdomain.com
```

**开发+生产混合**（最灵活）：
```bash
# 同时允许开发和生产环境
FRONTEND_ORIGIN=http://localhost:4321,https://blog.yourdomain.com
```

**临时调试（不推荐用于生产）**：
```bash
# 允许所有来源
FRONTEND_ORIGIN=*
```

### 2. 重启 PHP 服务

修改 `.env` 后必须重启服务：
```bash
/etc/init.d/php-fpm-82 restart
```

### 3. 验证配置

在浏览器控制台测试：
```javascript
fetch('https://pyqapi.3331322.xyz/api/blog/categories')
  .then(res => res.json())
  .then(data => console.log('Success:', data))
  .catch(err => console.error('Error:', err))
```

## CORS 中间件说明

项目已内置 `CorsMiddleware`，支持：

- ✅ 多个域名（逗号分隔）
- ✅ 通配符 `*`（允许所有来源）
- ✅ 自动处理 OPTIONS 预检请求
- ✅ 正确的 CORS headers

**响应头包含**：
- `Access-Control-Allow-Origin`: 请求来源
- `Access-Control-Allow-Credentials`: true
- `Access-Control-Allow-Methods`: GET, POST, PUT, PATCH, DELETE, OPTIONS
- `Access-Control-Allow-Headers`: Content-Type, Authorization, X-Requested-With

## 常见问题

### Q: 修改后仍然报错？
A: 确保已重启 PHP-FPM 服务，并清除浏览器缓存。

### Q: 生产环境如何配置？
A: 将 `FRONTEND_ORIGIN` 设置为生产域名（如 `https://blog.example.com`），不要使用 `*`。

### Q: 为什么需要域名前缀（http://或https://）？
A: CORS 规范要求完整的 origin，必须包含协议。

## 技术细节

CORS 中间件位于 `app/Middleware/CorsMiddleware.php`，在每个请求前自动执行。如果请求的 `Origin` 在允许列表中，会自动添加所需的响应头。

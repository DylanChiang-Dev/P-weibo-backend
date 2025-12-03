#!/bin/bash
# 获取token并运行媒体API测试

API_URL="https://pyqapi.3331322.xyz"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

if [ -z "$ADMIN_PASSWORD" ]; then
    echo "错误：请设置ADMIN_PASSWORD环境变量"
    echo "用法: ADMIN_EMAIL=your_email ADMIN_PASSWORD=your_password bash $0"
    exit 1
fi

echo "正在登录获取token..."
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.access_token // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo "❌ 登录失败"
    echo "响应: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ 登录成功"
echo ""

# 导出token并运行测试
export TOKEN
bash "$(dirname "$0")/quick-test-media.sh"

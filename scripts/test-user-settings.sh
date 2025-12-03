#!/bin/bash
# 测试用户设置API

API_URL="https://pyqapi.3331322.xyz"

echo "========================================"
echo "测试用户设置API"
echo "========================================"

# 获取token
echo "正在获取token..."
TOKEN=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"3331322@gmail.com","password":"ca123456789"}' | jq -r '.data.access_token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo "❌ 无法获取token"
    exit 1
fi

echo "✅ Token获取成功"
echo ""

echo "测试1: 获取用户设置（初始状态）"
curl -s -X GET "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "测试2: 保存API Keys"
SAVE_RESPONSE=$(curl -s -X POST "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "api_keys": {
      "tmdb_api_key": "test_tmdb_key_12345",
      "rawg_api_key": "test_rawg_key_67890",
      "google_books_api_key": "test_google_books_key_abc"
    }
  }')

echo "$SAVE_RESPONSE" | jq '.'
if [ "$(echo "$SAVE_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 保存成功"
else
    echo "❌ 保存失败"
fi
echo ""

echo "测试3: 再次获取用户设置（验证保存）"
curl -s -X GET "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "测试4: 更新部分API Keys"
curl -s -X POST "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "api_keys": {
      "tmdb_api_key": "updated_tmdb_key_99999"
    }
  }' | jq '.'
echo ""

echo "测试5: 验证更新结果"
curl -s -X GET "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "========================================"
echo "测试完成"
echo "========================================"

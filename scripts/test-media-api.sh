#!/bin/bash
# Media API 本地测试脚本

set -e

echo "================================"
echo "Media API 本地测试"
echo "================================"
echo ""

# 配置
API_URL="${API_URL:-http://localhost:8080}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin-password}"

echo "API URL: $API_URL"
echo ""

# 颜色输出
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. 测试登录获取 token
echo "步骤 1: 登录获取 token..."
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.access_token // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo -e "${RED}❌ 登录失败${NC}"
    echo "响应: $LOGIN_RESPONSE"
    exit 1
fi

echo -e "${GREEN}✅ 登录成功${NC}"
echo "Token: ${TOKEN:0:20}..."
echo ""

# 2. 创建测试文件
echo "步骤 2: 创建测试文件..."
TEST_DIR="/tmp/media-api-test-$$"
mkdir -p "$TEST_DIR"

# 创建测试文本文件
echo "Test content - $(date)" > "$TEST_DIR/test.txt"

# 创建测试图片 (1x1 PNG)
printf '\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0A\x49\x44\x41\x54\x78\x9C\x63\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00\x49\x45\x4E\x44\xAE\x42\x60\x82' > "$TEST_DIR/test.png"

echo -e "${GREEN}✅ 测试文件已创建${NC}"
echo "  - $TEST_DIR/test.txt"
echo "  - $TEST_DIR/test.png"
echo ""

# 3. 测试上传文本文件
echo "步骤 3: 测试上传文本文件..."
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test.txt" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "201" ]; then
    echo -e "${GREEN}✅ 文本文件上传成功 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.'
else
    echo -e "${RED}❌ 文本文件上传失败 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 4. 测试上传图片
echo "步骤 4: 测试上传图片..."
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test.png" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "201" ]; then
    echo -e "${GREEN}✅ 图片上传成功 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.'
    
    # 提取媒体 ID 用于后续测试
    MEDIA_ID=$(echo "$RESPONSE_BODY" | jq -r '.data.items[0].id // empty')
else
    echo -e "${RED}❌ 图片上传失败 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 5. 测试批量上传
echo "步骤 5: 测试批量上传..."
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test.txt" \
  -F "files[]=@$TEST_DIR/test.png" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "201" ]; then
    ITEMS_COUNT=$(echo "$RESPONSE_BODY" | jq '.data.items | length')
    echo -e "${GREEN}✅ 批量上传成功 (HTTP $HTTP_CODE, $ITEMS_COUNT 个文件)${NC}"
    echo "$RESPONSE_BODY" | jq '.'
else
    echo -e "${RED}❌ 批量上传失败 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 6. 测试获取媒体列表
echo "步骤 6: 测试获取媒体列表..."
LIST_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET \
  -H "Authorization: Bearer $TOKEN" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$LIST_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$LIST_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "200" ]; then
    ITEMS_COUNT=$(echo "$RESPONSE_BODY" | jq '.data.items | length')
    TOTAL=$(echo "$RESPONSE_BODY" | jq '.data.pagination.total')
    echo -e "${GREEN}✅ 获取媒体列表成功 (HTTP $HTTP_CODE)${NC}"
    echo "总计: $TOTAL 个媒体文件, 当前页: $ITEMS_COUNT 个"
    echo "$RESPONSE_BODY" | jq '.data.pagination'
else
    echo -e "${RED}❌ 获取媒体列表失败 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 7. 测试删除媒体（如果有 ID）
if [ -n "$MEDIA_ID" ] && [ "$MEDIA_ID" != "null" ]; then
    echo "步骤 7: 测试删除媒体 (ID: $MEDIA_ID)..."
    DELETE_RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE \
      -H "Authorization: Bearer $TOKEN" \
      "$API_URL/api/media/$MEDIA_ID")
    
    HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n1)
    RESPONSE_BODY=$(echo "$DELETE_RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✅ 删除媒体成功 (HTTP $HTTP_CODE)${NC}"
        echo "$RESPONSE_BODY" | jq '.'
    else
        echo -e "${YELLOW}⚠️  删除媒体失败 (HTTP $HTTP_CODE)${NC}"
        echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
    fi
    echo ""
else
    echo "步骤 7: 跳过删除测试（没有可用的媒体 ID）"
    echo ""
fi

# 清理
echo "清理测试文件..."
rm -rf "$TEST_DIR"
echo -e "${GREEN}✅ 清理完成${NC}"
echo ""

echo "================================"
echo "测试完成！"
echo "================================"

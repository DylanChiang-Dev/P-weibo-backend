#!/bin/bash
# 快速媒体API上传测试脚本

set -e

echo "================================"
echo "媒体API上传测试 (使用生产环境)"
echo "================================"
echo ""

# 配置
API_URL="https://pyqapi.3331322.xyz"

# 颜色输出
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查是否提供token
if [ -z "$TOKEN" ]; then
    echo -e "${RED}错误：请设置TOKEN环境变量${NC}"
    echo "用法: TOKEN=your_jwt_token bash $0"
    exit 1
fi

echo "API URL: $API_URL"
echo "Token: ${TOKEN:0:20}..."
echo ""

# 创建测试文件
echo "创建测试文件..."
TEST_DIR="/tmp/media-api-test-$$"
mkdir -p "$TEST_DIR"

# 创建1x1 PNG测试图片
printf '\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0A\x49\x44\x41\x54\x78\x9C\x63\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00\x49\x45\x4E\x44\xAE\x42\x60\x82' > "$TEST_DIR/test1.png"
printf '\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D\x49\x48\x44\x52\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0A\x49\x44\x41\x54\x78\x9C\x63\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00\x49\x45\x4E\x44\xAE\x42\x60\x82' > "$TEST_DIR/test2.png"

# 创建文本文件（用于测试错误处理）
echo "This is not an image" > "$TEST_DIR/test.txt"

echo -e "${GREEN}✅ 测试文件已创建${NC}"
echo ""

# 测试1: 单文件上传 (files[]=格式)
echo "========== 测试1: 单文件上传 (files[]=格式) =========="
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test1.png" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

echo "HTTP状态码: $HTTP_CODE"
if [ "$HTTP_CODE" = "201" ]; then
    ITEMS_COUNT=$(echo "$RESPONSE_BODY" | jq '.data.items | length' 2>/dev/null || echo "0")
    if [ "$ITEMS_COUNT" -gt "0" ]; then
        echo -e "${GREEN}✅ 测试通过！成功上传 $ITEMS_COUNT 个文件${NC}"
        echo "$RESPONSE_BODY" | jq '.'
    else
        echo -e "${RED}❌ 测试失败！items数组为空${NC}"
        echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
    fi
else
    echo -e "${RED}❌ 测试失败！HTTP $HTTP_CODE${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 测试2: 多文件上传
echo "========== 测试2: 多文件上传 =========="
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test1.png" \
  -F "files[]=@$TEST_DIR/test2.png" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

echo "HTTP状态码: $HTTP_CODE"
if [ "$HTTP_CODE" = "201" ]; then
    ITEMS_COUNT=$(echo "$RESPONSE_BODY" | jq '.data.items | length' 2>/dev/null || echo "0")
    if [ "$ITEMS_COUNT" = "2" ]; then
        echo -e "${GREEN}✅ 测试通过！成功上传 $ITEMS_COUNT 个文件${NC}"
        echo "$RESPONSE_BODY" | jq '.data.items[] | {id, filename, size}'
    else
        echo -e "${YELLOW}⚠️  预期上传2个文件，实际上传了 $ITEMS_COUNT 个${NC}"
        echo "$RESPONSE_BODY" | jq '.'
    fi
else
    echo -e "${RED}❌ 测试失败！HTTP $HTTP_CODE${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 测试3: 无效文件类型（应该返回错误）
echo "========== 测试3: 无效文件类型处理 =========="
UPLOAD_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -F "files[]=@$TEST_DIR/test.txt" \
  "$API_URL/api/media")

HTTP_CODE=$(echo "$UPLOAD_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$UPLOAD_RESPONSE" | head -n -1)

echo "HTTP状态码: $HTTP_CODE"
if [ "$HTTP_CODE" = "400" ] || [ "$HTTP_CODE" = "422" ]; then
    echo -e "${GREEN}✅ 测试通过！正确拒绝了无效文件类型 (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE_BODY" | jq '.'
else
    echo -e "${YELLOW}⚠️  预期返回400/422，实际返回 HTTP $HTTP_CODE${NC}"
    echo "$RESPONSE_BODY" | jq '.' || echo "$RESPONSE_BODY"
fi
echo ""

# 清理
echo "清理测试文件..."
rm -rf "$TEST_DIR"
echo -e "${GREEN}✅ 清理完成${NC}"
echo ""

echo "================================"
echo "测试完成！"
echo "================================"

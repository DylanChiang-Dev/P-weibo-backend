#!/bin/bash
# 测试文章浏览数增加接口（生产环境）

API_URL="https://pyqapi.3331322.xyz"

echo "========================================"
echo "测试文章浏览数增加功能（生产环境）"
echo "========================================"

# 1. 获取文章列表以找到一个测试ID
echo "正在获取文章列表..."
LIST_RESPONSE=$(curl -s "$API_URL/api/blog/articles?limit=1")
ARTICLE_ID=$(echo "$LIST_RESPONSE" | jq -r '.data.items[0].id // empty')

if [ -z "$ARTICLE_ID" ] || [ "$ARTICLE_ID" = "null" ]; then
    echo "❌ 无法获取文章ID，请确保至少有一篇已发布的文章"
    echo "响应: $LIST_RESPONSE"
    exit 1
fi

echo "使用文章 ID: $ARTICLE_ID"
ARTICLE_TITLE=$(echo "$LIST_RESPONSE" | jq -r '.data.items[0].title')
CURRENT_VIEWS=$(echo "$LIST_RESPONSE" | jq -r '.data.items[0].view_count')
echo "文章标题: $ARTICLE_TITLE"
echo "当前浏览数: $CURRENT_VIEWS"
echo ""

# 2. 调用增加浏览数接口
echo "正在增加浏览数..."
VIEW_RESPONSE=$(curl -s -X POST "$API_URL/api/blog/articles/$ARTICLE_ID/view")
echo "响应: $VIEW_RESPONSE"

SUCCESS=$(echo "$VIEW_RESPONSE" | jq -r '.success')
NEW_VIEWS=$(echo "$VIEW_RESPONSE" | jq -r '.data.view_count')

if [ "$SUCCESS" = "true" ]; then
    echo "✅ 调用成功"
    echo "新的浏览数: $NEW_VIEWS"
    
    if [ "$NEW_VIEWS" -gt "$CURRENT_VIEWS" ]; then
        echo "✅ 验证通过：浏览数已增加 ($CURRENT_VIEWS → $NEW_VIEWS)"
    else
        echo "❌ 验证失败：浏览数未增加"
    fi
else
    echo "❌ 调用失败"
    echo "$VIEW_RESPONSE" | jq '.'
fi
echo ""

# 3. 再次获取文章列表验证数据库值
echo "正在验证数据库值..."
VERIFY_RESPONSE=$(curl -s "$API_URL/api/blog/articles?limit=1")
VERIFY_VIEWS=$(echo "$VERIFY_RESPONSE" | jq -r '.data.items[0].view_count')
echo "数据库中的浏览数: $VERIFY_VIEWS"

if [ "$VERIFY_VIEWS" -eq "$NEW_VIEWS" ]; then
    echo "✅ 数据库验证通过：浏览数已持久化"
else
    echo "❌ 数据库验证失败：浏览数不一致"
fi

echo "========================================"

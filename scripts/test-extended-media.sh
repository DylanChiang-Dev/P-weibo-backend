#!/bin/bash
# 测试扩展媒体库API

API_URL="https://pyqapi.3331322.xyz"

echo "========================================"
echo "测试扩展媒体库API"
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

# ============================================
# 测试播客API
# ============================================
echo "========== 测试播客API =========="
echo ""

echo "测试1: 获取播客列表"
curl -s -X GET "$API_URL/api/library/podcasts" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "测试2: 添加播客"
PODCAST_RESPONSE=$(curl -s -X POST "$API_URL/api/library/podcasts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "podcast_id": "test123",
    "title": "测试播客",
    "host": "主播名",
    "my_rating": 8.5,
    "episodes_listened": 5,
    "total_episodes": 50,
    "status": "listening"
  }')

echo "$PODCAST_RESPONSE" | jq '.'
PODCAST_ID=$(echo "$PODCAST_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$PODCAST_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加播客成功 (ID: $PODCAST_ID)"
else
    echo "❌ 添加播客失败"
fi
echo ""

# ============================================
# 测试纪录片API
# ============================================
echo "========== 测试纪录片API =========="
echo ""

echo "测试3: 获取纪录片列表"
curl -s -X GET "$API_URL/api/library/documentaries" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "测试4: 添加纪录片"
DOC_RESPONSE=$(curl -s -X POST "$API_URL/api/library/documentaries" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tmdb_id": 12345,
    "my_rating": 9.0,
    "my_review": "优秀的纪录片",
    "status": "watched",
    "release_date": "2020-01-01",
    "completed_date": "2024-12-01"
  }')

echo "$DOC_RESPONSE" | jq '.'
DOC_ID=$(echo "$DOC_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$DOC_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加纪录片成功 (ID: $DOC_ID)"
else
    echo "❌ 添加纪录片失败"
fi
echo ""

# ============================================
# 测试动画API
# ============================================
echo "========== 测试动画API =========="
echo ""

echo "测试5: 获取动画列表"
curl -s -X GET "$API_URL/api/library/anime" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

echo "测试6: 添加动画"
ANIME_RESPONSE=$(curl -s -X POST "$API_URL/api/library/anime" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "anime_id": 9999,
    "my_rating": 10.0,
    "episodes_watched": 12,
    "total_episodes": 24,
    "status": "watching",
    "first_air_date": "2023-04-01"
  }')

echo "$ANIME_RESPONSE" | jq '.'
ANIME_ID=$(echo "$ANIME_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$ANIME_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加动画成功 (ID: $ANIME_ID)"
else
    echo "❌ 添加动画失败"
fi
echo ""

if [ ! -z "$ANIME_ID" ] && [ "$ANIME_ID" != "null" ]; then
    echo "测试7: 更新动画进度"
    curl -s -X PATCH "$API_URL/api/library/anime/$ANIME_ID/progress" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{"episodes_watched": 13}' | jq '.'
    echo ""
fi

echo "========================================="
echo "测试完成"
echo "========================================="
echo "播客ID: $PODCAST_ID"
echo "纪录片ID: $DOC_ID"
echo "动画ID: $ANIME_ID"

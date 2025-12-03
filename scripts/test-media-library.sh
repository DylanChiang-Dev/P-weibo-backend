#!/bin/bash
# 测试媒体库API

API_URL="${API_URL:-https://pyqapi.3331322.xyz}"

echo "========================================"
echo "测试媒体库API"
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
# 测试电影API
# ============================================
echo "========== 测试电影API =========="
echo ""

echo "测试1: 添加电影"
MOVIE_RESPONSE=$(curl -s -X POST "$API_URL/api/library/movies" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tmdb_id": 27205,
    "my_rating": 9.5,
    "my_review": "诺兰的神作《盗梦空间》",
    "status": "watched",
    "release_date": "2010-07-16",
    "completed_date": "2024-12-01"
  }')

echo "$MOVIE_RESPONSE" | jq '.'
MOVIE_ID=$(echo "$MOVIE_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$MOVIE_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加电影成功 (ID: $MOVIE_ID)"
else
    echo "❌ 添加电影失败"
fi
echo ""

echo "测试2: 获取电影列表"
LIST_RESPONSE=$(curl -s "$API_URL/api/library/movies" -H "Authorization: Bearer $TOKEN")
echo "$LIST_RESPONSE" | jq '.data.items[0]'
if [ "$(echo "$LIST_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 获取电影列表成功"
else
    echo "❌ 获取电影列表失败"
fi
echo ""

echo "测试3: 更新电影评分"
UPDATE_RESPONSE=$(curl -s -X PUT "$API_URL/api/library/movies/$MOVIE_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"my_rating": 10.0}')
echo "$UPDATE_RESPONSE" | jq '.'
if [ "$(echo "$UPDATE_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 更新电影成功"
else
    echo "❌ 更新电影失败"
fi
echo ""

# ============================================
# 测试电视剧API
# ============================================
echo "========== 测试电视剧API =========="
echo ""

echo "测试4: 添加电视剧"
TV_RESPONSE=$(curl -s -X POST "$API_URL/api/library/tv-shows" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tmdb_id": 1396,
    "my_rating": 9.8,
    "my_review": "Breaking Bad 经典美剧",
    "current_season": 5,
    "current_episode": 16,
    "status": "watched",
    "first_air_date": "2008-01-20",
    "completed_date": "2024-11-15"
  }')

echo "$TV_RESPONSE" | jq '.'
TV_ID=$(echo "$TV_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$TV_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加电视剧成功 (ID: $TV_ID)"
else
    echo "❌ 添加电视剧失败"
fi
echo ""

echo "测试5: 更新电视剧进度"
PROGRESS_RESPONSE=$(curl -s -X PATCH "$API_URL/api/library/tv-shows/$TV_ID/progress" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"current_season": 2, "current_episode": 5}')
echo "$PROGRESS_RESPONSE" | jq '.'
if [ "$(echo "$PROGRESS_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 更新进度成功"
else
    echo "❌ 更新进度失败"
fi
echo ""

# ============================================
# 测试书籍API
# ============================================
echo "========== 测试书籍API =========="
echo ""

echo "测试6: 添加书籍"
BOOK_RESPONSE=$(curl -s -X POST "$API_URL/api/library/books" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "google_books_id": "abc123",
    "isbn": "9787536692930",
    "my_rating": 9.0,
    "my_review": "刘慈欣的《三体》震撼人心",
    "status": "read",
    "publication_date": "2008-01-01",
    "completed_date": "2024-10-20"
  }')

echo "$BOOK_RESPONSE" | jq '.'
BOOK_ID=$(echo "$BOOK_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$BOOK_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加书籍成功 (ID: $BOOK_ID)"
else
    echo "❌ 添加书籍失败"
fi
echo ""

# ============================================
# 测试游戏API
# ============================================
echo "========== 测试游戏API =========="
echo ""

echo "测试7: 添加游戏"
GAME_RESPONSE=$(curl -s -X POST "$API_URL/api/library/games" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "rawg_id": 3328,
    "my_rating": 10.0,
    "my_review": "塞尔达传说：旷野之息 - 任天堂神作",
    "playtime_hours": 120,
    "platform": "Nintendo Switch",
    "status": "played",
    "release_date": "2017-03-03",
    "completed_date": "2024-09-10"
  }')

echo "$GAME_RESPONSE" | jq '.'
GAME_ID=$(echo "$GAME_RESPONSE" | jq -r '.data.id')
if [ "$(echo "$GAME_RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 添加游戏成功 (ID: $GAME_ID)"
else
    echo "❌ 添加游戏失败"
fi
echo ""

echo "========================================="
echo "测试汇总"
echo "========================================="
echo "电影ID: $MOVIE_ID"
echo "电视剧ID: $TV_ID"
echo "书籍ID: $BOOK_ID"
echo "游戏ID: $GAME_ID"
echo ""
echo "所有测试完成！"

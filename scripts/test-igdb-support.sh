#!/bin/bash
# 测试IGDB支持和用户设置

API_URL="https://pyqapi.3331322.xyz"

echo "========================================"
echo "测试IGDB完整功能"
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
# 测试1: 保存IGDB credentials
# ============================================
echo "========== 测试1: 保存IGDB Credentials =========="
SAVE_RESULT=$(curl -s -X POST "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "api_keys": {
      "tmdb_api_key": "test_tmdb_123",
      "rawg_api_key": "test_rawg_456",
      "igdb_client_id": "test_igdb_client_789",
      "igdb_access_token": "test_igdb_token_abc",
      "google_books_api_key": "test_google_def"
    }
  }')

echo "$SAVE_RESULT" | jq '.'
if [ "$(echo "$SAVE_RESULT" | jq -r '.success')" = "true" ]; then
    echo "✅ IGDB credentials保存成功"
else
    echo "❌ 保存失败"
fi
echo ""

# ============================================
# 测试2: 获取用户设置验证IGDB字段
# ============================================
echo "========== 测试2: 验证IGDB Credentials已保存 =========="
GET_RESULT=$(curl -s -X GET "$API_URL/api/user/settings" \
  -H "Authorization: Bearer $TOKEN")

echo "$GET_RESULT" | jq '.'

IGDB_CLIENT=$(echo "$GET_RESULT" | jq -r '.data.api_keys.igdb_client_id')
IGDB_TOKEN=$(echo "$GET_RESULT" | jq -r '.data.api_keys.igdb_access_token')

if [ "$IGDB_CLIENT" = "test_igdb_client_789" ] && [ "$IGDB_TOKEN" = "test_igdb_token_abc" ]; then
    echo "✅ IGDB credentials正确返回"
else
    echo "❌ IGDB credentials未正确保存或返回"
    echo "   igdb_client_id: $IGDB_CLIENT"
    echo "   igdb_access_token: $IGDB_TOKEN"
fi
echo ""

# ============================================
# 测试3: 添加游戏（带igdb_id）
# ============================================
echo "========== 测试3: 添加游戏（带IGDB ID） =========="
GAME_RESULT=$(curl -s -X POST "$API_URL/api/library/games" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "rawg_id": 5679,
    "igdb_id": 1234,
    "my_rating": 9.5,
    "my_review": "测试IGDB游戏",
    "platform": "PS5",
    "status": "played"
  }')

echo "$GAME_RESULT" | jq '.'
GAME_ID=$(echo "$GAME_RESULT" | jq -r '.data.id')

if [ "$(echo "$GAME_RESULT" | jq -r '.success')" = "true" ]; then
    echo "✅ 游戏添加成功 (ID: $GAME_ID)"
else
    echo "❌ 游戏添加失败"
fi
echo ""

# ============================================
# 测试4: 获取游戏列表验证igdb_id
# ============================================
echo "========== 测试4: 验证游戏包含IGDB ID =========="
GAMES_LIST=$(curl -s -X GET "$API_URL/api/library/games" \
  -H "Authorization: Bearer $TOKEN")

echo "$GAMES_LIST" | jq '.data.items[] | select(.id == '$GAME_ID')'

RETURNED_IGDB_ID=$(echo "$GAMES_LIST" | jq -r '.data.items[] | select(.id == '$GAME_ID') | .igdb_id')

if [ "$RETURNED_IGDB_ID" = "1234" ]; then
    echo "✅ IGDB ID正确返回"
else
    echo "❌ IGDB ID未正确返回 (got: $RETURNED_IGDB_ID)"
fi
echo ""

# ============================================
# 测试5: 更新游戏的igdb_id
# ============================================
if [ ! -z "$GAME_ID" ] && [ "$GAME_ID" != "null" ]; then
    echo "========== 测试5: 更新游戏的IGDB ID =========="
    UPDATE_RESULT=$(curl -s -X PUT "$API_URL/api/library/games/$GAME_ID" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "igdb_id": 5678,
        "my_rating": 10.0
      }')
    
    echo "$UPDATE_RESULT" | jq '.'
    
    if [ "$(echo "$UPDATE_RESULT" | jq -r '.success')" = "true" ]; then
        echo "✅ 游戏更新成功"
        
        # 验证更新
        UPDATED_GAME=$(curl -s -X GET "$API_URL/api/library/games/$GAME_ID" \
          -H "Authorization: Bearer $TOKEN")
        
        UPDATED_IGDB_ID=$(echo "$UPDATED_GAME" | jq -r '.data.igdb_id')
        if [ "$UPDATED_IGDB_ID" = "5678" ]; then
            echo "✅ IGDB ID更新成功"
        else
            echo "❌ IGDB ID未更新 (got: $UPDATED_IGDB_ID)"
        fi
    else
        echo "❌ 游戏更新失败"
    fi
fi
echo ""

echo "========================================"
echo "测试完成"
echo "========================================"
echo "游戏ID: $GAME_ID"

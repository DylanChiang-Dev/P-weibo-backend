#!/bin/bash
# Test script for Daily Activities API

API_URL="${API_URL:-https://pyqapi.3331322.xyz}"

echo "========================================"
echo "测试每日打卡API"
echo "========================================"

# Get token
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

# Test 1: Check in exercise
echo "测试1: 运动打卡"
RESPONSE=$(curl -s -X POST "$API_URL/api/activities/checkin" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "activity_type": "exercise",
    "activity_date": "2024-12-03",
    "duration_minutes": 30,
    "intensity": "high",
    "notes": "晨跑5公里"
  }')

echo "$RESPONSE" | jq '.'
if [ "$(echo "$RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 运动打卡成功"
else
    echo "❌ 运动打卡失败"
fi
echo ""

# Test 2: Check in reading
echo "测试2: 阅读打卡"
RESPONSE=$(curl -s -X POST "$API_URL/api/activities/checkin" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "activity_type": "reading",
    "activity_date": "2024-12-03",
    "duration_minutes": 60,
    "pages_read": 50,
    "notes": "阅读《三体》"
  }')

echo "$RESPONSE" | jq '.'
if [ "$(echo "$RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 阅读打卡成功"
else
    echo "❌ 阅读打卡失败"
fi
echo ""

# Test 3: Check in Duolingo
echo "测试3: Duolingo打卡"
RESPONSE=$(curl -s -X POST "$API_URL/api/activities/checkin" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "activity_type": "duolingo",
    "activity_date": "2024-12-03",
    "xp_earned": 50,
    "courses_completed": 3
  }')

echo "$RESPONSE" | jq '.'
if [ "$(echo "$RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ Duolingo打卡成功"
else
    echo "❌ Duolingo打卡失败"
fi
echo ""

# Test 4: Get daily activities
echo "测试4: 获取今日打卡"
RESPONSE=$(curl -s "$API_URL/api/activities/daily?date=2024-12-03" \
  -H "Authorization: Bearer $TOKEN")

echo "$RESPONSE" | jq '.'
COUNT=$(echo "$RESPONSE" | jq '.data | length')
if [ "$COUNT" -eq 3 ]; then
    echo "✅ 获取今日打卡成功（3条记录）"
else
    echo "⚠️  获取今日打卡：$COUNT 条记录"
fi
echo ""

# Test 5: Get heatmap data
echo "测试5: 获取运动热力图数据"
RESPONSE=$(curl -s "$API_URL/api/activities/heatmap?type=exercise&year=2024" \
  -H "Authorization: Bearer $TOKEN")

echo "数据量: $(echo "$RESPONSE" | jq '.data | length') 天"
if [ "$(echo "$RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 获取热力图数据成功"
else
    echo "❌ 获取热力图数据失败"
fi
echo ""

# Test 6: Get statistics
echo "测试6: 获取运动统计"
RESPONSE=$(curl -s "$API_URL/api/activities/stats?type=exercise&year=2024" \
  -H "Authorization: Bearer $TOKEN")

echo "$RESPONSE" | jq '.'
if [ "$(echo "$RESPONSE" | jq -r '.success')" = "true" ]; then
    echo "✅ 获取统计数据成功"
else
    echo "❌ 获取统计数据失败"
fi
echo ""

echo "========================================"
echo "测试完成"
echo "========================================"

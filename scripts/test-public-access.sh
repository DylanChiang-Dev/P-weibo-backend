#!/bin/bash
# 测试公开访问（不带Token）

API_URL="https://pyqapi.3331322.xyz"

echo "========================================" echo "测试公开访问（无需认证）"
echo "========================================"
echo ""

echo "测试1: 获取电影列表（无Token）"
curl -s "$API_URL/api/library/movies" | jq '.'
echo ""

echo "测试2: 获取电视剧列表（无Token）"
curl -s "$API_URL/api/library/tv-shows" | jq '.'
echo ""

echo "测试3: 获取打卡热力图（无Token）"
curl -s "$API_URL/api/activities/heatmap?type=exercise&year=2024" | jq '.'
echo ""

echo "测试4: 获取打卡统计（无Token）"
curl -s "$API_URL/api/activities/stats?type=exercise&year=2024" | jq '.'
echo ""

echo "========================================"
echo "测试写操作保护（应该返回401）"
echo "========================================"
echo ""

echo "测试5: 尝试添加电影（无Token，应该失败）"
WRITE_RESULT=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_URL/api/library/movies" \
  -H "Content-Type: application/json" \
  -d '{"tmdb_id": 12345}')

if [ "$WRITE_RESULT" = "401" ]; then
    echo "✅ 写操作正确受保护（返回401）"
else
    echo "❌ 写操作未受保护（返回$WRITE_RESULT）"
fi
echo ""

echo "========================================"
echo "测试完成"
echo "========================================"

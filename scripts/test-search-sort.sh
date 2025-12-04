#!/bin/bash
# 测试媒体库搜索与排序功能
# Usage: ./scripts/test-search-sort.sh

API_URL="${API_URL:-https://pyqapi.3331322.xyz}"

echo "========================================"
echo "测试媒体库搜索与排序功能"
echo "========================================"
echo ""

# 测试 1: 默认排序
echo "测试1: 默认排序 (date_desc)"
curl -s "$API_URL/api/library/movies?page=1&limit=3" | jq '.data.items | length, .data.pagination'
echo ""

# 测试 2: 评分最高排序
echo "测试2: 评分最高排序 (rating_desc)"
curl -s "$API_URL/api/library/movies?sort=rating_desc&limit=3" | jq '.data.items[0:2] | .[].my_rating'
echo ""

# 测试 3: 评分最低排序
echo "测试3: 评分最低排序 (rating_asc)"
curl -s "$API_URL/api/library/movies?sort=rating_asc&limit=3" | jq '.data.items[0:2] | .[].my_rating'
echo ""

# 测试 4: 最近添加排序
echo "测试4: 最早添加排序 (date_asc)"
curl -s "$API_URL/api/library/movies?sort=date_asc&limit=3" | jq '.data.items[0:2] | .[].created_at'
echo ""

# 测试 5: 最近完成排序
echo "测试5: 最近完成排序 (completed_desc)"
curl -s "$API_URL/api/library/movies?sort=completed_desc&limit=3" | jq '.data.items[0:2] | .[].completed_date'
echo ""

# 测试 6: 搜索功能
echo "测试6: 搜索功能"
# 使用一个可能存在的关键词进行搜索
curl -s "$API_URL/api/library/movies?search=test&limit=5" | jq '.data.pagination.total, .data.items | length'
echo ""

# 测试 7: 组合查询（搜索 + 排序）
echo "测试7: 组合查询 (search + rating_desc)"
curl -s "$API_URL/api/library/movies?search=test&sort=rating_desc&limit=5" | jq '.success, .data.pagination'
echo ""

# 测试 8: 其他媒体类型
echo "测试8: TV Shows 排序功能"
curl -s "$API_URL/api/library/tv-shows?sort=rating_desc&limit=3" | jq '.success, .data.items | length'
echo ""

echo "测试9: Books 排序功能"
curl -s "$API_URL/api/library/books?sort=completed_desc&limit=3" | jq '.success, .data.items | length'
echo ""

echo "测试10: Games 搜索功能"
curl -s "$API_URL/api/library/games?search=test&limit=3" | jq '.success, .data.pagination.total'
echo ""

echo "========================================"
echo "测试完成"
echo "========================================"

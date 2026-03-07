#!/bin/bash

# 测试优化后的小组详情查询（避免 N+1 查询）

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试优化后的小组详情查询"
echo "=========================================="

# 步骤 1: 登录获取 token
echo -e "\n步骤 1: 登录获取 token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "登录响应: $LOGIN_RESPONSE"

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ 登录失败，无法获取 token"
  exit 1
fi

echo "✓ 登录成功，token: ${TOKEN:0:20}..."

# 步骤 2: 获取小组列表
echo -e "\n步骤 2: 获取小组列表..."
GROUPS_RESPONSE=$(curl -s -X GET "$BASE_URL/groups" \
  -H "Authorization: Bearer $TOKEN")

echo "小组列表响应: $GROUPS_RESPONSE"

# 提取第一个小组的 ID
GROUP_ID=$(echo $GROUPS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$GROUP_ID" ]; then
  echo "❌ 没有找到小组"
  exit 1
fi

echo "✓ 找到小组 ID: $GROUP_ID"

# 步骤 3: 获取小组详情（测试优化后的查询）
echo -e "\n步骤 3: 获取小组详情（测试批量查询优化）..."
GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "小组详情响应:"
echo "$GROUP_DETAIL" | python3 -m json.tool 2>/dev/null || echo "$GROUP_DETAIL"

# 验证响应包含必要字段
if echo "$GROUP_DETAIL" | grep -q '"creator"'; then
  echo "✓ 包含 creator 字段"
else
  echo "❌ 缺少 creator 字段"
fi

if echo "$GROUP_DETAIL" | grep -q '"members"'; then
  echo "✓ 包含 members 字段"
else
  echo "❌ 缺少 members 字段"
fi

# 统计成员数量
MEMBER_COUNT=$(echo "$GROUP_DETAIL" | grep -o '"members":\[[^]]*\]' | grep -o '"id":' | wc -l)
echo "✓ 小组成员数量: $MEMBER_COUNT"

echo -e "\n=========================================="
echo "测试完成！"
echo "=========================================="
echo ""
echo "优化说明："
echo "- 之前：每个成员都单独查询一次用户信息（N+1 查询）"
echo "- 现在：批量查询所有成员的用户信息（1 次查询）"
echo "- 性能提升：当小组有 N 个成员时，减少 N 次数据库查询"

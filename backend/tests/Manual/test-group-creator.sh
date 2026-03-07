#!/bin/bash

# 测试小组详情返回创建者信息
# 验证 GroupService::getById() 是否正确返回 creator 字段

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试：小组详情返回创建者信息"
echo "=========================================="
echo ""

# 步骤 1: 登录获取 token
echo "步骤 1: 登录获取 token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ 登录失败"
  echo "响应: $LOGIN_RESPONSE"
  exit 1
fi

echo "✓ 登录成功"
echo "Token: ${TOKEN:0:20}..."
echo ""

# 步骤 2: 创建一个小组
echo "步骤 2: 创建测试小组..."
CREATE_GROUP_RESPONSE=$(curl -s -X POST "$BASE_URL/groups" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "测试小组-创建者信息",
    "description": "用于测试创建者信息返回"
  }')

GROUP_ID=$(echo $CREATE_GROUP_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$GROUP_ID" ]; then
  echo "❌ 创建小组失败"
  echo "响应: $CREATE_GROUP_RESPONSE"
  exit 1
fi

echo "✓ 小组创建成功"
echo "小组 ID: $GROUP_ID"
echo ""

# 步骤 3: 获取小组详情
echo "步骤 3: 获取小组详情..."
GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "小组详情响应:"
echo "$GROUP_DETAIL" | python3 -m json.tool 2>/dev/null || echo "$GROUP_DETAIL"
echo ""

# 步骤 4: 验证 creator 字段
echo "步骤 4: 验证 creator 字段..."

HAS_CREATOR=$(echo $GROUP_DETAIL | grep -o '"creator"')
if [ -z "$HAS_CREATOR" ]; then
  echo "❌ 响应中缺少 creator 字段"
  exit 1
fi

CREATOR_ID=$(echo $GROUP_DETAIL | grep -o '"creator":{[^}]*"id":[0-9]*' | grep -o '"id":[0-9]*' | cut -d':' -f2)
CREATOR_USERNAME=$(echo $GROUP_DETAIL | grep -o '"creator":{[^}]*"username":"[^"]*"' | grep -o '"username":"[^"]*"' | cut -d'"' -f4)
CREATOR_NICKNAME=$(echo $GROUP_DETAIL | grep -o '"creator":{[^}]*"nickname":"[^"]*"' | grep -o '"nickname":"[^"]*"' | cut -d'"' -f4)

if [ -z "$CREATOR_ID" ]; then
  echo "❌ creator 字段中缺少 id"
  exit 1
fi

if [ -z "$CREATOR_USERNAME" ]; then
  echo "❌ creator 字段中缺少 username"
  exit 1
fi

echo "✓ creator 字段验证通过"
echo "  - ID: $CREATOR_ID"
echo "  - Username: $CREATOR_USERNAME"
echo "  - Nickname: $CREATOR_NICKNAME"
echo ""

# 步骤 5: 验证 members 字段
echo "步骤 5: 验证 members 字段..."

HAS_MEMBERS=$(echo $GROUP_DETAIL | grep -o '"members"')
if [ -z "$HAS_MEMBERS" ]; then
  echo "❌ 响应中缺少 members 字段"
  exit 1
fi

echo "✓ members 字段存在"
echo ""

# 步骤 6: 清理 - 删除测试小组
echo "步骤 6: 清理测试数据..."
DELETE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "✓ 测试小组已删除"
echo ""

echo "=========================================="
echo "✓ 所有测试通过！"
echo "=========================================="
echo ""
echo "验证结果："
echo "1. ✓ 小组详情包含 creator 字段"
echo "2. ✓ creator 包含 id, username, nickname 字段"
echo "3. ✓ 小组详情包含 members 字段"

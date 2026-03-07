#!/bin/bash

# 测试小组详情 API 是否返回创建者信息
# Task 9.1: 验证后端返回 creator 字段

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试小组详情 API - 验证创建者信息"
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

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
  echo "❌ 登录失败"
  echo "响应: $LOGIN_RESPONSE"
  exit 1
fi

echo "✅ 登录成功"
echo "Token: ${TOKEN:0:20}..."
echo ""

# 步骤 2: 获取小组列表
echo "步骤 2: 获取小组列表..."
GROUPS_RESPONSE=$(curl -s -X GET "$BASE_URL/groups" \
  -H "Authorization: Bearer $TOKEN")

echo "小组列表响应:"
echo $GROUPS_RESPONSE | jq '.'
echo ""

# 提取第一个小组的 ID
GROUP_ID=$(echo $GROUPS_RESPONSE | jq -r '.data.items[0].id // empty')

if [ -z "$GROUP_ID" ]; then
  echo "❌ 没有找到小组，请先创建小组"
  exit 1
fi

echo "使用小组 ID: $GROUP_ID"
echo ""

# 步骤 3: 获取小组详情
echo "步骤 3: 获取小组详情（验证 creator 字段）..."
GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "小组详情响应:"
echo $GROUP_DETAIL | jq '.'
echo ""

# 提取 data 字段
GROUP_DATA=$(echo $GROUP_DETAIL | jq '.data')

# 步骤 4: 验证 creator 字段
echo "步骤 4: 验证响应结构..."
echo ""

HAS_CREATOR=$(echo $GROUP_DATA | jq 'has("creator")')
HAS_MEMBERS=$(echo $GROUP_DATA | jq 'has("members")')

if [ "$HAS_CREATOR" = "true" ]; then
  echo "✅ 响应包含 creator 字段"
  
  CREATOR_ID=$(echo $GROUP_DATA | jq -r '.creator.id // empty')
  CREATOR_USERNAME=$(echo $GROUP_DATA | jq -r '.creator.username // empty')
  CREATOR_NICKNAME=$(echo $GROUP_DATA | jq -r '.creator.nickname // empty')
  CREATOR_AVATAR=$(echo $GROUP_DATA | jq -r '.creator.avatar // empty')
  
  echo "  - ID: $CREATOR_ID"
  echo "  - 用户名: $CREATOR_USERNAME"
  echo "  - 昵称: $CREATOR_NICKNAME"
  echo "  - 头像: $CREATOR_AVATAR"
else
  echo "❌ 响应不包含 creator 字段"
fi

echo ""

if [ "$HAS_MEMBERS" = "true" ]; then
  MEMBER_COUNT=$(echo $GROUP_DATA | jq '.members | length')
  echo "✅ 响应包含 members 字段 (共 $MEMBER_COUNT 个成员)"
  
  if [ "$MEMBER_COUNT" -gt 0 ]; then
    echo ""
    echo "成员列表:"
    echo $GROUP_DATA | jq '.members[] | {id, username, nickname}'
  fi
else
  echo "❌ 响应不包含 members 字段"
fi

echo ""
echo "=========================================="
echo "测试完成"
echo "=========================================="

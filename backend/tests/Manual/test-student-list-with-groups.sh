#!/bin/bash

# 测试学生列表是否包含小组信息
# 验证 Task 8.2: 更新学生列表页面显示关联小组

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试学生列表 API 返回小组信息"
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

# 步骤 2: 获取学生列表
echo "步骤 2: 获取学生列表..."
STUDENTS_RESPONSE=$(curl -s -X GET "$BASE_URL/students?page=1&pageSize=10" \
  -H "Authorization: Bearer $TOKEN")

echo "响应:"
echo "$STUDENTS_RESPONSE" | jq '.'
echo ""

# 步骤 3: 检查是否包含 groups 字段
echo "步骤 3: 验证响应格式..."

# 检查是否有学生数据
STUDENT_COUNT=$(echo "$STUDENTS_RESPONSE" | jq '.data.items | length')
echo "学生数量: $STUDENT_COUNT"

if [ "$STUDENT_COUNT" -gt 0 ]; then
  # 检查第一个学生是否有 groups 字段
  HAS_GROUPS=$(echo "$STUDENTS_RESPONSE" | jq '.data.items[0] | has("groups")')
  
  if [ "$HAS_GROUPS" = "true" ]; then
    echo "✓ 学生数据包含 groups 字段"
    
    # 显示第一个学生的小组信息
    FIRST_STUDENT=$(echo "$STUDENTS_RESPONSE" | jq '.data.items[0]')
    STUDENT_NAME=$(echo "$FIRST_STUDENT" | jq -r '.user.nickname // "未知"')
    GROUPS=$(echo "$FIRST_STUDENT" | jq -r '.groups')
    GROUP_COUNT=$(echo "$FIRST_STUDENT" | jq '.groups | length')
    
    echo ""
    echo "示例学生: $STUDENT_NAME"
    echo "所属小组数量: $GROUP_COUNT"
    
    if [ "$GROUP_COUNT" -gt 0 ]; then
      echo "小组列表:"
      echo "$GROUPS" | jq -r '.[] | "  - [\(.id)] \(.name)"'
      echo ""
      echo "✓ 学生成功关联到小组"
    else
      echo "⚠ 该学生未加入任何小组"
    fi
  else
    echo "❌ 学生数据不包含 groups 字段"
    echo "第一个学生的数据结构:"
    echo "$STUDENTS_RESPONSE" | jq '.data.items[0]'
    exit 1
  fi
else
  echo "⚠ 没有学生数据，无法验证"
fi

echo ""
echo "=========================================="
echo "测试完成"
echo "=========================================="

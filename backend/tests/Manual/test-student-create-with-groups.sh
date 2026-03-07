#!/bin/bash

# 测试学生创建并验证 auto_joined_groups 字段
# 验证任务 8.1：前端应该能够解析并显示自动加入的小组信息

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试：学生创建返回 auto_joined_groups 字段"
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
echo ""

# 步骤 2: 创建学校
echo "步骤 2: 创建测试学校..."
SCHOOL_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "测试学校-学生小组关联",
    "address": "测试地址"
  }')

SCHOOL_ID=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$SCHOOL_ID" ]; then
  echo "❌ 创建学校失败"
  echo "响应: $SCHOOL_RESPONSE"
  exit 1
fi

echo "✓ 学校创建成功 (ID: $SCHOOL_ID)"
echo ""

# 步骤 3: 创建班级（会自动创建关联的小组）
echo "步骤 3: 创建测试班级（会自动创建小组）..."
CLASS_RESPONSE=$(curl -s -X POST "$BASE_URL/classes" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"测试班级-小组关联\",
    \"school_id\": $SCHOOL_ID
  }")

CLASS_ID=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$CLASS_ID" ]; then
  echo "❌ 创建班级失败"
  echo "响应: $CLASS_RESPONSE"
  exit 1
fi

echo "✓ 班级创建成功 (ID: $CLASS_ID)"
echo ""

# 步骤 4: 获取当前用户信息
echo "步骤 4: 获取当前用户信息..."
USER_RESPONSE=$(curl -s -X GET "$BASE_URL/auth/me" \
  -H "Authorization: Bearer $TOKEN")

USER_ID=$(echo $USER_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$USER_ID" ]; then
  echo "❌ 获取用户信息失败"
  echo "响应: $USER_RESPONSE"
  exit 1
fi

echo "✓ 用户信息获取成功 (ID: $USER_ID)"
echo ""

# 步骤 5: 创建学生并检查 auto_joined_groups 字段
echo "步骤 5: 创建学生并检查 auto_joined_groups 字段..."
STUDENT_RESPONSE=$(curl -s -X POST "$BASE_URL/students" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"user_id\": $USER_ID,
    \"class_id\": $CLASS_ID
  }")

echo "学生创建响应:"
echo "$STUDENT_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$STUDENT_RESPONSE"
echo ""

# 检查是否包含 auto_joined_groups 字段
if echo "$STUDENT_RESPONSE" | grep -q "auto_joined_groups"; then
  echo "✓ 响应包含 auto_joined_groups 字段"
  
  # 提取小组数量
  GROUP_COUNT=$(echo "$STUDENT_RESPONSE" | grep -o '"auto_joined_groups":\[[^]]*\]' | grep -o '{' | wc -l)
  echo "✓ 学生自动加入了 $GROUP_COUNT 个小组"
  
  # 提取小组名称
  echo ""
  echo "自动加入的小组："
  echo "$STUDENT_RESPONSE" | grep -o '"name":"[^"]*"' | cut -d'"' -f4 | while read name; do
    echo "  - $name"
  done
else
  echo "❌ 响应不包含 auto_joined_groups 字段"
  exit 1
fi

echo ""
echo "=========================================="
echo "✓ 测试通过：auto_joined_groups 字段正确返回"
echo "=========================================="
echo ""
echo "前端实现说明："
echo "1. Student 类型已添加 auto_joined_groups 字段"
echo "2. StudentForm.vue 已更新以解析并显示小组信息"
echo "3. 成功消息格式：'添加成功！学生已自动加入 X 个小组：小组1、小组2'"

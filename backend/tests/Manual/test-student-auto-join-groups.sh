#!/bin/bash

# 测试学生加入班级时自动加入小组功能
# 测试 API: POST /api/students

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试：学生加入班级自动加入小组"
echo "=========================================="
echo ""

# 步骤 1: 登录获取 token
echo "步骤 1: 登录获取 JWT token..."
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

echo "✓ 登录成功，获取到 token"
echo ""

# 步骤 2: 创建测试班级
echo "步骤 2: 创建测试班级..."
CLASS_RESPONSE=$(curl -s -X POST "$BASE_URL/classes" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "测试班级-自动加入小组",
    "school_id": 18
  }')

CLASS_ID=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$CLASS_ID" ]; then
  echo "❌ 创建班级失败"
  echo "响应: $CLASS_RESPONSE"
  exit 1
fi

echo "✓ 班级创建成功，ID: $CLASS_ID"
echo ""

# 步骤 3: 查询班级关联的小组
echo "步骤 3: 查询班级关联的小组..."
sleep 1
CLASS_DETAIL=$(curl -s -X GET "$BASE_URL/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "班级详情: $CLASS_DETAIL"
echo ""

# 步骤 4: 查找一个可用的用户（不是学生的用户）
echo "步骤 4: 查找可用用户..."

# 使用当前登录用户的ID
CURRENT_USER=$(echo $LOGIN_RESPONSE | grep -o '"user_id":[0-9]*' | cut -d':' -f2)
if [ -z "$CURRENT_USER" ]; then
  CURRENT_USER=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
fi

# 如果当前用户已经是学生，使用另一个测试用户ID
TEST_USER_ID=${CURRENT_USER:-25}

echo "使用测试用户 ID: $TEST_USER_ID"
echo ""

# 步骤 5: 添加学生到班级
echo "步骤 5: 添加学生到班级（应自动加入小组）..."
STUDENT_RESPONSE=$(curl -s -X POST "$BASE_URL/students" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"user_id\": $TEST_USER_ID,
    \"class_id\": $CLASS_ID
  }")

echo "学生创建响应:"
echo "$STUDENT_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$STUDENT_RESPONSE"
echo ""

# 检查响应中是否包含 auto_joined_groups 字段
if echo "$STUDENT_RESPONSE" | grep -q "auto_joined_groups"; then
  echo "✓ 响应包含 auto_joined_groups 字段"
  
  # 提取自动加入的小组数量
  GROUP_COUNT=$(echo "$STUDENT_RESPONSE" | grep -o '"auto_joined_groups":\[[^]]*\]' | grep -o '{' | wc -l)
  echo "✓ 学生自动加入了 $GROUP_COUNT 个小组"
else
  echo "❌ 响应中没有 auto_joined_groups 字段"
fi

STUDENT_ID=$(echo $STUDENT_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

echo ""
echo "=========================================="
echo "测试完成"
echo "=========================================="
echo "班级 ID: $CLASS_ID"
echo "学生 ID: $STUDENT_ID"
echo ""
echo "清理提示: 可以手动删除测试数据"
echo "  DELETE $BASE_URL/students/$STUDENT_ID"
echo "  DELETE $BASE_URL/classes/$CLASS_ID"

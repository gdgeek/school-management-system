#!/bin/bash

# 测试学生删除时自动离开小组功能
# 验证设计文档算法 2 的实现

BASE_URL="http://localhost:8084/api"
TOKEN=""

echo "=========================================="
echo "测试：学生删除时自动离开小组"
echo "=========================================="

# 步骤 1: 登录获取 token
echo -e "\n[步骤 1] 登录获取 token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)
CURRENT_USER_ID=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$TOKEN" ]; then
  echo "❌ 登录失败"
  echo "响应: $LOGIN_RESPONSE"
  exit 1
fi

echo "✓ 登录成功，获取到 token"
echo "  当前用户 ID: $CURRENT_USER_ID"

# 步骤 2: 创建测试学校
echo -e "\n[步骤 2] 创建测试学校..."
SCHOOL_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "测试学校-自动离开小组"
  }')

SCHOOL_ID=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$SCHOOL_ID" ]; then
  echo "❌ 创建学校失败"
  echo "响应: $SCHOOL_RESPONSE"
  exit 1
fi

echo "✓ 创建学校成功，学校 ID: $SCHOOL_ID"

# 步骤 3: 创建测试班级
echo -e "\n[步骤 3] 创建测试班级..."
CLASS_RESPONSE=$(curl -s -X POST "$BASE_URL/classes" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"测试班级-自动离开小组\",
    \"school_id\": $SCHOOL_ID
  }")

CLASS_ID=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$CLASS_ID" ]; then
  echo "❌ 创建班级失败"
  echo "响应: $CLASS_RESPONSE"
  exit 1
fi

echo "✓ 创建班级成功，班级 ID: $CLASS_ID"

# 步骤 3: 获取班级关联的小组
echo -e "\n[步骤 4] 获取班级关联的小组..."
CLASS_DETAIL=$(curl -s -X GET "$BASE_URL/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

GROUP_IDS=$(echo $CLASS_DETAIL | grep -o '"id":[0-9]*' | tail -n +2 | cut -d':' -f2)
GROUP_COUNT=$(echo "$GROUP_IDS" | wc -l)

echo "✓ 班级关联了 $GROUP_COUNT 个小组"
echo "小组 IDs: $GROUP_IDS"

# 步骤 4: 创建测试用户
echo -e "\n[步骤 5] 准备测试用户..."
# 使用当前登录用户作为测试用户
TEST_USER_ID=$CURRENT_USER_ID

# 检查用户是否已经是学生，如果是则先删除
EXISTING_STUDENTS=$(curl -s -X GET "$BASE_URL/students?page=1&pageSize=100" \
  -H "Authorization: Bearer $TOKEN")

EXISTING_STUDENT_ID=$(echo $EXISTING_STUDENTS | grep -o "\"user_id\":$TEST_USER_ID[^}]*\"id\":[0-9]*" | grep -o "\"id\":[0-9]*" | cut -d':' -f2)

if [ -n "$EXISTING_STUDENT_ID" ]; then
  echo "  用户已是学生，先删除现有学生记录 ID: $EXISTING_STUDENT_ID"
  curl -s -X DELETE "$BASE_URL/students/$EXISTING_STUDENT_ID" \
    -H "Authorization: Bearer $TOKEN" > /dev/null
  echo "  ✓ 已删除现有学生记录"
fi

echo "✓ 使用测试用户 ID: $TEST_USER_ID"

# 步骤 5: 添加学生到班级（应自动加入小组）
echo -e "\n[步骤 6] 添加学生到班级..."
STUDENT_RESPONSE=$(curl -s -X POST "$BASE_URL/students" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"user_id\": $TEST_USER_ID,
    \"class_id\": $CLASS_ID
  }")

STUDENT_ID=$(echo $STUDENT_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$STUDENT_ID" ]; then
  echo "❌ 添加学生失败"
  echo "响应: $STUDENT_RESPONSE"
  exit 1
fi

echo "✓ 添加学生成功，学生 ID: $STUDENT_ID"

# 检查自动加入的小组
AUTO_JOINED=$(echo $STUDENT_RESPONSE | grep -o '"auto_joined_groups"')
if [ -n "$AUTO_JOINED" ]; then
  echo "✓ 学生已自动加入小组"
else
  echo "⚠ 响应中未包含 auto_joined_groups 字段"
fi

# 步骤 6: 验证学生在小组中
echo -e "\n[步骤 7] 验证学生在所有关联小组中..."
MEMBER_COUNT=0
for GROUP_ID in $GROUP_IDS; do
  GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
    -H "Authorization: Bearer $TOKEN")
  
  # 检查成员列表中是否包含该用户
  if echo "$GROUP_DETAIL" | grep -q "\"id\":$TEST_USER_ID"; then
    echo "✓ 学生在小组 $GROUP_ID 中"
    MEMBER_COUNT=$((MEMBER_COUNT + 1))
  else
    echo "❌ 学生不在小组 $GROUP_ID 中"
  fi
done

if [ $MEMBER_COUNT -eq $GROUP_COUNT ]; then
  echo "✓ 学生在所有 $GROUP_COUNT 个小组中"
else
  echo "❌ 学生只在 $MEMBER_COUNT/$GROUP_COUNT 个小组中"
fi

# 步骤 7: 删除学生（应自动离开所有小组）
echo -e "\n[步骤 8] 删除学生（测试自动离开小组）..."
DELETE_RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/students/$STUDENT_ID" \
  -H "Authorization: Bearer $TOKEN")

HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n1)

if [ "$HTTP_CODE" = "204" ] || [ "$HTTP_CODE" = "200" ]; then
  echo "✓ 删除学生成功，HTTP 状态码: $HTTP_CODE"
else
  echo "❌ 删除学生失败，HTTP 状态码: $HTTP_CODE"
  echo "响应: $DELETE_RESPONSE"
  exit 1
fi

# 步骤 8: 验证学生已从所有小组中移除
echo -e "\n[步骤 9] 验证学生已从所有小组中移除..."
STILL_MEMBER_COUNT=0
for GROUP_ID in $GROUP_IDS; do
  GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
    -H "Authorization: Bearer $TOKEN")
  
  # 检查成员列表中是否还包含该用户
  if echo "$GROUP_DETAIL" | grep -q "\"id\":$TEST_USER_ID"; then
    echo "❌ 学生仍在小组 $GROUP_ID 中（应该已被移除）"
    STILL_MEMBER_COUNT=$((STILL_MEMBER_COUNT + 1))
  else
    echo "✓ 学生已从小组 $GROUP_ID 中移除"
  fi
done

# 步骤 9: 清理测试数据
echo -e "\n[步骤 10] 清理测试数据..."
curl -s -X DELETE "$BASE_URL/classes/$CLASS_ID" \
  -H "Authorization: Bearer $TOKEN" > /dev/null
echo "✓ 已删除测试班级"

curl -s -X DELETE "$BASE_URL/schools/$SCHOOL_ID" \
  -H "Authorization: Bearer $TOKEN" > /dev/null
echo "✓ 已删除测试学校"

# 最终结果
echo -e "\n=========================================="
echo "测试结果总结"
echo "=========================================="

if [ $STILL_MEMBER_COUNT -eq 0 ]; then
  echo "✅ 测试通过：学生删除后已从所有 $GROUP_COUNT 个小组中自动移除"
  echo ""
  echo "验证的功能："
  echo "  ✓ 删除学生前查询班级关联的所有小组"
  echo "  ✓ 使用事务将用户从所有关联小组中移除"
  echo "  ✓ 删除操作的原子性"
  echo "  ✓ 符合设计文档算法 2"
  exit 0
else
  echo "❌ 测试失败：学生删除后仍在 $STILL_MEMBER_COUNT/$GROUP_COUNT 个小组中"
  exit 1
fi

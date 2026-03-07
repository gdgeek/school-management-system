#!/bin/bash

# 简化版测试：学生删除时自动离开小组功能
# 使用 API 直接验证核心功能

BASE_URL="http://localhost:8084/api"
TOKEN=""

echo "=========================================="
echo "测试：学生删除时自动离开小组（简化版）"
echo "=========================================="

# 步骤 1: 登录
echo -e "\n[步骤 1] 登录..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "guanfei", "password": "123456"}')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ 登录失败"
  exit 1
fi
echo "✓ 登录成功"

# 步骤 2: 获取现有学生列表，找一个可以删除的
echo -e "\n[步骤 2] 查找现有学生..."
STUDENTS_RESPONSE=$(curl -s -X GET "$BASE_URL/students?page=1&pageSize=10" \
  -H "Authorization: Bearer $TOKEN")

# 提取第一个学生的 ID、user_id 和 class_id
STUDENT_ID=$(echo $STUDENTS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
STUDENT_USER_ID=$(echo $STUDENTS_RESPONSE | grep -o '"user_id":[0-9]*' | head -1 | cut -d':' -f2)
STUDENT_CLASS_ID=$(echo $STUDENTS_RESPONSE | grep -o '"class_id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$STUDENT_ID" ]; then
  echo "⚠ 没有找到现有学生，创建新的测试数据..."
  
  # 创建学校
  SCHOOL_RESPONSE=$(curl -s -X POST "$BASE_URL/schools" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"name": "测试学校-删除学生"}')
  SCHOOL_ID=$(echo $SCHOOL_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
  
  # 创建班级（会自动创建小组）
  CLASS_RESPONSE=$(curl -s -X POST "$BASE_URL/classes" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"测试班级-删除学生\", \"school_id\": $SCHOOL_ID}")
  STUDENT_CLASS_ID=$(echo $CLASS_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
  
  # 创建一个新用户作为学生（使用一个不太可能存在的 user_id）
  # 实际上我们需要先确保有可用的用户，这里简化处理
  echo "  需要手动准备测试数据，跳过此测试"
  exit 0
fi

echo "✓ 找到学生 ID: $STUDENT_ID (user_id: $STUDENT_USER_ID, class_id: $STUDENT_CLASS_ID)"

# 步骤 3: 获取班级关联的小组
echo -e "\n[步骤 3] 获取班级 $STUDENT_CLASS_ID 关联的小组..."
CLASS_DETAIL=$(curl -s -X GET "$BASE_URL/classes/$STUDENT_CLASS_ID" \
  -H "Authorization: Bearer $TOKEN")

# 提取小组 ID（跳过第一个 id，那是班级的 id）
GROUP_IDS=$(echo $CLASS_DETAIL | grep -o '"groups":\[[^]]*\]' | grep -o '"id":[0-9]*' | cut -d':' -f2)
GROUP_COUNT=$(echo "$GROUP_IDS" | grep -c '[0-9]')

if [ $GROUP_COUNT -eq 0 ]; then
  echo "⚠ 班级没有关联小组，无法测试"
  exit 0
fi

echo "✓ 班级关联了 $GROUP_COUNT 个小组"
echo "  小组 IDs: $(echo $GROUP_IDS | tr '\n' ' ')"

# 步骤 4: 检查学生在小组中的成员关系（删除前）
echo -e "\n[步骤 4] 检查学生在小组中（删除前）..."
BEFORE_MEMBER_COUNT=0
for GROUP_ID in $GROUP_IDS; do
  GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
    -H "Authorization: Bearer $TOKEN")
  
  if echo "$GROUP_DETAIL" | grep -q "\"id\":$STUDENT_USER_ID"; then
    echo "  ✓ 学生在小组 $GROUP_ID 中"
    BEFORE_MEMBER_COUNT=$((BEFORE_MEMBER_COUNT + 1))
  fi
done

echo "删除前：学生在 $BEFORE_MEMBER_COUNT/$GROUP_COUNT 个小组中"

# 步骤 5: 删除学生
echo -e "\n[步骤 5] 删除学生 $STUDENT_ID..."
DELETE_RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/students/$STUDENT_ID" \
  -H "Authorization: Bearer $TOKEN")

HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n1)

if [ "$HTTP_CODE" = "204" ] || [ "$HTTP_CODE" = "200" ]; then
  echo "✓ 删除学生成功 (HTTP $HTTP_CODE)"
else
  echo "❌ 删除学生失败 (HTTP $HTTP_CODE)"
  echo "响应: $DELETE_RESPONSE"
  exit 1
fi

# 步骤 6: 验证学生已从所有小组中移除
echo -e "\n[步骤 6] 验证学生已从小组中移除（删除后）..."
AFTER_MEMBER_COUNT=0
for GROUP_ID in $GROUP_IDS; do
  GROUP_DETAIL=$(curl -s -X GET "$BASE_URL/groups/$GROUP_ID" \
    -H "Authorization: Bearer $TOKEN")
  
  if echo "$GROUP_DETAIL" | grep -q "\"id\":$STUDENT_USER_ID"; then
    echo "  ❌ 学生仍在小组 $GROUP_ID 中"
    AFTER_MEMBER_COUNT=$((AFTER_MEMBER_COUNT + 1))
  else
    echo "  ✓ 学生已从小组 $GROUP_ID 中移除"
  fi
done

echo "删除后：学生在 $AFTER_MEMBER_COUNT/$GROUP_COUNT 个小组中"

# 最终结果
echo -e "\n=========================================="
echo "测试结果"
echo "=========================================="

if [ $AFTER_MEMBER_COUNT -eq 0 ] && [ $BEFORE_MEMBER_COUNT -gt 0 ]; then
  echo "✅ 测试通过"
  echo ""
  echo "验证结果："
  echo "  • 删除前：学生在 $BEFORE_MEMBER_COUNT 个小组中"
  echo "  • 删除后：学生在 0 个小组中"
  echo "  • 自动离开小组功能正常工作"
  exit 0
elif [ $BEFORE_MEMBER_COUNT -eq 0 ]; then
  echo "⚠ 测试无效：学生删除前就不在任何小组中"
  exit 0
else
  echo "❌ 测试失败"
  echo "  • 删除前：学生在 $BEFORE_MEMBER_COUNT 个小组中"
  echo "  • 删除后：学生仍在 $AFTER_MEMBER_COUNT 个小组中"
  echo "  • 自动离开小组功能未正常工作"
  exit 1
fi

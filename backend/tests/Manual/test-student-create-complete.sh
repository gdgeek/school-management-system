#!/bin/bash

# 完整测试学生创建路由
# 包括认证、授权、输入验证和成功创建

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "完整测试学生创建路由"
echo "=========================================="
echo ""

# 步骤 1: 登录获取 token
echo "步骤 1: 登录获取 token"
LOGIN_RESPONSE=$(curl -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "guanfei", "password": "123456"}' \
  -s)

echo "$LOGIN_RESPONSE" | jq '.'
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')
ROLES=$(echo "$LOGIN_RESPONSE" | jq -r '.data.user.roles // []')

if [ -z "$TOKEN" ]; then
  echo "错误: 无法获取 token"
  exit 1
fi

echo "Token 获取成功"
echo "用户角色: $ROLES"
echo ""
echo "----------------------------------------"
echo ""

# 步骤 2: 测试未认证请求
echo "步骤 2: 测试未认证请求（无 token）"
echo "预期: 401 Unauthorized"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "class_id": 1}' \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 步骤 3: 测试输入验证 - 缺少 user_id
echo "步骤 3: 测试输入验证 - 缺少 user_id"
echo "预期: 400 Bad Request 或 403 Forbidden（如果用户无权限）"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"class_id": 1}' \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 步骤 4: 测试输入验证 - 缺少 class_id
echo "步骤 4: 测试输入验证 - 缺少 class_id"
echo "预期: 400 Bad Request 或 403 Forbidden（如果用户无权限）"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id": 1}' \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 步骤 5: 测试输入验证 - user_id 不是数字
echo "步骤 5: 测试输入验证 - user_id 不是数字"
echo "预期: 400 Bad Request 或 403 Forbidden（如果用户无权限）"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id": "abc", "class_id": 1}' \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 检查用户是否有教师或管理员角色
HAS_PERMISSION=$(echo "$ROLES" | grep -E "teacher|admin" | wc -l)

if [ "$HAS_PERMISSION" -eq 0 ]; then
  echo "注意: 当前用户没有 teacher 或 admin 角色"
  echo "无法测试成功创建学生的场景"
  echo "需要使用有教师或管理员角色的账号"
  echo ""
  echo "测试完成（部分）"
  exit 0
fi

echo "用户有权限，继续测试创建学生..."
echo ""

# 步骤 6: 查找一个可用的班级
echo "步骤 6: 查找可用的班级"
CLASSES_RESPONSE=$(curl -X GET "$BASE_URL/classes?page=1&pageSize=1" \
  -H "Authorization: Bearer $TOKEN" \
  -s)

echo "$CLASSES_RESPONSE" | jq '.'
CLASS_ID=$(echo "$CLASSES_RESPONSE" | jq -r '.data.items[0].id // empty')

if [ -z "$CLASS_ID" ]; then
  echo "错误: 没有找到可用的班级"
  exit 1
fi

echo "找到班级 ID: $CLASS_ID"
echo ""
echo "----------------------------------------"
echo ""

# 步骤 7: 查找一个不是学生的用户
echo "步骤 7: 查找可用的用户（不是学生）"
USERS_RESPONSE=$(curl -X GET "$BASE_URL/users/search?keyword=test&limit=10" \
  -H "Authorization: Bearer $TOKEN" \
  -s)

echo "$USERS_RESPONSE" | jq '.'
USER_ID=$(echo "$USERS_RESPONSE" | jq -r '.data[0].id // empty')

if [ -z "$USER_ID" ]; then
  echo "警告: 没有找到测试用户，使用默认 user_id=999"
  USER_ID=999
fi

echo "使用用户 ID: $USER_ID"
echo ""
echo "----------------------------------------"
echo ""

# 步骤 8: 创建学生
echo "步骤 8: 创建学生"
echo "预期: 201 Created，包含 auto_joined_groups 字段"
CREATE_RESPONSE=$(curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"user_id\": $USER_ID, \"class_id\": $CLASS_ID}" \
  -s)

echo "$CREATE_RESPONSE" | jq '.'

# 检查是否包含 auto_joined_groups
HAS_AUTO_JOINED=$(echo "$CREATE_RESPONSE" | jq '.data.auto_joined_groups // empty')
if [ -n "$HAS_AUTO_JOINED" ]; then
  echo ""
  echo "✅ 成功: 响应包含 auto_joined_groups 字段"
  echo "自动加入的小组:"
  echo "$CREATE_RESPONSE" | jq '.data.auto_joined_groups'
else
  echo ""
  echo "⚠️  警告: 响应不包含 auto_joined_groups 字段"
fi

STUDENT_ID=$(echo "$CREATE_RESPONSE" | jq -r '.data.id // empty')

echo ""
echo "----------------------------------------"
echo ""

# 步骤 9: 清理 - 删除创建的学生
if [ -n "$STUDENT_ID" ]; then
  echo "步骤 9: 清理 - 删除创建的学生 (ID: $STUDENT_ID)"
  curl -X DELETE "$BASE_URL/students/$STUDENT_ID" \
    -H "Authorization: Bearer $TOKEN" \
    -s | jq '.'
  echo ""
fi

echo "=========================================="
echo "测试完成"
echo "=========================================="

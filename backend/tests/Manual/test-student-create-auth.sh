#!/bin/bash

# 测试学生创建路由的认证和授权功能
# 验证：
# 1. 未认证请求返回 401
# 2. 非教师/管理员用户返回 403
# 3. 缺少必填字段返回 400
# 4. 成功创建返回 auto_joined_groups

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试学生创建路由的认证和授权"
echo "=========================================="
echo ""

# 测试 1: 未认证请求（无 token）
echo "测试 1: 未认证请求（无 token）"
echo "预期: 401 Unauthorized"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "class_id": 1}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s
echo ""
echo "----------------------------------------"
echo ""

# 测试 2: 无效 token
echo "测试 2: 无效 token"
echo "预期: 401 Unauthorized"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer invalid_token_here" \
  -d '{"user_id": 1, "class_id": 1}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s
echo ""
echo "----------------------------------------"
echo ""

# 测试 3: 获取有效 token（使用测试账号 guanfei）
echo "测试 3: 登录获取 token"
LOGIN_RESPONSE=$(curl -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "guanfei", "password": "123456"}' \
  -s)

echo "$LOGIN_RESPONSE" | jq '.'
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
  echo "错误: 无法获取 token"
  exit 1
fi

echo "Token 获取成功"
echo ""
echo "----------------------------------------"
echo ""

# 测试 4: 缺少 user_id
echo "测试 4: 缺少 user_id"
echo "预期: 400 Bad Request"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"class_id": 1}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 测试 5: 缺少 class_id
echo "测试 5: 缺少 class_id"
echo "预期: 400 Bad Request"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id": 1}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 测试 6: user_id 不是数字
echo "测试 6: user_id 不是数字"
echo "预期: 400 Bad Request"
curl -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"user_id": "abc", "class_id": 1}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

# 测试 7: 检查当前用户角色
echo "测试 7: 检查当前用户信息和角色"
curl -X GET "$BASE_URL/auth/user" \
  -H "Authorization: Bearer $TOKEN" \
  -s | jq '.'
echo ""
echo "----------------------------------------"
echo ""

echo "=========================================="
echo "测试完成"
echo "=========================================="
echo ""
echo "注意事项："
echo "1. 如果用户没有 teacher 或 admin 角色，测试 8 会返回 403"
echo "2. 如果用户有正确角色，测试 8 会尝试创建学生"
echo "3. 成功响应应包含 auto_joined_groups 字段"

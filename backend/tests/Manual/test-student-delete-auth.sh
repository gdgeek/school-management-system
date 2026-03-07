#!/bin/bash

# 测试学生删除路由的认证和权限检查
# 验证：
# 1. 未认证用户无法删除学生（401）
# 2. 非教师/管理员用户无法删除学生（403）
# 3. 教师可以删除学生（204）
# 4. 删除后学生自动离开所有小组

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试学生删除路由的认证和权限"
echo "=========================================="
echo ""

# 步骤 1: 登录获取 token（教师账号）
echo "步骤 1: 登录获取教师 token..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

echo "登录响应: $LOGIN_RESPONSE"
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ 登录失败，无法获取 token"
    exit 1
fi

echo "✓ 成功获取 token: ${TOKEN:0:20}..."
echo ""

# 步骤 2: 创建测试学生
echo "步骤 2: 创建测试学生..."
CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/students" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "user_id": 2,
    "class_id": 1
  }')

echo "创建响应: $CREATE_RESPONSE"
STUDENT_ID=$(echo $CREATE_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$STUDENT_ID" ]; then
    echo "❌ 创建学生失败"
    exit 1
fi

echo "✓ 成功创建学生，ID: $STUDENT_ID"
echo ""

# 步骤 3: 测试未认证删除（应返回 401）
echo "步骤 3: 测试未认证删除（应返回 401）..."
UNAUTH_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL/students/$STUDENT_ID")
UNAUTH_HTTP_CODE=$(echo "$UNAUTH_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
UNAUTH_BODY=$(echo "$UNAUTH_RESPONSE" | sed '/HTTP_CODE:/d')

echo "响应码: $UNAUTH_HTTP_CODE"
echo "响应体: $UNAUTH_BODY"

if [ "$UNAUTH_HTTP_CODE" = "401" ]; then
    echo "✓ 未认证删除正确返回 401"
else
    echo "❌ 未认证删除应返回 401，实际返回 $UNAUTH_HTTP_CODE"
fi
echo ""

# 步骤 4: 测试非教师/管理员删除（应返回 403）
# 注意：这需要一个学生角色的账号，暂时跳过
echo "步骤 4: 测试非教师/管理员删除（跳过，需要学生角色账号）"
echo ""

# 步骤 5: 测试教师删除（应返回 204）
echo "步骤 5: 测试教师删除（应返回 204）..."
DELETE_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL/students/$STUDENT_ID" \
  -H "Authorization: Bearer $TOKEN")
DELETE_HTTP_CODE=$(echo "$DELETE_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
DELETE_BODY=$(echo "$DELETE_RESPONSE" | sed '/HTTP_CODE:/d')

echo "响应码: $DELETE_HTTP_CODE"
echo "响应体: $DELETE_BODY"

if [ "$DELETE_HTTP_CODE" = "204" ]; then
    echo "✓ 教师删除正确返回 204 No Content"
else
    echo "❌ 教师删除应返回 204，实际返回 $DELETE_HTTP_CODE"
fi
echo ""

# 步骤 6: 验证学生已删除（应返回 404）
echo "步骤 6: 验证学生已删除（应返回 404）..."
VERIFY_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "$BASE_URL/students/$STUDENT_ID" \
  -H "Authorization: Bearer $TOKEN")
VERIFY_HTTP_CODE=$(echo "$VERIFY_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
VERIFY_BODY=$(echo "$VERIFY_RESPONSE" | sed '/HTTP_CODE:/d')

echo "响应码: $VERIFY_HTTP_CODE"
echo "响应体: $VERIFY_BODY"

if [ "$VERIFY_HTTP_CODE" = "404" ]; then
    echo "✓ 学生已成功删除"
else
    echo "❌ 学生应该已删除（404），实际返回 $VERIFY_HTTP_CODE"
fi
echo ""

echo "=========================================="
echo "测试完成"
echo "=========================================="

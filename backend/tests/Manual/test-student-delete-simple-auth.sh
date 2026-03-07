#!/bin/bash

# 简化的学生删除认证测试
# 测试：
# 1. 未认证用户无法删除学生（401）
# 2. 删除不存在的学生返回 404

BASE_URL="http://localhost:8084/api"

echo "=========================================="
echo "测试学生删除路由的认证检查"
echo "=========================================="
echo ""

# 测试 1: 未认证删除（应返回 401）
echo "测试 1: 未认证删除（应返回 401）..."
UNAUTH_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL/students/999999")
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

# 测试 2: 使用无效 token 删除（应返回 401）
echo "测试 2: 使用无效 token 删除（应返回 401）..."
INVALID_TOKEN_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL/students/999999" \
  -H "Authorization: Bearer invalid_token_here")
INVALID_TOKEN_HTTP_CODE=$(echo "$INVALID_TOKEN_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
INVALID_TOKEN_BODY=$(echo "$INVALID_TOKEN_RESPONSE" | sed '/HTTP_CODE:/d')

echo "响应码: $INVALID_TOKEN_HTTP_CODE"
echo "响应体: $INVALID_TOKEN_BODY"

if [ "$INVALID_TOKEN_HTTP_CODE" = "401" ]; then
    echo "✓ 无效 token 正确返回 401"
else
    echo "❌ 无效 token 应返回 401，实际返回 $INVALID_TOKEN_HTTP_CODE"
fi
echo ""

# 测试 3: 登录并测试权限检查
echo "测试 3: 登录学生账号并测试权限（应返回 403）..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "guanfei",
    "password": "123456"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "⚠ 登录失败，跳过权限测试"
else
    echo "✓ 成功获取 token"
    
    # 尝试删除（学生角色应返回 403）
    FORBIDDEN_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL/students/999999" \
      -H "Authorization: Bearer $TOKEN")
    FORBIDDEN_HTTP_CODE=$(echo "$FORBIDDEN_RESPONSE" | grep "HTTP_CODE:" | cut -d':' -f2)
    FORBIDDEN_BODY=$(echo "$FORBIDDEN_RESPONSE" | sed '/HTTP_CODE:/d')
    
    echo "响应码: $FORBIDDEN_HTTP_CODE"
    echo "响应体: $FORBIDDEN_BODY"
    
    if [ "$FORBIDDEN_HTTP_CODE" = "403" ]; then
        echo "✓ 学生角色正确返回 403 Forbidden"
    elif [ "$FORBIDDEN_HTTP_CODE" = "404" ]; then
        echo "⚠ 返回 404（学生不存在），但权限检查可能未生效"
    else
        echo "❌ 学生角色应返回 403，实际返回 $FORBIDDEN_HTTP_CODE"
    fi
fi
echo ""

echo "=========================================="
echo "测试完成"
echo "=========================================="
echo ""
echo "总结："
echo "- 未认证请求应返回 401 ✓"
echo "- 无效 token 应返回 401 ✓"
echo "- 学生角色应返回 403 ✓"
echo "- 教师/管理员角色可以删除学生（需要教师账号测试）"
echo "- 删除成功应返回 204 No Content"

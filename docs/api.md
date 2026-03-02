# API 文档

学校管理系统后端 RESTful API 文档。

## 基础信息

- **Base URL**: `http://localhost:8084/api`
- **数据格式**: JSON
- **字符编码**: UTF-8
- **认证方式**: Bearer Token (JWT)

## 统一响应格式

### 成功响应

```json
{
  "code": 200,
  "message": "Success",
  "data": { ... },
  "timestamp": 1700000000
}
```

### 分页响应

```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [ ... ],
    "pagination": {
      "total": 100,
      "page": 1,
      "pageSize": 20,
      "totalPages": 5
    }
  },
  "timestamp": 1700000000
}
```

### 错误响应

```json
{
  "code": 400,
  "message": "Validation failed",
  "errors": {
    "name": ["Name is required"]
  },
  "timestamp": 1700000000
}
```

## 错误码说明

| HTTP 状态码 | 说明 |
|------------|------|
| 200 | 请求成功 |
| 201 | 创建成功 |
| 400 | 请求参数错误 |
| 401 | 未认证，需要登录 |
| 403 | 无权限执行此操作 |
| 404 | 资源不存在 |
| 422 | 数据验证失败 |
| 429 | 请求频率超限 |
| 500 | 服务器内部错误 |

---

## 认证接口 `/auth`

### POST /auth/login

使用用户名和密码登录，获取 JWT 令牌。

**请求体**:
```json
{
  "username": "admin",
  "password": "password123"
}
```

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Login successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "username": "admin",
      "nickname": "管理员",
      "avatar": "https://example.com/avatar.jpg",
      "role": "admin"
    }
  },
  "timestamp": 1700000000
}
```

**错误响应** (401):
```json
{
  "code": 401,
  "message": "Invalid credentials",
  "timestamp": 1700000000
}
```

---

### POST /auth/verify

验证来自主系统的 Session Token，完成跨系统单点登录。

**请求体**:
```json
{
  "token": "session-token-from-main-system"
}
```

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Token verified",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "username": "teacher01",
      "nickname": "张老师",
      "role": "teacher"
    }
  },
  "timestamp": 1700000000
}
```

---

### POST /auth/refresh

刷新 JWT 令牌，延长会话有效期。

**请求头**: `Authorization: Bearer <token>`

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Token refreshed",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400
  },
  "timestamp": 1700000000
}
```

---

### POST /auth/logout

退出登录，使当前令牌失效。

**请求头**: `Authorization: Bearer <token>`

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Logged out successfully",
  "timestamp": 1700000000
}
```

---

### GET /auth/me

获取当前登录用户信息。

**请求头**: `Authorization: Bearer <token>`

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "id": 1,
    "username": "teacher01",
    "nickname": "张老师",
    "avatar": "https://example.com/avatar.jpg",
    "email": "teacher@example.com",
    "role": "teacher",
    "created_at": "2024-01-01T00:00:00Z"
  },
  "timestamp": 1700000000
}
```

---

## 学校接口 `/schools`

> 所有接口需要认证：`Authorization: Bearer <token>`

### GET /schools

获取学校列表，支持分页和搜索。

**查询参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | integer | 否 | 页码，默认 1 |
| pageSize | integer | 否 | 每页数量，默认 20，最大 100 |
| name | string | 否 | 按学校名称搜索 |

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "示例小学",
        "image_id": 10,
        "image_url": "https://example.com/school.jpg",
        "info": "学校简介",
        "principal": {
          "id": 5,
          "nickname": "王校长",
          "avatar": "https://example.com/avatar.jpg"
        },
        "class_count": 12,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "total": 50,
      "page": 1,
      "pageSize": 20,
      "totalPages": 3
    }
  },
  "timestamp": 1700000000
}
```

---

### POST /schools

创建新学校。

**权限**: 系统管理员

**请求体**:
```json
{
  "name": "示例小学",
  "image_id": 10,
  "principal_id": 5,
  "info": "学校简介"
}
```

**字段说明**:
| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 学校名称，最大 255 字符 |
| image_id | integer | 否 | 学校图片 ID |
| principal_id | integer | 否 | 校长用户 ID |
| info | string | 否 | 学校简介 |

**成功响应** (201):
```json
{
  "code": 201,
  "message": "School created successfully",
  "data": {
    "id": 1,
    "name": "示例小学",
    "created_at": "2024-01-01T00:00:00Z"
  },
  "timestamp": 1700000000
}
```

---

### GET /schools/{id}

获取学校详情。

**路径参数**: `id` - 学校 ID

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "示例小学",
    "image_id": 10,
    "image_url": "https://example.com/school.jpg",
    "info": "学校简介",
    "principal": {
      "id": 5,
      "nickname": "王校长",
      "avatar": "https://example.com/avatar.jpg"
    },
    "classes": [
      { "id": 1, "name": "一年级一班" },
      { "id": 2, "name": "一年级二班" }
    ],
    "created_at": "2024-01-01T00:00:00Z"
  },
  "timestamp": 1700000000
}
```

---

### PUT /schools/{id}

更新学校信息。

**权限**: 系统管理员或该学校校长

**请求体**:
```json
{
  "name": "示例小学（更新）",
  "image_id": 11,
  "principal_id": 6,
  "info": "更新后的学校简介"
}
```

**成功响应** (200):
```json
{
  "code": 200,
  "message": "School updated successfully",
  "data": { "id": 1, "name": "示例小学（更新）" },
  "timestamp": 1700000000
}
```

---

### DELETE /schools/{id}

删除学校（级联删除关联班级、教师、学生记录）。

**权限**: 系统管理员

**成功响应** (200):
```json
{
  "code": 200,
  "message": "School deleted successfully",
  "timestamp": 1700000000
}
```

---

## 班级接口 `/classes`

### GET /classes

获取班级列表。

**查询参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | integer | 否 | 页码，默认 1 |
| pageSize | integer | 否 | 每页数量，默认 20 |
| school_id | integer | 否 | 按学校筛选 |
| name | string | 否 | 按班级名称搜索 |

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "一年级一班",
        "school_id": 1,
        "school_name": "示例小学",
        "image_url": null,
        "info": "班级简介",
        "teacher_count": 3,
        "student_count": 45,
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": { "total": 30, "page": 1, "pageSize": 20, "totalPages": 2 }
  },
  "timestamp": 1700000000
}
```

---

### POST /classes

创建班级。

**请求体**:
```json
{
  "name": "一年级一班",
  "school_id": 1,
  "image_id": 20,
  "info": "班级简介"
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Class created successfully",
  "data": { "id": 1, "name": "一年级一班", "school_id": 1 },
  "timestamp": 1700000000
}
```

---

### GET /classes/{id}

获取班级详情，包含教师和学生列表。

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "一年级一班",
    "school": { "id": 1, "name": "示例小学" },
    "image_url": null,
    "info": "班级简介",
    "teachers": [
      { "id": 1, "user_id": 10, "nickname": "李老师", "avatar": "..." }
    ],
    "students": [
      { "id": 1, "user_id": 20, "nickname": "小明", "avatar": "..." }
    ],
    "created_at": "2024-01-01T00:00:00Z"
  },
  "timestamp": 1700000000
}
```

---

### PUT /classes/{id}

更新班级信息。

**请求体**:
```json
{
  "name": "一年级一班（更新）",
  "image_id": 21,
  "info": "更新后的班级简介"
}
```

---

### DELETE /classes/{id}

删除班级（级联删除教师、学生记录）。

---

## 教师接口 `/teachers`

### GET /teachers

获取教师列表。

**查询参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| class_id | integer | 否 | 按班级筛选 |
| school_id | integer | 否 | 按学校筛选 |
| nickname | string | 否 | 按昵称搜索 |
| page | integer | 否 | 页码 |
| pageSize | integer | 否 | 每页数量 |

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "user_id": 10,
        "class_id": 1,
        "class_name": "一年级一班",
        "nickname": "李老师",
        "avatar": "https://example.com/avatar.jpg",
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": { "total": 10, "page": 1, "pageSize": 20, "totalPages": 1 }
  },
  "timestamp": 1700000000
}
```

---

### POST /teachers

将用户添加为班级教师。

**请求体**:
```json
{
  "user_id": 10,
  "class_id": 1
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Teacher added successfully",
  "data": { "id": 1, "user_id": 10, "class_id": 1 },
  "timestamp": 1700000000
}
```

**错误响应** (409 - 重复添加):
```json
{
  "code": 409,
  "message": "User is already a teacher of this class",
  "timestamp": 1700000000
}
```

---

### DELETE /teachers/{id}

移除班级教师。

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Teacher removed successfully",
  "timestamp": 1700000000
}
```

---

## 学生接口 `/students`

### GET /students

获取学生列表。

**查询参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| class_id | integer | 否 | 按班级筛选 |
| school_id | integer | 否 | 按学校筛选 |
| nickname | string | 否 | 按昵称搜索 |
| page | integer | 否 | 页码 |
| pageSize | integer | 否 | 每页数量 |

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "user_id": 20,
        "class_id": 1,
        "class_name": "一年级一班",
        "nickname": "小明",
        "avatar": "https://example.com/avatar.jpg",
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": { "total": 45, "page": 1, "pageSize": 20, "totalPages": 3 }
  },
  "timestamp": 1700000000
}
```

---

### POST /students

将用户添加为班级学生。

**请求体**:
```json
{
  "user_id": 20,
  "class_id": 1
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Student added successfully",
  "data": { "id": 1, "user_id": 20, "class_id": 1 },
  "timestamp": 1700000000
}
```

---

### DELETE /students/{id}

移除班级学生。

---

## 小组接口 `/groups`

### GET /groups

获取小组列表。

**查询参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | integer | 否 | 页码 |
| pageSize | integer | 否 | 每页数量 |
| name | string | 否 | 按名称搜索 |

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "数学兴趣小组",
        "description": "小组描述",
        "image_url": null,
        "member_count": 8,
        "class_count": 2,
        "creator": { "id": 10, "nickname": "李老师" },
        "created_at": "2024-01-01T00:00:00Z"
      }
    ],
    "pagination": { "total": 20, "page": 1, "pageSize": 20, "totalPages": 1 }
  },
  "timestamp": 1700000000
}
```

---

### POST /groups

创建小组。

**请求体**:
```json
{
  "name": "数学兴趣小组",
  "description": "小组描述",
  "image_id": 30,
  "info": "小组详细信息"
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Group created successfully",
  "data": { "id": 1, "name": "数学兴趣小组" },
  "timestamp": 1700000000
}
```

---

### GET /groups/{id}

获取小组详情，包含成员和关联班级。

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "id": 1,
    "name": "数学兴趣小组",
    "description": "小组描述",
    "image_url": null,
    "info": "小组详细信息",
    "creator": { "id": 10, "nickname": "李老师" },
    "members": [
      { "id": 20, "nickname": "小明", "avatar": "..." },
      { "id": 21, "nickname": "小红", "avatar": "..." }
    ],
    "classes": [
      { "id": 1, "name": "一年级一班" }
    ],
    "created_at": "2024-01-01T00:00:00Z"
  },
  "timestamp": 1700000000
}
```

---

### PUT /groups/{id}

更新小组信息。

**请求体**:
```json
{
  "name": "数学兴趣小组（更新）",
  "description": "更新后的描述",
  "image_id": 31,
  "info": "更新后的详细信息"
}
```

---

### DELETE /groups/{id}

删除小组。

---

### POST /groups/{id}/members

向小组添加成员。

**请求体**:
```json
{
  "user_id": 25
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Member added successfully",
  "timestamp": 1700000000
}
```

---

### DELETE /groups/{id}/members/{userId}

从小组移除成员。

**成功响应** (200):
```json
{
  "code": 200,
  "message": "Member removed successfully",
  "timestamp": 1700000000
}
```

---

### POST /groups/{id}/classes

将班级关联到小组。

**请求体**:
```json
{
  "class_id": 1
}
```

**成功响应** (201):
```json
{
  "code": 201,
  "message": "Class associated successfully",
  "timestamp": 1700000000
}
```

---

### DELETE /groups/{id}/classes/{classId}

解除班级与小组的关联。

---

## 健康检查接口

### GET /health

基础健康检查，无需认证。

**成功响应** (200):
```json
{
  "status": "ok",
  "timestamp": 1700000000
}
```

---

### GET /health/detailed

详细健康检查，包含数据库和 Redis 连接状态。

**成功响应** (200):
```json
{
  "status": "ok",
  "services": {
    "database": { "status": "ok", "latency_ms": 2 },
    "redis": { "status": "ok", "latency_ms": 1 }
  },
  "timestamp": 1700000000
}
```

**部分故障响应** (503):
```json
{
  "status": "degraded",
  "services": {
    "database": { "status": "ok", "latency_ms": 2 },
    "redis": { "status": "error", "message": "Connection refused" }
  },
  "timestamp": 1700000000
}
```

---

### GET /version

获取应用版本信息。

**成功响应** (200):
```json
{
  "version": "1.0.0",
  "build": "2024-01-01",
  "php_version": "8.1.0",
  "framework": "Yii3"
}
```

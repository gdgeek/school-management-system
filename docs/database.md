# 数据库设计文档

学校管理系统与主系统共享同一个 MySQL 数据库（`xrugc`）。本文档描述学校管理系统所使用的表结构、字段说明、索引和约束。

## 数据库概览

- **数据库引擎**: MySQL 8.0+
- **字符集**: utf8mb4
- **排序规则**: utf8mb4_unicode_ci
- **共享数据库**: 与主系统（XR UGC）共享，学校管理系统对 `user`、`file` 表只有读权限

---

## 表结构

### user（用户表）

存储系统所有用户信息，由主系统管理，学校管理系统只读。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| username | varchar(64) | 否 | | 用户名，唯一 |
| password | varchar(255) | 否 | | 密码哈希 |
| nickname | varchar(64) | 是 | NULL | 昵称 |
| avatar | varchar(255) | 是 | NULL | 头像 URL |
| email | varchar(128) | 是 | NULL | 邮箱 |
| role | varchar(32) | 否 | 'user' | 角色：admin/teacher/student/user |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_username (username)`
- `KEY idx_role (role)`
- `KEY idx_deleted_at (deleted_at)`

---

### edu_school（学校表）

存储学校基本信息。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| name | varchar(255) | 否 | | 学校名称 |
| image_id | bigint unsigned | 是 | NULL | 学校图片 ID，关联 file 表 |
| principal_id | bigint unsigned | 是 | NULL | 校长用户 ID，关联 user 表 |
| info | text | 是 | NULL | 学校简介 |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `KEY idx_name (name)`
- `KEY idx_principal_id (principal_id)`
- `KEY idx_deleted_at (deleted_at)`

**外键约束**（逻辑约束，不强制 FK）:
- `principal_id` → `user.id`
- `image_id` → `file.id`

---

### edu_class（班级表）

存储班级信息，每个班级属于一所学校。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| name | varchar(255) | 否 | | 班级名称 |
| school_id | bigint unsigned | 否 | | 所属学校 ID |
| image_id | bigint unsigned | 是 | NULL | 班级图片 ID |
| info | text | 是 | NULL | 班级简介 |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `KEY idx_school_id (school_id)`
- `KEY idx_name (name)`
- `KEY idx_deleted_at (deleted_at)`

---

### edu_teacher（教师表）

记录用户与班级的教师关联关系。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| user_id | bigint unsigned | 否 | | 用户 ID |
| class_id | bigint unsigned | 否 | | 班级 ID |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_user_class (user_id, class_id)` — 防止重复分配
- `KEY idx_class_id (class_id)`
- `KEY idx_deleted_at (deleted_at)`

---

### edu_student（学生表）

记录用户与班级的学生关联关系。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| user_id | bigint unsigned | 否 | | 用户 ID |
| class_id | bigint unsigned | 否 | | 班级 ID |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_user_class (user_id, class_id)` — 防止重复分配
- `KEY idx_class_id (class_id)`
- `KEY idx_deleted_at (deleted_at)`

**业务约束**: 一个用户同一时间只能是一个班级的学生（通过业务逻辑保证）。

---

### group（小组表）

存储学习小组信息。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| name | varchar(255) | 否 | | 小组名称 |
| description | varchar(500) | 是 | NULL | 小组描述 |
| user_id | bigint unsigned | 否 | | 创建者用户 ID |
| image_id | bigint unsigned | 是 | NULL | 小组图片 ID |
| info | text | 是 | NULL | 小组详细信息 |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 创建时间 |
| deleted_at | timestamp | 是 | NULL | 软删除时间 |

**索引**:
- `PRIMARY KEY (id)`
- `KEY idx_user_id (user_id)`
- `KEY idx_name (name)`
- `KEY idx_deleted_at (deleted_at)`

---

### group_user（小组成员表）

记录用户与小组的成员关系。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| user_id | bigint unsigned | 否 | | 用户 ID |
| group_id | bigint unsigned | 否 | | 小组 ID |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 加入时间 |

**索引**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_user_group (user_id, group_id)` — 防止重复加入
- `KEY idx_group_id (group_id)`

---

### class_group（班级小组关联表）

记录班级与小组的关联关系（多对多）。

| 字段 | 类型 | 可空 | 默认值 | 说明 |
|------|------|------|--------|------|
| id | bigint unsigned | 否 | AUTO_INCREMENT | 主键 |
| class_id | bigint unsigned | 否 | | 班级 ID |
| group_id | bigint unsigned | 否 | | 小组 ID |
| created_at | timestamp | 否 | CURRENT_TIMESTAMP | 关联时间 |

**索引**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_class_group (class_id, group_id)` — 防止重复关联
- `KEY idx_group_id (group_id)`

---

## ER 图（ASCII）

```
┌─────────────┐       ┌──────────────┐       ┌─────────────┐
│    user     │       │  edu_school  │       │  edu_class  │
├─────────────┤       ├──────────────┤       ├─────────────┤
│ id (PK)     │◄──────│ principal_id │       │ id (PK)     │
│ username    │       │ id (PK)      │◄──────│ school_id   │
│ password    │       │ name         │       │ name        │
│ nickname    │       │ image_id     │       │ image_id    │
│ avatar      │       │ info         │       │ info        │
│ email       │       │ created_at   │       │ created_at  │
│ role        │       │ deleted_at   │       │ deleted_at  │
│ created_at  │       └──────────────┘       └──────┬──────┘
│ deleted_at  │                                     │
└──────┬──────┘                                     │
       │                                            │
       │  ┌─────────────┐    ┌──────────────┐      │
       │  │ edu_teacher │    │  edu_student │      │
       │  ├─────────────┤    ├──────────────┤      │
       └─►│ user_id     │    │ user_id      │◄─────┘
          │ class_id    │◄───│ class_id     │
          │ created_at  │    │ created_at   │
          │ deleted_at  │    │ deleted_at   │
          └─────────────┘    └──────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    group    │       │ group_user  │       │ class_group │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id (PK)     │◄──────│ group_id    │       │ group_id    │◄──┐
│ name        │       │ user_id     │◄──┐   │ class_id    │   │
│ description │       │ created_at  │   │   │ created_at  │   │
│ user_id     │       └─────────────┘   │   └─────────────┘   │
│ image_id    │                         │                      │
│ info        │                         └──── user.id          │
│ created_at  │                                                │
│ deleted_at  │────────────────────────────────────────────────┘
└─────────────┘
```

---

## 软删除说明

所有主要业务表均使用软删除（`deleted_at` 字段）：
- 删除操作只设置 `deleted_at = NOW()`，不物理删除数据
- 查询时默认过滤 `deleted_at IS NULL` 的记录
- 可通过管理工具查看已删除数据或执行恢复操作

## 数据一致性

- 删除学校时，级联软删除关联的班级、教师、学生记录
- 删除班级时，级联软删除关联的教师、学生记录
- 所有写操作使用数据库事务保证原子性
- 唯一索引在数据库层面防止重复数据

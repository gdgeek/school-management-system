# 班级和小组删除功能测试

## 功能说明

### 1. 删除小组（级联删除班级）
- 删除小组时，会自动删除所有关联的班级
- 删除顺序：班级 → 小组成员 → 班级关联 → 小组

### 2. 删除班级（可选删除小组）
- 删除班级时，可以选择是否删除关联的小组
- 通过 `deleteGroups` 查询参数控制
  - `deleteGroups=true`: 删除班级和关联的小组
  - `deleteGroups=false` 或不传: 只删除班级，保留小组

## API 测试

### 测试 1: 删除班级（不删除小组）

```bash
# 删除班级 ID=1，不删除关联的小组
curl -X DELETE "http://localhost:8080/api/classes/1" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 或明确指定不删除小组
curl -X DELETE "http://localhost:8080/api/classes/1?deleteGroups=false" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

预期结果：
- 班级被删除
- 关联的小组仍然存在
- 班级-小组关联关系被删除

### 测试 2: 删除班级（同时删除小组）

```bash
# 删除班级 ID=2，同时删除关联的小组
curl -X DELETE "http://localhost:8080/api/classes/2?deleteGroups=true" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

预期结果：
- 班级被删除
- 关联的小组也被删除
- 班级-小组关联关系被删除

### 测试 3: 删除小组（级联删除班级）

```bash
# 删除小组 ID=1，自动删除关联的班级
curl -X DELETE "http://localhost:8080/api/groups/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

预期结果：
- 小组被删除
- 所有关联的班级被删除
- 班级-小组关联关系被删除
- 小组成员关系被删除

## 数据库验证

### 验证删除班级（不删除小组）

```sql
-- 删除前
SELECT * FROM edu_class WHERE id = 1;
SELECT * FROM `group` WHERE id = 1;
SELECT * FROM edu_class_group WHERE class_id = 1;

-- 执行删除（不删除小组）

-- 删除后
SELECT * FROM edu_class WHERE id = 1;        -- 应该为空
SELECT * FROM `group` WHERE id = 1;          -- 应该存在
SELECT * FROM edu_class_group WHERE class_id = 1;  -- 应该为空
```

### 验证删除班级（删除小组）

```sql
-- 删除前
SELECT * FROM edu_class WHERE id = 2;
SELECT * FROM `group` WHERE id = 2;
SELECT * FROM edu_class_group WHERE class_id = 2;

-- 执行删除（删除小组）

-- 删除后
SELECT * FROM edu_class WHERE id = 2;        -- 应该为空
SELECT * FROM `group` WHERE id = 2;          -- 应该为空
SELECT * FROM edu_class_group WHERE class_id = 2;  -- 应该为空
```

### 验证删除小组（级联删除班级）

```sql
-- 删除前
SELECT * FROM `group` WHERE id = 3;
SELECT * FROM edu_class WHERE id IN (SELECT class_id FROM edu_class_group WHERE group_id = 3);
SELECT * FROM edu_class_group WHERE group_id = 3;

-- 执行删除

-- 删除后
SELECT * FROM `group` WHERE id = 3;          -- 应该为空
SELECT * FROM edu_class WHERE id IN (SELECT class_id FROM edu_class_group WHERE group_id = 3);  -- 应该为空
SELECT * FROM edu_class_group WHERE group_id = 3;  -- 应该为空
```

## 注意事项

1. 删除操作使用事务，确保数据一致性
2. 删除小组时会级联删除班级，请谨慎操作
3. 删除班级时默认不删除小组，需要明确指定 `deleteGroups=true`
4. 建议在生产环境添加二次确认机制

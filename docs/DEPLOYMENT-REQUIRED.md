# 需要重新部署

## 修改内容

修复了删除小组时未级联删除班级的 bug，并实现了删除班级时可选删除小组的功能。

## 修改的文件

1. `backend/src/Service/GroupService.php` - 修复删除逻辑，添加级联删除
2. `backend/src/Service/ClassService.php` - 添加可选删除小组参数
3. `backend/src/Controller/ClassController.php` - 支持 deleteGroups 参数
4. `backend/public/index.php` - 修复依赖注入参数，添加 deleteGroups 参数处理
5. `frontend/src/api/class.ts` - 添加 deleteGroups 参数
6. `frontend/src/views/classes/ClassList.vue` - 两步确认删除流程
7. `frontend/src/views/groups/GroupList.vue` - 增强删除警告

## 部署步骤

### 后端
```bash
# 重启后端服务
docker restart xrugc-school-backend
```

### 前端
```bash
cd school-management-system/frontend
npm run build
# 或重启开发服务器
npm run dev
```

## 验证步骤

### 测试删除小组（级联删除班级）
```bash
cd school-management-system/backend
./tests/Manual/test-delete-group-api.sh
```

预期结果：
```
步骤 5: 验证级联删除
-------------------
✓ 班级已删除
✓ 小组已删除
```

### 测试删除班级（可选删除小组）
```bash
cd school-management-system/backend
./tests/Manual/test-delete-class-with-groups.sh
```

预期结果：
```
场景 1: 删除班级，保留小组
✓ 班级已删除
✓ 小组仍存在（符合预期）

场景 2: 删除班级，同时删除小组
✓ 班级已删除
✓ 小组已删除（符合预期）
```

## 当前状态

- ✅ 代码已修改
- ✅ 后端服务已重启
- ✅ 功能已测试验证
- ⚠️ 需要重新构建前端（如果是生产环境）

## 功能说明

### 删除小组
- 删除小组时，自动删除所有关联的班级
- 前端显示警告："删除小组将同时删除所有关联的班级！"

### 删除班级
- 删除班级时，前端会询问用户是否删除关联的小组
- 两步确认流程：
  1. 确认删除班级
  2. 选择是否删除关联的小组
- API 参数：`DELETE /api/classes/{id}?deleteGroups={true|false}`

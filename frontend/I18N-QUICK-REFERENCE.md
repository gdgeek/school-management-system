# 多语言快速参考

## 快速开始

### 在模板中使用

```vue
<template>
  <!-- 简单文本 -->
  <el-button>{{ $t('common.save') }}</el-button>
  
  <!-- 带插值 -->
  <span>{{ $t('common.total', { total: 100 }) }}</span>
  
  <!-- 属性绑定 -->
  <el-input :placeholder="$t('school.searchPlaceholder')" />
</template>
```

### 在脚本中使用

```typescript
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// 提示消息
ElMessage.success(t('common.success'))

// 确认对话框
await ElMessageBox.confirm(
  t('school.deleteConfirm', { name: school.name }),
  t('common.deleteConfirm'),
  {
    confirmButtonText: t('common.confirm'),
    cancelButtonText: t('common.cancel'),
  }
)

// 表格列（使用计算属性）
const columns = computed(() => [
  { prop: 'name', label: t('school.name') }
])
</script>
```

## 常用翻译键

### 通用操作
```typescript
$t('common.save')      // 保存
$t('common.cancel')    // 取消
$t('common.confirm')   // 确认
$t('common.delete')    // 删除
$t('common.edit')      // 编辑
$t('common.create')    // 创建
$t('common.search')    // 搜索
$t('common.reset')     // 重置
$t('common.submit')    // 提交
$t('common.back')      // 返回
$t('common.close')     // 关闭
```

### 状态消息
```typescript
$t('common.success')         // 操作成功
$t('common.failed')          // 操作失败
$t('common.loading')         // 加载中...
$t('common.noData')          // 暂无数据
$t('common.deleteConfirm')   // 确定要删除吗？
```

### 学校模块
```typescript
$t('school.title')           // 学校管理
$t('school.create')          // 创建学校
$t('school.edit')            // 编辑学校
$t('school.name')            // 学校名称
$t('school.info')            // 学校简介
$t('school.principal')       // 校长
$t('school.searchPlaceholder') // 搜索学校名称...
$t('school.createSuccess')   // 学校创建成功
$t('school.deleteConfirm', { name }) // 确定要删除学校 "{name}" 吗？
```

### 班级模块
```typescript
$t('class.title')            // 班级管理
$t('class.create')           // 创建班级
$t('class.name')             // 班级名称
$t('class.school')           // 所属学校
```

### 教师/学生模块
```typescript
$t('teacher.title')          // 教师管理
$t('teacher.add')            // 添加教师
$t('teacher.remove')         // 移除教师
$t('student.title')          // 学生管理
$t('student.add')            // 添加学生
```

### 小组模块
```typescript
$t('group.title')            // 小组管理
$t('group.create')           // 创建小组
$t('group.members')          // 成员
$t('group.addMember')        // 添加成员
```

## 自动化脚本

```bash
# 处理单个文件
node scripts/apply-i18n.js "src/views/schools/SchoolList.vue"

# 处理所有视图
node scripts/apply-i18n.js "src/views/**/*.vue"

# 处理特定模块
node scripts/apply-i18n.js "src/views/classes/*.vue"
```

## 常见模式

### 1. 按钮
```vue
<el-button type="primary">{{ $t('common.save') }}</el-button>
```

### 2. 表单标签
```vue
<el-form-item :label="$t('school.name')" prop="name">
```

### 3. 输入框占位符
```vue
<el-input :placeholder="$t('school.searchPlaceholder')" />
```

### 4. 表格列
```typescript
const columns = computed(() => [
  { prop: 'name', label: t('school.name') }
])
```

### 5. 提示消息
```typescript
ElMessage.success(t('common.success'))
ElMessage.error(t('common.failed'))
```

### 6. 确认对话框
```typescript
await ElMessageBox.confirm(
  t('school.deleteConfirm', { name: school.name }),
  t('common.deleteConfirm'),
  {
    confirmButtonText: t('common.confirm'),
    cancelButtonText: t('common.cancel'),
    type: 'warning'
  }
)
```

## 注意事项

1. ✅ 使用 `$t()` 在模板中
2. ✅ 使用 `t()` 在脚本中（需要先 `const { t } = useI18n()`）
3. ✅ 表格列使用 `computed(() => [...])`
4. ✅ 动态文本使用插值：`t('key', { var })`
5. ❌ 不要在循环中重复调用 `t()`
6. ❌ 不要硬编码文本

## 测试

切换语言：点击右上角语言切换按钮
- 简体中文
- 繁體中文
- English

## 更多信息

- 详细指南：`docs/i18n-implementation-guide.md`
- 系统文档：`docs/i18n-plugin-system.md`
- 完整示例：`src/views/schools/SchoolList.i18n.vue`

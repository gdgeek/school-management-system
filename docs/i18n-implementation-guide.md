# 多语言实现指南

## 概述

本指南说明如何在学校管理系统中应用多语言功能。

## 架构

### 1. 多语言文件结构

```
frontend/src/locales/
├── index.ts          # i18n 配置
├── zh-CN.ts          # 简体中文
├── zh-TW.ts          # 繁体中文
└── en.ts             # 英文
```

### 2. Composable

创建了 `useI18n` composable 来简化使用：

```typescript
// frontend/src/composables/useI18n.ts
import { useI18n as useVueI18n } from 'vue-i18n'

export function useI18n() {
  const { t, locale } = useVueI18n()
  
  return {
    t,
    locale,
    common: {
      confirm: () => t('common.confirm'),
      cancel: () => t('common.cancel'),
      // ... 更多快捷方式
    },
  }
}
```

## 在组件中应用多语言

### 方法 1: 使用 $t (模板中)

```vue
<template>
  <el-button>{{ $t('common.save') }}</el-button>
  <h1>{{ $t('school.title') }}</h1>
</template>
```

### 方法 2: 使用 useI18n (脚本中)

```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// 在函数中使用
function showMessage() {
  ElMessage.success(t('common.success'))
}
</script>
```

### 方法 3: 使用自定义 composable

```vue
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n'

const { t, common } = useI18n()

// 使用快捷方式
console.log(common.save()) // "保存" 或 "儲存" 或 "Save"
</script>
```

## 组件改造示例

### 示例 1: 学校列表组件

#### 改造前

```vue
<template>
  <PageHeader title="学校管理">
    <template #actions>
      <el-button type="primary">创建学校</el-button>
    </template>
  </PageHeader>
  
  <el-form-item label="搜索">
    <el-input placeholder="输入学校名称" />
  </el-form-item>
  
  <el-button type="primary">搜索</el-button>
  <el-button>重置</el-button>
</template>
```

#### 改造后

```vue
<template>
  <PageHeader :title="$t('school.title')">
    <template #actions>
      <el-button type="primary">
        <el-icon><Plus /></el-icon>
        {{ $t('school.create') }}
      </el-button>
    </template>
  </PageHeader>
  
  <el-form-item :label="$t('common.search')">
    <el-input :placeholder="$t('school.searchPlaceholder')" />
  </el-form-item>
  
  <el-button type="primary">{{ $t('common.search') }}</el-button>
  <el-button>{{ $t('common.reset') }}</el-button>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// 表格列配置
const columns = [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'name', label: t('school.name'), minWidth: 200 },
  { prop: 'principal', label: t('school.principal'), minWidth: 150 },
  { prop: 'info', label: t('school.info'), minWidth: 200 },
  { prop: 'created_at', label: t('common.createdAt'), width: 180 }
]

// 删除确认
async function handleDelete(school: School) {
  await ElMessageBox.confirm(
    t('school.deleteConfirm', { name: school.name }),
    t('common.deleteConfirm'),
    {
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel'),
      type: 'warning'
    }
  )
  
  await deleteSchool(school.id)
  ElMessage.success(t('school.deleteSuccess'))
}
</script>
```

### 示例 2: 表单组件

#### 改造前

```vue
<template>
  <el-dialog title="创建学校">
    <el-form>
      <el-form-item label="学校名称" prop="name">
        <el-input placeholder="请输入学校名称" />
      </el-form-item>
      <el-form-item label="学校简介" prop="info">
        <el-input type="textarea" placeholder="请输入学校简介" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" @click="handleSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>
```

#### 改造后

```vue
<template>
  <el-dialog :title="mode === 'create' ? $t('school.create') : $t('school.edit')">
    <el-form>
      <el-form-item :label="$t('school.name')" prop="name">
        <el-input :placeholder="$t('school.name')" />
      </el-form-item>
      <el-form-item :label="$t('school.info')" prop="info">
        <el-input type="textarea" :placeholder="$t('school.info')" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="handleCancel">{{ $t('common.cancel') }}</el-button>
      <el-button type="primary" @click="handleSubmit">{{ $t('common.confirm') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

async function handleSubmit() {
  // ...
  ElMessage.success(
    mode.value === 'create' 
      ? t('school.createSuccess') 
      : t('school.updateSuccess')
  )
}
</script>
```

## 批量改造步骤

### 1. 识别需要翻译的文本

在组件中查找所有硬编码的中文文本：
- 按钮文本
- 标签文本
- 提示信息
- 错误信息
- 确认对话框文本

### 2. 替换为翻译键

```vue
<!-- 改造前 -->
<el-button>保存</el-button>

<!-- 改造后 -->
<el-button>{{ $t('common.save') }}</el-button>
```

### 3. 更新脚本部分

```typescript
// 改造前
ElMessage.success('操作成功')

// 改造后
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
ElMessage.success(t('common.success'))
```

### 4. 处理动态文本

```typescript
// 使用插值
t('school.deleteConfirm', { name: school.name })

// 对应的翻译文件
{
  school: {
    deleteConfirm: '确定要删除学校 "{name}" 吗？'
  }
}
```

## 需要改造的组件列表

### 视图组件
- [ ] SchoolList.vue
- [ ] SchoolForm.vue
- [ ] ClassList.vue
- [ ] ClassForm.vue
- [ ] TeacherList.vue
- [ ] StudentList.vue
- [ ] GroupList.vue
- [ ] GroupDetail.vue
- [ ] GroupForm.vue
- [ ] LoginView.vue

### 通用组件
- [ ] AppHeader.vue (已完成语言切换器)
- [ ] AppSidebar.vue
- [ ] DataTable.vue
- [ ] FormDialog.vue
- [ ] UserSelectDialog.vue
- [ ] PageHeader.vue

## 自动化工具

### 查找硬编码文本

```bash
# 查找所有包含中文的 Vue 文件
grep -r "[\u4e00-\u9fa5]" src/views --include="*.vue"

# 查找模板中的中文
grep -r "<.*>.*[\u4e00-\u9fa5].*<" src/views --include="*.vue"
```

### 批量替换脚本

创建一个脚本来辅助批量替换常见文本：

```javascript
// scripts/replace-i18n.js
const replacements = {
  '保存': "{{ $t('common.save') }}",
  '取消': "{{ $t('common.cancel') }}",
  '确定': "{{ $t('common.confirm') }}",
  '删除': "{{ $t('common.delete') }}",
  '编辑': "{{ $t('common.edit') }}",
  '创建': "{{ $t('common.create') }}",
  '搜索': "{{ $t('common.search') }}",
  '重置': "{{ $t('common.reset') }}",
}

// 使用 fs 读取文件并替换
```

## 测试

### 1. 切换语言测试

- 切换到简体中文，检查所有文本
- 切换到繁体中文，检查所有文本
- 切换到英文，检查所有文本

### 2. 功能测试

确保多语言改造后功能正常：
- 表单验证
- 提示信息
- 确认对话框
- 错误处理

### 3. 边界情况

- 长文本是否正确显示
- 特殊字符是否正确处理
- 插值是否正确工作

## 最佳实践

1. **保持翻译键的一致性**
   - 使用统一的命名规范
   - 相同含义的文本使用相同的键

2. **避免在翻译中使用 HTML**
   - 使用插值而不是 HTML 标签
   - 如需格式化，使用组件

3. **提供上下文**
   - 翻译键名应该清晰表达含义
   - 添加注释说明特殊情况

4. **处理复数和性别**
   - 使用 vue-i18n 的复数功能
   - 考虑不同语言的语法规则

5. **性能优化**
   - 避免在循环中调用 t()
   - 考虑使用计算属性缓存翻译结果

## 常见问题

### Q: 如何处理动态内容？

A: 使用插值：
```typescript
t('message.welcome', { name: user.name })
```

### Q: 如何处理 Element Plus 组件的文本？

A: Element Plus 支持国际化，需要配置：
```typescript
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'

app.use(ElementPlus, { locale: zhCn })
```

### Q: 翻译文件太大怎么办？

A: 可以按模块拆分：
```typescript
// locales/zh-CN/index.ts
import common from './common'
import school from './school'
import class from './class'

export default {
  common,
  school,
  class,
}
```

## 参考资源

- [Vue I18n 官方文档](https://vue-i18n.intlify.dev/)
- [Element Plus 国际化](https://element-plus.org/zh-CN/guide/i18n.html)
- [繁简转换工具](https://www.aies.cn/)

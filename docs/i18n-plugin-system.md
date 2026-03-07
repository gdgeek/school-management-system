# 多语言插件系统

## 概述

已为学校管理系统创建了完整的多语言插件系统，包括翻译文件、工具和文档。

## 系统组成

### 1. 核心文件

#### 翻译文件
- `frontend/src/locales/zh-CN.ts` - 简体中文
- `frontend/src/locales/zh-TW.ts` - 繁体中文 ✨
- `frontend/src/locales/en.ts` - 英文
- `frontend/src/locales/index.ts` - i18n 配置

#### Composable
- `frontend/src/composables/useI18n.ts` - 多语言 composable，简化使用

### 2. 工具和脚本

#### 自动化脚本
- `frontend/scripts/apply-i18n.js` - 批量应用多语言的自动化脚本

**功能：**
- 自动替换常见的硬编码文本
- 自动添加必要的 import 语句
- 支持批量处理多个文件

**使用方法：**
```bash
# 处理单个文件
node scripts/apply-i18n.js "src/views/schools/SchoolList.vue"

# 处理整个目录
node scripts/apply-i18n.js "src/views/**/*.vue"
```

### 3. 示例和文档

#### 示例文件
- `frontend/src/views/schools/SchoolList.i18n.vue` - 完整的多语言应用示例

**展示内容：**
- 模板中使用 `$t()`
- 脚本中使用 `useI18n()`
- 动态文本的插值
- 表格列配置的国际化
- 确认对话框的国际化

#### 文档
- `docs/i18n-implementation-guide.md` - 详细的实现指南
- `docs/i18n-traditional-chinese.md` - 繁体中文支持说明
- `docs/i18n-plugin-system.md` - 本文档
- `frontend/I18N-README.md` - 快速开始指南

## 使用流程

### 方案 A: 手动改造（推荐用于复杂组件）

1. 参考示例文件 `SchoolList.i18n.vue`
2. 按照实现指南逐步改造
3. 测试所有语言切换

### 方案 B: 自动化改造（推荐用于简单组件）

1. 备份代码
2. 运行自动化脚本
3. 手动调整复杂部分
4. 测试验证

### 方案 C: 混合方案（推荐）

1. 先用自动化脚本处理简单文本
2. 手动处理复杂逻辑
3. 参考示例文件完善细节

## 改造模式

### 模式 1: 简单文本替换

```vue
<!-- 改造前 -->
<el-button>保存</el-button>

<!-- 改造后 -->
<el-button>{{ $t('common.save') }}</el-button>
```

### 模式 2: 带插值的文本

```vue
<!-- 改造前 -->
<span>共 {{ total }} 条</span>

<!-- 改造后 -->
<span>{{ $t('common.total', { total }) }}</span>
```

### 模式 3: 表格列配置

```typescript
// 改造前
const columns = [
  { prop: 'name', label: '学校名称' }
]

// 改造后
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

const columns = computed(() => [
  { prop: 'name', label: t('school.name') }
])
```

### 模式 4: 确认对话框

```typescript
// 改造前
await ElMessageBox.confirm(
  `确定要删除学校"${school.name}"吗？`,
  '删除确认'
)

// 改造后
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

### 模式 5: 提示消息

```typescript
// 改造前
ElMessage.success('操作成功')

// 改造后
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

ElMessage.success(t('common.success'))
```

## 翻译键命名规范

### 结构

```
模块.功能.具体内容
```

### 示例

```typescript
{
  common: {
    save: '保存',
    cancel: '取消',
  },
  school: {
    title: '学校管理',
    create: '创建学校',
    deleteConfirm: '确定要删除学校 "{name}" 吗？'
  }
}
```

### 规则

1. 使用小驼峰命名
2. 通用文本放在 `common` 下
3. 模块特定文本放在对应模块下
4. 动作相关使用动词（create, edit, delete）
5. 标题相关使用 title
6. 确认消息使用 Confirm 后缀

## 支持的语言

| 语言 | 代码 | 状态 | 覆盖率 |
|------|------|------|--------|
| 简体中文 | zh-CN | ✅ 完成 | 100% |
| 繁体中文 | zh-TW | ✅ 完成 | 100% |
| 英文 | en | ✅ 完成 | 100% |

## 组件改造进度

### 已完成
- [x] 语言切换器 (AppHeader.vue)
- [x] 翻译文件 (zh-CN, zh-TW, en)
- [x] Composable (useI18n)
- [x] 示例组件 (SchoolList.i18n.vue)
- [x] 自动化脚本
- [x] 文档

### 待改造
- [ ] 所有视图组件 (12个)
- [ ] 通用组件 (5个)

## 测试清单

### 功能测试
- [ ] 切换到简体中文，所有文本正确显示
- [ ] 切换到繁体中文，所有文本正确显示
- [ ] 切换到英文，所有文本正确显示
- [ ] 刷新页面后语言设置保持
- [ ] 所有按钮功能正常
- [ ] 所有表单验证正常
- [ ] 所有提示消息正确显示

### 边界测试
- [ ] 长文本正确显示和换行
- [ ] 特殊字符正确处理
- [ ] 插值变量正确替换
- [ ] 复数形式正确处理（如果有）

### 性能测试
- [ ] 语言切换响应迅速
- [ ] 大量数据渲染性能正常
- [ ] 内存使用正常

## 最佳实践

### 1. 始终使用翻译键

❌ 不好：
```vue
<el-button>保存</el-button>
```

✅ 好：
```vue
<el-button>{{ $t('common.save') }}</el-button>
```

### 2. 使用计算属性缓存

❌ 不好：
```typescript
const columns = [
  { label: t('school.name') }
]
```

✅ 好：
```typescript
const columns = computed(() => [
  { label: t('school.name') }
])
```

### 3. 提供上下文

❌ 不好：
```typescript
{
  msg1: '删除',
  msg2: '删除成功'
}
```

✅ 好：
```typescript
{
  delete: '删除',
  deleteSuccess: '删除成功',
  deleteConfirm: '确定要删除吗？'
}
```

### 4. 使用插值而非拼接

❌ 不好：
```typescript
t('hello') + ' ' + name
```

✅ 好：
```typescript
t('hello', { name })
```

## 扩展性

### 添加新语言

1. 创建新的翻译文件：
```typescript
// frontend/src/locales/ja.ts
export default {
  common: {
    save: '保存',
    // ...
  }
}
```

2. 更新配置：
```typescript
// frontend/src/locales/index.ts
import ja from './ja'

const i18n = createI18n({
  messages: {
    'zh-CN': zhCN,
    'zh-TW': zhTW,
    'en': en,
    'ja': ja, // 新增
  },
})
```

3. 更新语言切换器：
```vue
<el-dropdown-item command="ja">日本語</el-dropdown-item>
```

### 添加新模块

在翻译文件中添加新的模块：

```typescript
export default {
  // 现有模块
  common: { /* ... */ },
  school: { /* ... */ },
  
  // 新模块
  newModule: {
    title: '新模块',
    create: '创建',
    // ...
  }
}
```

## 维护指南

### 定期检查

1. 确保所有语言文件的键保持一致
2. 检查是否有遗漏的翻译
3. 更新文档

### 添加新功能时

1. 同时添加所有语言的翻译
2. 更新相关文档
3. 测试所有语言

### 修复 Bug 时

1. 检查是否影响多语言
2. 更新相关翻译
3. 测试所有语言

## 工具推荐

- [繁简转换工具](https://www.aies.cn/)
- [Google 翻译](https://translate.google.com/)
- [DeepL 翻译](https://www.deepl.com/)
- [i18n Ally (VS Code 插件)](https://marketplace.visualstudio.com/items?itemName=Lokalise.i18n-ally)

## 总结

多语言插件系统已完全就绪，包括：

✅ 完整的翻译文件（简体、繁体、英文）
✅ 便捷的 Composable
✅ 自动化改造脚本
✅ 详细的文档和示例
✅ 清晰的使用流程

现在可以开始应用到实际组件中了！

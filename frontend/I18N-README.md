# 多语言应用指南

## 快速开始

### 1. 查看示例

已创建的多语言示例文件：
- `src/views/schools/SchoolList.i18n.vue` - 完整的多语言应用示例
- `src/composables/useI18n.ts` - 多语言 composable

### 2. 手动改造组件

参考 `docs/i18n-implementation-guide.md` 中的详细说明。

### 3. 使用自动化脚本

```bash
# 安装依赖（如果需要）
npm install glob

# 处理单个文件
node scripts/apply-i18n.js "src/views/schools/SchoolList.vue"

# 处理整个目录
node scripts/apply-i18n.js "src/views/**/*.vue"

# 处理特定模块
node scripts/apply-i18n.js "src/views/classes/*.vue"
```

## 改造步骤

### 步骤 1: 备份

```bash
# 创建备份分支
git checkout -b feature/i18n-implementation
git add .
git commit -m "backup before i18n implementation"
```

### 步骤 2: 运行自动化脚本

```bash
# 先处理一个文件测试
node scripts/apply-i18n.js "src/views/schools/SchoolList.vue"

# 检查结果，如果正确则继续
node scripts/apply-i18n.js "src/views/**/*.vue"
```

### 步骤 3: 手动调整

自动化脚本无法处理的情况：

1. **动态文本**
   ```vue
   <!-- 需要手动改造 -->
   <span>{{ `共 ${total} 条` }}</span>
   
   <!-- 改为 -->
   <span>{{ $t('common.total', { total }) }}</span>
   ```

2. **复杂的确认对话框**
   ```typescript
   // 需要手动改造
   await ElMessageBox.confirm(
     `确定要删除学校"${school.name}"吗？删除后将同时删除该学校下的所有班级、教师和学生数据。`,
     '删除确认'
   )
   
   // 改为
   await ElMessageBox.confirm(
     t('school.deleteConfirm', { name: school.name }),
     t('common.deleteConfirm')
   )
   ```

3. **表格列配置**
   ```typescript
   // 需要使用计算属性
   const columns = computed(() => [
     { prop: 'id', label: 'ID' },
     { prop: 'name', label: t('school.name') },
   ])
   ```

### 步骤 4: 测试

```bash
# 启动开发服务器
npm run dev

# 测试所有语言
# 1. 切换到简体中文
# 2. 切换到繁体中文
# 3. 切换到英文
```

### 步骤 5: 提交

```bash
git add .
git commit -m "feat: apply i18n to all components"
```

## 组件改造清单

### 视图组件

- [ ] `src/views/schools/SchoolList.vue`
- [ ] `src/views/schools/SchoolForm.vue`
- [ ] `src/views/classes/ClassList.vue`
- [ ] `src/views/classes/ClassForm.vue`
- [ ] `src/views/classes/ClassDetailView.vue`
- [ ] `src/views/teachers/TeacherList.vue`
- [ ] `src/views/students/StudentList.vue`
- [ ] `src/views/students/StudentForm.vue`
- [ ] `src/views/groups/GroupList.vue`
- [ ] `src/views/groups/GroupDetail.vue`
- [ ] `src/views/groups/GroupForm.vue`
- [ ] `src/views/LoginView.vue`

### 通用组件

- [x] `src/components/common/AppHeader.vue` (已完成)
- [ ] `src/components/common/AppSidebar.vue`
- [ ] `src/components/common/DataTable.vue`
- [ ] `src/components/common/FormDialog.vue`
- [ ] `src/components/common/UserSelectDialog.vue`
- [ ] `src/components/common/PageHeader.vue`

## 常见问题

### Q1: 自动化脚本替换错误怎么办？

A: 使用 git 恢复：
```bash
git checkout -- src/views/schools/SchoolList.vue
```

### Q2: 如何添加新的翻译？

A: 同时更新三个文件：
1. `src/locales/zh-CN.ts`
2. `src/locales/zh-TW.ts`
3. `src/locales/en.ts`

### Q3: Element Plus 组件的文本如何翻译？

A: Element Plus 有自己的国际化系统，需要在 `main.ts` 中配置：

```typescript
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'
import zhTw from 'element-plus/dist/locale/zh-tw.mjs'
import en from 'element-plus/dist/locale/en.mjs'

// 根据当前语言选择
const locale = localStorage.getItem('locale') || 'zh-CN'
const elementLocale = {
  'zh-CN': zhCn,
  'zh-TW': zhTw,
  'en': en
}[locale]

app.use(ElementPlus, { locale: elementLocale })
```

### Q4: 如何处理日期时间格式？

A: 使用 vue-i18n 的日期时间格式化：

```typescript
// 在 locales/index.ts 中配置
const i18n = createI18n({
  // ...
  datetimeFormats: {
    'zh-CN': {
      short: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      },
      long: {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      }
    }
  }
})

// 在组件中使用
{{ $d(new Date(), 'long') }}
```

## 性能优化

### 1. 使用计算属性缓存翻译

```typescript
const title = computed(() => t('school.title'))
```

### 2. 避免在循环中调用 t()

```typescript
// 不好
<div v-for="item in items">
  {{ t('common.name') }}: {{ item.name }}
</div>

// 好
const nameLabel = computed(() => t('common.name'))
<div v-for="item in items">
  {{ nameLabel }}: {{ item.name }}
</div>
```

### 3. 懒加载语言包

```typescript
// 按需加载语言包
const loadLocale = async (locale: string) => {
  const messages = await import(`./locales/${locale}.ts`)
  i18n.global.setLocaleMessage(locale, messages.default)
}
```

## 参考资源

- [完整实现指南](docs/i18n-implementation-guide.md)
- [繁体中文支持文档](docs/i18n-traditional-chinese.md)
- [Vue I18n 官方文档](https://vue-i18n.intlify.dev/)
- [Element Plus 国际化](https://element-plus.org/zh-CN/guide/i18n.html)

## 联系支持

如有问题，请查看文档或联系开发团队。

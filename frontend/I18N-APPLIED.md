# 多语言已应用组件清单

## ✅ 已完成改造的组件

### 视图组件 (3/12)

1. **SchoolList.vue** - 学校列表
   - 页面标题、按钮文本
   - 搜索表单
   - 表格列标题（使用 computed）
   - 删除确认对话框
   - 成功/失败消息

2. **ClassList.vue** - 班级列表
   - 页面标题、按钮文本
   - 搜索表单（包含学校选择器）
   - 表格列标题（使用 computed）
   - 两步删除确认流程
   - 成功/失败消息

3. **LoginView.vue** - 登录页面
   - 系统标题和副标题
   - Token 输入提示
   - 按钮文本
   - 验证消息
   - 使用提示

### 通用组件 (1/5)

1. **AppSidebar.vue** - 侧边栏导航
   - 所有菜单项文本
   - 支持折叠状态

## 📋 待改造组件

### 视图组件 (9 个)
- [ ] SchoolForm.vue
- [ ] ClassForm.vue
- [ ] ClassDetailView.vue
- [ ] TeacherList.vue
- [ ] StudentList.vue
- [ ] StudentForm.vue
- [ ] GroupList.vue
- [ ] GroupDetail.vue
- [ ] GroupForm.vue

### 通用组件 (4 个)
- [ ] DataTable.vue
- [ ] FormDialog.vue
- [ ] UserSelectDialog.vue
- [ ] PageHeader.vue

## 🎯 改造要点

### 1. 导入和初始化
```typescript
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
```

### 2. 模板中使用
```vue
<!-- 静态文本 -->
<el-button>{{ $t('common.save') }}</el-button>

<!-- 动态属性 -->
<el-input :placeholder="$t('school.searchPlaceholder')" />

<!-- 带参数 -->
<span>{{ $t('common.total', { total: 100 }) }}</span>
```

### 3. 脚本中使用
```typescript
// 消息提示
ElMessage.success(t('school.createSuccess'))

// 确认对话框
await ElMessageBox.confirm(
  t('school.deleteConfirm', { name: school.name }),
  t('common.deleteConfirm')
)
```

### 4. 动态配置
```typescript
// 表格列配置必须使用 computed
const columns = computed(() => [
  { prop: 'name', label: t('school.name') },
  { prop: 'created_at', label: t('common.createdAt') }
])
```

## 🔑 常用翻译键

### 通用操作
- `common.confirm` - 确认
- `common.cancel` - 取消
- `common.save` - 保存
- `common.edit` - 编辑
- `common.delete` - 删除
- `common.create` - 创建
- `common.search` - 搜索
- `common.reset` - 重置

### 消息提示
- `common.success` - 操作成功
- `common.failed` - 操作失败
- `common.loading` - 加载中...
- `common.noData` - 暂无数据

### 导航菜单
- `nav.schools` - 学校管理
- `nav.classes` - 班级管理
- `nav.teachers` - 教师管理
- `nav.students` - 学生管理
- `nav.groups` - 小组管理

### 模块特定
- `school.*` - 学校相关
- `class.*` - 班级相关
- `teacher.*` - 教师相关
- `student.*` - 学生相关
- `group.*` - 小组相关
- `auth.*` - 认证相关

## 🧪 测试清单

改造完成后，请进行以下测试：

1. **语言切换测试**
   - [ ] 切换到简体中文，检查所有文本
   - [ ] 切换到繁体中文，检查所有文本
   - [ ] 切换到英文，检查所有文本

2. **功能测试**
   - [ ] 创建操作正常
   - [ ] 编辑操作正常
   - [ ] 删除操作正常（包含确认对话框）
   - [ ] 搜索功能正常
   - [ ] 分页功能正常

3. **消息测试**
   - [ ] 成功消息显示正确
   - [ ] 错误消息显示正确
   - [ ] 警告消息显示正确

4. **表格测试**
   - [ ] 列标题正确显示
   - [ ] 切换语言后列标题自动更新
   - [ ] 数据格式化正确

## 📚 参考文档

- `I18N-README.md` - 系统总览
- `I18N-QUICK-REFERENCE.md` - 快速参考
- `I18N-CHECKLIST.md` - 改造检查清单
- `i18n-implementation-guide.md` - 详细指南
- `i18n-implementation-complete.md` - 完成报告

## 🚀 下一步

1. 根据业务优先级，选择下一个要改造的组件
2. 参考已改造的组件进行改造
3. 如需添加新的翻译键，同时更新 `zh-CN.ts`、`zh-TW.ts` 和 `en.ts`
4. 改造完成后更新本清单

## 💡 提示

- 优先改造用户最常使用的页面
- 表单组件改造时注意验证消息的多语言支持
- 详情页面改造时注意字段标签的多语言支持
- 保持翻译键的命名一致性和层次结构

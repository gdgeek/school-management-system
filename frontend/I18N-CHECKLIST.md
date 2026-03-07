# 多语言应用检查清单

## 开始前

- [ ] 阅读快速参考：`I18N-QUICK-REFERENCE.md`
- [ ] 查看示例文件：`src/views/schools/SchoolList.i18n.vue`
- [ ] 备份代码：`git checkout -b feature/i18n`

## 改造单个组件

### 1. 准备工作
- [ ] 打开要改造的组件
- [ ] 识别所有硬编码的中文文本
- [ ] 确定需要的翻译键

### 2. 模板改造
- [ ] 替换按钮文本：`保存` → `{{ $t('common.save') }}`
- [ ] 替换标签文本：`label="名称"` → `:label="$t('common.name')"`
- [ ] 替换占位符：`placeholder="输入..."` → `:placeholder="$t('...')"`
- [ ] 替换标题文本：`<h1>标题</h1>` → `<h1>{{ $t('...') }}</h1>`

### 3. 脚本改造
- [ ] 添加 import：`import { useI18n } from 'vue-i18n'`
- [ ] 添加 composable：`const { t } = useI18n()`
- [ ] 替换提示消息：`ElMessage.success('成功')` → `ElMessage.success(t('common.success'))`
- [ ] 替换确认对话框文本
- [ ] 更新表格列配置（使用 computed）

### 4. 测试
- [ ] 切换到简体中文，检查显示
- [ ] 切换到繁体中文，检查显示
- [ ] 切换到英文，检查显示
- [ ] 测试所有功能正常

### 5. 提交
- [ ] 检查代码质量
- [ ] 提交更改：`git commit -m "feat: apply i18n to ComponentName"`

## 批量改造

### 1. 准备
- [ ] 备份代码
- [ ] 确认自动化脚本可用：`node scripts/apply-i18n.js --help`

### 2. 执行
- [ ] 先处理一个文件测试：`node scripts/apply-i18n.js "src/views/schools/SchoolList.vue"`
- [ ] 检查结果是否正确
- [ ] 批量处理：`node scripts/apply-i18n.js "src/views/**/*.vue"`

### 3. 手动调整
- [ ] 检查动态文本是否正确
- [ ] 检查表格列配置
- [ ] 检查确认对话框
- [ ] 检查复杂逻辑

### 4. 测试
- [ ] 测试所有改造的组件
- [ ] 测试所有语言切换
- [ ] 测试所有功能

### 5. 提交
- [ ] 提交更改：`git commit -m "feat: apply i18n to all components"`

## 组件改造清单

### 视图组件
- [ ] SchoolList.vue
- [ ] SchoolForm.vue
- [ ] ClassList.vue
- [ ] ClassForm.vue
- [ ] ClassDetailView.vue
- [ ] TeacherList.vue
- [ ] StudentList.vue
- [ ] StudentForm.vue
- [ ] GroupList.vue
- [ ] GroupDetail.vue
- [ ] GroupForm.vue
- [ ] LoginView.vue

### 通用组件
- [x] AppHeader.vue（已完成）
- [ ] AppSidebar.vue
- [ ] DataTable.vue
- [ ] FormDialog.vue
- [ ] UserSelectDialog.vue
- [ ] PageHeader.vue

## 常见问题检查

### 模板问题
- [ ] 所有硬编码文本都已替换
- [ ] 属性绑定使用了 `:` 前缀
- [ ] 插值正确使用 `{{ }}`

### 脚本问题
- [ ] 已导入 useI18n
- [ ] 已声明 const { t } = useI18n()
- [ ] 表格列使用了 computed
- [ ] 动态文本使用了插值

### 功能问题
- [ ] 所有按钮可点击
- [ ] 所有表单可提交
- [ ] 所有提示正确显示
- [ ] 所有确认对话框正常

### 显示问题
- [ ] 文本没有溢出
- [ ] 长文本正确换行
- [ ] 特殊字符正确显示
- [ ] 布局没有错乱

## 测试清单

### 功能测试
- [ ] 创建功能
- [ ] 编辑功能
- [ ] 删除功能
- [ ] 搜索功能
- [ ] 分页功能

### 语言测试
- [ ] 简体中文完整显示
- [ ] 繁体中文完整显示
- [ ] 英文完整显示
- [ ] 语言切换即时生效
- [ ] 刷新后语言保持

### 边界测试
- [ ] 空数据显示
- [ ] 长文本显示
- [ ] 特殊字符显示
- [ ] 错误提示显示

## 完成标准

### 代码质量
- [ ] 没有硬编码的中文文本
- [ ] 所有翻译键都存在
- [ ] 代码格式正确
- [ ] 没有 console 错误

### 功能完整
- [ ] 所有功能正常
- [ ] 所有提示正确
- [ ] 所有语言可切换
- [ ] 用户体验良好

### 文档更新
- [ ] 更新相关文档
- [ ] 添加必要注释
- [ ] 更新 README

## 最终检查

- [ ] 所有组件已改造
- [ ] 所有测试已通过
- [ ] 代码已提交
- [ ] 文档已更新
- [ ] 团队已通知

## 完成！🎉

恭喜！多语言功能已成功应用。

下一步：
- 收集用户反馈
- 持续优化翻译
- 考虑添加更多语言

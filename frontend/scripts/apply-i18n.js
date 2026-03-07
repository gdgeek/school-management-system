#!/usr/bin/env node

/**
 * 批量应用多语言脚本
 * 
 * 用法：
 * node scripts/apply-i18n.js [file-pattern]
 * 
 * 示例：
 * node scripts/apply-i18n.js "src/views/**\/*.vue"
 */

const fs = require('fs')
const path = require('path')
const glob = require('glob')

// 常见文本替换映射
const replacements = {
  // 通用按钮
  '>保存<': ">{{ $t('common.save') }}<",
  '>取消<': ">{{ $t('common.cancel') }}<",
  '>确定<': ">{{ $t('common.confirm') }}<",
  '>确认<': ">{{ $t('common.confirm') }}<",
  '>删除<': ">{{ $t('common.delete') }}<",
  '>编辑<': ">{{ $t('common.edit') }}<",
  '>创建<': ">{{ $t('common.create') }}<",
  '>搜索<': ">{{ $t('common.search') }}<",
  '>重置<': ">{{ $t('common.reset') }}<",
  '>提交<': ">{{ $t('common.submit') }}<",
  '>返回<': ">{{ $t('common.back') }}<",
  '>关闭<': ">{{ $t('common.close') }}<",
  
  // 学校相关
  '>学校管理<': ">{{ $t('school.title') }}<",
  '>创建学校<': ">{{ $t('school.create') }}<",
  '>编辑学校<': ">{{ $t('school.edit') }}<",
  '>学校名称<': ">{{ $t('school.name') }}<",
  '>学校简介<': ">{{ $t('school.info') }}<",
  '>校长<': ">{{ $t('school.principal') }}<",
  '>学校管理员<': ">{{ $t('school.principal') }}<",
  
  // 班级相关
  '>班级管理<': ">{{ $t('class.title') }}<",
  '>创建班级<': ">{{ $t('class.create') }}<",
  '>编辑班级<': ">{{ $t('class.edit') }}<",
  '>班级名称<': ">{{ $t('class.name') }}<",
  '>班级简介<': ">{{ $t('class.info') }}<",
  '>所属学校<': ">{{ $t('class.school') }}<",
  
  // 教师相关
  '>教师管理<': ">{{ $t('teacher.title') }}<",
  '>添加教师<': ">{{ $t('teacher.add') }}<",
  '>新增教师<': ">{{ $t('teacher.add') }}<",
  '>移除教师<': ">{{ $t('teacher.remove') }}<",
  
  // 学生相关
  '>学生管理<': ">{{ $t('student.title') }}<",
  '>添加学生<': ">{{ $t('student.add') }}<",
  '>新增学生<': ">{{ $t('student.add') }}<",
  '>移除学生<': ">{{ $t('student.remove') }}<",
  
  // 小组相关
  '>小组管理<': ">{{ $t('group.title') }}<",
  '>创建小组<': ">{{ $t('group.create') }}<",
  '>编辑小组<': ">{{ $t('group.edit') }}<",
  '>小组名称<': ">{{ $t('group.name') }}<",
  '>小组简介<': ">{{ $t('group.info') }}<",
  
  // 表单标签
  'label="搜索"': "label=\"{{ $t('common.search') }}\"",
  'label="名称"': "label=\"{{ $t('common.name') }}\"",
  'label="描述"': "label=\"{{ $t('common.description') }}\"",
  'label="创建时间"': "label=\"{{ $t('common.createdAt') }}\"",
  'label="更新时间"': "label=\"{{ $t('common.updatedAt') }}\"",
  'label="操作"': "label=\"{{ $t('common.actions') }}\"",
  
  // 占位符
  'placeholder="输入学校名称"': "placeholder=\"{{ $t('school.searchPlaceholder') }}\"",
  'placeholder="输入班级名称"': "placeholder=\"{{ $t('class.searchPlaceholder') }}\"",
  'placeholder="搜索"': "placeholder=\"{{ $t('common.search') }}\"",
}

// 脚本中的替换（需要添加 import）
const scriptReplacements = {
  "ElMessage.success('操作成功')": "ElMessage.success(t('common.success'))",
  "ElMessage.success('删除成功')": "ElMessage.success(t('common.deleteSuccess'))",
  "ElMessage.success('创建成功')": "ElMessage.success(t('common.createSuccess'))",
  "ElMessage.success('更新成功')": "ElMessage.success(t('common.updateSuccess'))",
  "ElMessage.error('操作失败')": "ElMessage.error(t('common.failed'))",
  "ElMessage.error('删除失败')": "ElMessage.error(t('common.deleteFailed'))",
}

function processFile(filePath) {
  console.log(`处理文件: ${filePath}`)
  
  let content = fs.readFileSync(filePath, 'utf8')
  let modified = false
  
  // 应用模板替换
  for (const [oldText, newText] of Object.entries(replacements)) {
    if (content.includes(oldText)) {
      content = content.replace(new RegExp(oldText, 'g'), newText)
      modified = true
    }
  }
  
  // 应用脚本替换
  for (const [oldText, newText] of Object.entries(scriptReplacements)) {
    if (content.includes(oldText)) {
      content = content.replace(new RegExp(oldText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), newText)
      modified = true
    }
  }
  
  // 检查是否需要添加 useI18n import
  if (modified && content.includes("$t('") && !content.includes("import { useI18n }")) {
    // 查找 script setup 标签
    const scriptMatch = content.match(/<script setup.*?>/s)
    if (scriptMatch) {
      const insertPos = scriptMatch.index + scriptMatch[0].length
      const importStatement = "\nimport { useI18n } from 'vue-i18n'\n\nconst { t } = useI18n()\n"
      content = content.slice(0, insertPos) + importStatement + content.slice(insertPos)
    }
  }
  
  if (modified) {
    fs.writeFileSync(filePath, content, 'utf8')
    console.log(`✓ 已更新: ${filePath}`)
    return true
  } else {
    console.log(`- 无需更新: ${filePath}`)
    return false
  }
}

function main() {
  const pattern = process.argv[2] || 'src/views/**/*.vue'
  const files = glob.sync(pattern, { cwd: process.cwd() })
  
  console.log(`找到 ${files.length} 个文件`)
  console.log('开始处理...\n')
  
  let updatedCount = 0
  files.forEach(file => {
    if (processFile(file)) {
      updatedCount++
    }
  })
  
  console.log(`\n完成！共更新 ${updatedCount} 个文件`)
  console.log('\n注意：')
  console.log('1. 请检查自动替换的结果是否正确')
  console.log('2. 某些复杂的文本可能需要手动调整')
  console.log('3. 确保所有翻译键在语言文件中都有定义')
}

if (require.main === module) {
  main()
}

module.exports = { processFile, replacements }

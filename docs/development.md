# 开发指南

## 开发环境搭建

### 前置条件

- Node.js 18+（推荐使用 [nvm](https://github.com/nvm-sh/nvm) 管理版本）
- PHP 8.1+
- Composer 2.0+
- MySQL 8.0+（可使用主系统的数据库）
- Redis 6.0+（可使用主系统的 Redis）
- Git

### 克隆代码

```bash
git clone <repository-url>
cd school-management-system
```

### 前端开发环境

```bash
cd frontend

# 安装依赖
npm install

# 复制开发环境配置
cp .env.development .env.local
# 根据需要修改 .env.local 中的 API 地址

# 启动开发服务器（热重载）
npm run dev
# 访问 http://localhost:5173
```

前端开发环境配置（`.env.development`）：

```dotenv
VITE_API_BASE_URL=http://localhost:8084/api
VITE_APP_TITLE=学校管理系统（开发）
```

### 后端开发环境

```bash
cd backend

# 安装 PHP 依赖
composer install

# 复制环境配置
cp .env.example .env
# 编辑 .env 填写本地数据库和 Redis 配置

# 验证数据库连接
php migrations/verify_tables.php

# 启动 PHP 内置服务器（开发用）
php -S localhost:8083 -t public/
# 或使用 PHP-FPM + Nginx
```

---

## 前端开发工作流

### 项目结构约定

```
src/
├── api/          # 每个模块一个文件，如 school.ts、class.ts
├── components/   # 可复用组件，按功能分子目录
├── stores/       # Pinia store，每个模块一个文件
├── views/        # 页面组件，按模块分子目录
└── types/        # TypeScript 类型定义
```

### 添加新 API 接口

在 `src/api/` 目录下对应模块文件中添加：

```typescript
// src/api/school.ts
import request from '@/utils/request'
import type { School, PaginatedResponse } from '@/types'

export const getSchools = (params: { page?: number; name?: string }) =>
  request.get<PaginatedResponse<School>>('/schools', { params })

export const createSchool = (data: Partial<School>) =>
  request.post<School>('/schools', data)
```

### 添加新页面

1. 在 `src/views/` 下创建视图组件
2. 在 `src/router/index.ts` 中注册路由
3. 在侧边栏组件中添加导航链接

### 状态管理规范

```typescript
// src/stores/school.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getSchools } from '@/api/school'

export const useSchoolStore = defineStore('school', () => {
  const schools = ref([])
  const loading = ref(false)

  async function fetchSchools(params = {}) {
    loading.value = true
    try {
      const res = await getSchools(params)
      schools.value = res.data.items
    } finally {
      loading.value = false
    }
  }

  return { schools, loading, fetchSchools }
})
```

### 常用命令

```bash
# 开发服务器
npm run dev

# 类型检查
npm run type-check

# 代码格式化
npm run format

# 构建生产版本
npm run build

# 预览构建结果
npm run preview
```

---

## 后端开发工作流

### 添加新 API 端点

1. 在 `src/Controller/` 创建或修改控制器
2. 在 `src/Service/` 实现业务逻辑
3. 在 `src/Repository/` 实现数据访问
4. 在路由配置中注册端点

**控制器示例**:

```php
<?php
// src/Controller/ExampleController.php

namespace App\Controller;

use App\Service\ExampleService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExampleController
{
    public function __construct(
        private ExampleService $service,
        private ResponseHelper $response
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $data = $this->service->getList($params);
        return $this->response->success($data);
    }
}
```

**服务层示例**:

```php
<?php
// src/Service/ExampleService.php

namespace App\Service;

use App\Repository\ExampleRepository;
use App\Helper\CacheHelper;

class ExampleService
{
    public function __construct(
        private ExampleRepository $repository,
        private CacheHelper $cache
    ) {}

    public function getList(array $params): array
    {
        $cacheKey = 'example:list:' . md5(serialize($params));
        return $this->cache->remember($cacheKey, 300, function () use ($params) {
            return $this->repository->findAll($params);
        });
    }
}
```

### 常用命令

```bash
# 安装依赖
composer install

# 更新依赖
composer update

# 代码风格检查
composer cs-check

# 自动修复代码风格
composer cs-fix

# 运行测试
composer test

# 运行单元测试
./vendor/bin/phpunit tests/Unit

# 运行集成测试
./vendor/bin/phpunit tests/Integration
```

---

## 代码风格和规范

### 前端规范

- 使用 TypeScript，避免 `any` 类型
- 组件使用 `<script setup>` 语法（Composition API）
- 组件名使用 PascalCase（如 `SchoolList.vue`）
- 函数和变量使用 camelCase
- 常量使用 UPPER_SNAKE_CASE
- 使用 JSDoc 注释公共函数

```typescript
/**
 * 格式化日期为本地化字符串
 * @param date - ISO 日期字符串
 * @returns 格式化后的日期字符串
 */
export function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('zh-CN')
}
```

### 后端规范

- 遵循 PSR-12 代码风格
- 使用 PHPDoc 注释所有公共方法
- 类名使用 PascalCase
- 方法名使用 camelCase
- 常量使用 UPPER_SNAKE_CASE
- 所有方法必须声明返回类型

```php
/**
 * 根据 ID 获取学校信息
 *
 * @param int $id 学校 ID
 * @return array|null 学校数据，不存在时返回 null
 * @throws \App\Exception\NotFoundException 学校不存在时抛出
 */
public function findById(int $id): ?array
{
    // ...
}
```

---

## Git 工作流

### 分支策略

```
main          ← 生产环境代码，只接受 merge request
develop       ← 开发主分支
feature/*     ← 功能开发分支
bugfix/*      ← Bug 修复分支
hotfix/*      ← 紧急修复分支（从 main 分出）
```

### 提交信息规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 格式：

```
<type>(<scope>): <description>

[optional body]
```

类型（type）：
- `feat`: 新功能
- `fix`: Bug 修复
- `docs`: 文档更新
- `style`: 代码格式（不影响功能）
- `refactor`: 重构
- `test`: 测试相关
- `chore`: 构建/工具相关

示例：
```
feat(school): add school search by name
fix(auth): fix token expiry check
docs(api): update school API documentation
```

### 开发流程

```bash
# 1. 从 develop 创建功能分支
git checkout develop
git pull origin develop
git checkout -b feature/add-school-export

# 2. 开发并提交
git add .
git commit -m "feat(school): add CSV export for school list"

# 3. 推送并创建 Merge Request
git push origin feature/add-school-export
# 在 GitLab/GitHub 创建 MR，指向 develop 分支
```

---

## 测试方法

### 前端测试

```bash
cd frontend

# 运行单元测试（单次）
npm run test:unit -- --run

# 运行测试并查看覆盖率
npm run test:coverage
```

### 后端测试

```bash
cd backend

# 运行所有测试
./vendor/bin/phpunit

# 运行单元测试
./vendor/bin/phpunit tests/Unit

# 运行集成测试（需要数据库连接）
./vendor/bin/phpunit tests/Integration

# 生成覆盖率报告
./vendor/bin/phpunit --coverage-html coverage/
```

### API 手动测试

使用 curl 测试 API：

```bash
# 登录获取 token
TOKEN=$(curl -s -X POST http://localhost:8084/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}' \
  | jq -r '.data.token')

# 获取学校列表
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8084/api/schools
```

---

## 调试技巧

### 前端调试

- 使用 Vue DevTools 浏览器扩展查看组件状态和 Pinia store
- 在 `vite.config.ts` 中开启 source map
- 使用 `console.log` 或 Vue DevTools 的时间线功能

### 后端调试

- 查看 `runtime/logs/` 目录下的日志文件
- 在 `.env` 中设置 `APP_DEBUG=true` 开启详细错误信息
- 使用 Xdebug 进行断点调试（需要安装 PHP Xdebug 扩展）

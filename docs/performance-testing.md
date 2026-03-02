# 性能测试指南

## 概述

本文档描述学校管理系统的性能测试方案，包括测试工具、测试脚本、基准指标和执行方法。

性能测试脚本位于 `tests/performance/` 目录，主要使用 [k6](https://k6.io/) 作为测试工具，同时提供基于 curl 的轻量级脚本作为备选方案。

## 性能基准指标

根据需求文档（需求 14），系统需满足以下性能要求：

| 指标 | 阈值 | 说明 |
|------|------|------|
| 列表页响应时间 | < 2s (P95) | 包含分页的列表查询 |
| 详情页响应时间 | < 1s (P95) | 单条记录查询 |
| 创建操作响应时间 | < 1.5s (P95) | POST 请求 |
| 更新操作响应时间 | < 1.5s (P95) | PUT 请求 |
| 删除操作响应时间 | < 1.5s (P95) | DELETE 请求 |
| 健康检查响应时间 | < 500ms | /health 端点 |
| 错误率 | < 5% | 所有请求的失败比例 |
| 并发用户支持 | ≥ 50 | 同时在线用户数 |

## 前置条件

### 安装 k6

```bash
# macOS
brew install k6

# Ubuntu/Debian
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Docker
docker pull grafana/k6
```

### 获取认证令牌

性能测试需要有效的 JWT 令牌。通过以下方式获取：

```bash
# 通过认证 API 获取令牌
curl -s -X POST http://localhost:8084/api/auth/verify \
  -H "Content-Type: application/json" \
  -d '{"token": "<session_token>"}' | jq -r '.data.token'

# 将令牌设置为环境变量
export AUTH_TOKEN="<your-jwt-token>"
```

### 确保服务运行

```bash
# 启动所有服务
cd school-management-system
docker-compose up -d

# 验证服务状态
curl http://localhost:8084/health
curl http://localhost:3002
```

## 测试脚本说明

### 1. API 端点测试 (`api-endpoints.js`)

测试所有关键 API 端点的响应时间和可靠性。

覆盖端点：
- `GET /health` — 健康检查
- `GET /api/schools` — 学校列表 + 详情
- `GET /api/classes` — 班级列表 + 详情
- `GET /api/teachers` — 教师列表
- `GET /api/students` — 学生列表
- `GET /api/groups` — 小组列表 + 详情

### 2. CRUD 操作测试 (`crud-operations.js`)

测试完整的增删改查生命周期，每个虚拟用户执行：创建 → 读取 → 更新 → 删除。

覆盖资源：
- 学校（School）完整 CRUD
- 小组（Group）完整 CRUD

### 3. 分页性能测试 (`pagination-load.js`)

测试分页查询在不同条件下的性能：
- 不同页面大小（10, 20, 50, 100）
- 深层分页（第 1, 2, 5, 10 页）
- 搜索 + 分页组合
- 最大页面大小（100 条/页）

### 4. 并发用户测试 (`concurrent-users.js`)

模拟真实的多用户并发场景：
- **浏览用户**（20 VU）：只读操作，浏览列表和详情
- **管理员用户**（5 VU）：执行创建和删除操作
- **突发流量**（50 VU）：模拟突然的流量高峰

### 5. Shell 负载测试 (`load-test.sh`)

不依赖 k6 的轻量级测试脚本，使用 curl 实现，适合快速验证。

## 运行测试

### 使用 k6

```bash
cd school-management-system/tests/performance

# 冒烟测试（快速验证，1 个虚拟用户）
k6 run -e AUTH_TOKEN=$AUTH_TOKEN -e TEST_TYPE=smoke api-endpoints.js

# 负载测试（10 个虚拟用户，持续 2 分钟）
k6 run -e AUTH_TOKEN=$AUTH_TOKEN -e TEST_TYPE=load api-endpoints.js

# 压力测试（最高 100 个虚拟用户）
k6 run -e AUTH_TOKEN=$AUTH_TOKEN -e TEST_TYPE=stress api-endpoints.js

# CRUD 操作测试
k6 run -e AUTH_TOKEN=$AUTH_TOKEN -e TEST_TYPE=load crud-operations.js

# 分页性能测试
k6 run -e AUTH_TOKEN=$AUTH_TOKEN -e TEST_TYPE=load pagination-load.js

# 并发用户场景测试
k6 run -e AUTH_TOKEN=$AUTH_TOKEN concurrent-users.js
```

### 使用 Docker 运行 k6

```bash
docker run --rm -i --network host \
  -v $(pwd)/tests/performance:/scripts \
  -e AUTH_TOKEN=$AUTH_TOKEN \
  -e TEST_TYPE=load \
  grafana/k6 run /scripts/api-endpoints.js
```

### 自定义目标服务器

```bash
# 测试远程服务器
k6 run -e BASE_URL=http://staging-server:8084 \
       -e AUTH_TOKEN=$AUTH_TOKEN \
       -e TEST_TYPE=load \
       api-endpoints.js
```

### 使用 Shell 脚本

```bash
cd school-management-system/tests/performance

# 默认配置（localhost:8084，5 并发）
AUTH_TOKEN=$AUTH_TOKEN ./load-test.sh

# 自定义配置
AUTH_TOKEN=$AUTH_TOKEN ./load-test.sh http://localhost:8084 10 30
```

## 输出结果解读

### k6 输出示例

```
✓ schools list status 200
✓ schools list < 2s
✓ school detail status 200
✓ school detail < 1s

http_req_duration..........: avg=156ms  min=12ms  max=890ms  p(95)=450ms
http_req_failed............: 0.00%
list_duration..............: avg=180ms  min=45ms  max=890ms  p(95)=520ms
detail_duration............: avg=95ms   min=12ms  max=340ms  p(95)=210ms
```

关键指标：
- **p(95)**: 95% 的请求在此时间内完成（主要关注指标）
- **avg**: 平均响应时间
- **max**: 最大响应时间（可能受网络波动影响）
- **http_req_failed**: 请求失败率

### 结果导出

```bash
# 导出为 JSON
k6 run --out json=results.json -e AUTH_TOKEN=$AUTH_TOKEN api-endpoints.js

# 导出为 CSV
k6 run --out csv=results.csv -e AUTH_TOKEN=$AUTH_TOKEN api-endpoints.js
```

## 前端性能基准

前端加载性能可通过浏览器开发者工具或 Lighthouse 测量：

| 指标 | 目标值 | 说明 |
|------|--------|------|
| First Contentful Paint (FCP) | < 1.5s | 首次内容绘制 |
| Largest Contentful Paint (LCP) | < 2.5s | 最大内容绘制 |
| Time to Interactive (TTI) | < 3.5s | 可交互时间 |
| Total Bundle Size (gzipped) | < 500KB | 压缩后的总包大小 |

### 使用 Lighthouse 测试前端

```bash
# 安装 Lighthouse CLI
npm install -g lighthouse

# 运行测试
lighthouse http://localhost:3002 --output=json --output-path=./lighthouse-report.json

# 生成 HTML 报告
lighthouse http://localhost:3002 --output=html --output-path=./lighthouse-report.html
```

## 数据库查询性能

系统已实现以下数据库优化（参见 `QueryOptimizer.php`）：

- **分页查询**: 所有列表接口使用 LIMIT/OFFSET 分页，每页最多 100 条
- **Eager Loading**: 关联数据通过 JOIN 查询避免 N+1 问题
- **索引优化**: 关键查询字段已建立索引
- **Redis 缓存**: 频繁访问的数据使用 Redis 缓存

可通过 MySQL 慢查询日志监控数据库性能：

```sql
-- 启用慢查询日志
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';

-- 查看慢查询
SHOW GLOBAL STATUS LIKE 'Slow_queries';
```

## 持续性能监控

建议在 CI/CD 流程中集成性能测试：

```yaml
# GitHub Actions 示例
performance-test:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - name: Start services
      run: docker-compose up -d
    - name: Install k6
      run: |
        sudo gpg -k
        sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
          --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
        echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
          | sudo tee /etc/apt/sources.list.d/k6.list
        sudo apt-get update && sudo apt-get install k6
    - name: Run smoke tests
      run: k6 run -e TEST_TYPE=smoke tests/performance/api-endpoints.js
```

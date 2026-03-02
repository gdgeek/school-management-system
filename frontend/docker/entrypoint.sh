#!/bin/sh
# ============================================================
# 运行时环境变量注入
# 将 API_BASE_URL 和 MAIN_SYSTEM_URL 写入前端可读的配置文件
# ============================================================

cat > /usr/share/nginx/html/env-config.js << EOF
window.__ENV__ = {
  API_BASE_URL: "${API_BASE_URL:-http://localhost:8084}",
  MAIN_SYSTEM_URL: "${MAIN_SYSTEM_URL:-http://localhost:8080}"
};
EOF

echo "Frontend env-config.js generated:"
echo "  API_BASE_URL=${API_BASE_URL:-http://localhost:8084}"
echo "  MAIN_SYSTEM_URL=${MAIN_SYSTEM_URL:-http://localhost:8080}"

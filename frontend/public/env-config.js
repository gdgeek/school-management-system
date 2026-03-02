// 本地开发环境配置 — Docker 部署时会被 entrypoint.sh 覆盖
window.__ENV__ = {
  API_BASE_URL: "http://localhost:8084",
  MAIN_SYSTEM_URL: "http://localhost:8080"
};

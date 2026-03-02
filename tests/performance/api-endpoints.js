/**
 * k6 Performance Test - API Endpoints
 *
 * Tests all key API endpoints for response time and reliability.
 * Validates against the performance requirements (Requirement 14):
 *   - List pages: < 2s response time
 *   - Detail pages: < 1s response time
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=smoke api-endpoints.js
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=load api-endpoints.js
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=stress api-endpoints.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, getHeaders, getStages, THRESHOLDS } from './config.js';

// Custom metrics
const errorRate = new Rate('errors');
const listDuration = new Trend('list_duration', true);
const detailDuration = new Trend('detail_duration', true);
const createDuration = new Trend('create_duration', true);

export const options = {
  stages: getStages(),
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.05'],
    errors: ['rate<0.1'],
    list_duration: [`p(95)<${THRESHOLDS.listPage}`],
    detail_duration: [`p(95)<${THRESHOLDS.detailPage}`],
  },
};

const headers = getHeaders();

export default function () {
  group('Health Check', () => {
    const res = http.get(`${BASE_URL}/health`, { headers });
    check(res, {
      'health status 200': (r) => r.status === 200,
      'health response < 500ms': (r) => r.timings.duration < THRESHOLDS.healthCheck,
    }) || errorRate.add(1);
  });

  group('Schools API', () => {
    // List schools
    const listRes = http.get(`${BASE_URL}/api/schools?page=1&pageSize=20`, { headers });
    listDuration.add(listRes.timings.duration);
    check(listRes, {
      'schools list status 200': (r) => r.status === 200,
      'schools list < 2s': (r) => r.timings.duration < THRESHOLDS.listPage,
      'schools list has data': (r) => {
        try { return JSON.parse(r.body).code === 200; } catch { return false; }
      },
    }) || errorRate.add(1);

    // Get first school detail if available
    try {
      const body = JSON.parse(listRes.body);
      const items = body.data?.items || body.data || [];
      if (items.length > 0) {
        const schoolId = items[0].id;
        const detailRes = http.get(`${BASE_URL}/api/schools/${schoolId}`, { headers });
        detailDuration.add(detailRes.timings.duration);
        check(detailRes, {
          'school detail status 200': (r) => r.status === 200,
          'school detail < 1s': (r) => r.timings.duration < THRESHOLDS.detailPage,
        }) || errorRate.add(1);
      }
    } catch (_) { /* skip if no data */ }
  });

  group('Classes API', () => {
    const listRes = http.get(`${BASE_URL}/api/classes?page=1&pageSize=20`, { headers });
    listDuration.add(listRes.timings.duration);
    check(listRes, {
      'classes list status 200': (r) => r.status === 200,
      'classes list < 2s': (r) => r.timings.duration < THRESHOLDS.listPage,
    }) || errorRate.add(1);

    try {
      const body = JSON.parse(listRes.body);
      const items = body.data?.items || body.data || [];
      if (items.length > 0) {
        const classId = items[0].id;
        const detailRes = http.get(`${BASE_URL}/api/classes/${classId}`, { headers });
        detailDuration.add(detailRes.timings.duration);
        check(detailRes, {
          'class detail status 200': (r) => r.status === 200,
          'class detail < 1s': (r) => r.timings.duration < THRESHOLDS.detailPage,
        }) || errorRate.add(1);
      }
    } catch (_) { /* skip */ }
  });

  group('Teachers API', () => {
    const res = http.get(`${BASE_URL}/api/teachers?page=1&pageSize=20`, { headers });
    listDuration.add(res.timings.duration);
    check(res, {
      'teachers list status 200': (r) => r.status === 200,
      'teachers list < 2s': (r) => r.timings.duration < THRESHOLDS.listPage,
    }) || errorRate.add(1);
  });

  group('Students API', () => {
    const res = http.get(`${BASE_URL}/api/students?page=1&pageSize=20`, { headers });
    listDuration.add(res.timings.duration);
    check(res, {
      'students list status 200': (r) => r.status === 200,
      'students list < 2s': (r) => r.timings.duration < THRESHOLDS.listPage,
    }) || errorRate.add(1);
  });

  group('Groups API', () => {
    const listRes = http.get(`${BASE_URL}/api/groups?page=1&pageSize=20`, { headers });
    listDuration.add(listRes.timings.duration);
    check(listRes, {
      'groups list status 200': (r) => r.status === 200,
      'groups list < 2s': (r) => r.timings.duration < THRESHOLDS.listPage,
    }) || errorRate.add(1);

    try {
      const body = JSON.parse(listRes.body);
      const items = body.data?.items || body.data || [];
      if (items.length > 0) {
        const groupId = items[0].id;
        const detailRes = http.get(`${BASE_URL}/api/groups/${groupId}`, { headers });
        detailDuration.add(detailRes.timings.duration);
        check(detailRes, {
          'group detail status 200': (r) => r.status === 200,
          'group detail < 1s': (r) => r.timings.duration < THRESHOLDS.detailPage,
        }) || errorRate.add(1);
      }
    } catch (_) { /* skip */ }
  });

  sleep(1);
}

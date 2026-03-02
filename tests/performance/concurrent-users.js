/**
 * k6 Performance Test - Concurrent Users Simulation
 *
 * Simulates realistic concurrent user scenarios where multiple users
 * perform different operations simultaneously.
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=load concurrent-users.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, getHeaders, THRESHOLDS } from './config.js';

const errorRate = new Rate('errors');
const responseDuration = new Trend('response_duration', true);

export const options = {
  scenarios: {
    // Scenario 1: Browsing users (read-heavy)
    browsers: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 20 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
      ],
      exec: 'browsingUser',
    },
    // Scenario 2: Admin users (write operations)
    admins: {
      executor: 'constant-vus',
      vus: 5,
      duration: '2m',
      exec: 'adminUser',
      startTime: '10s',
    },
    // Scenario 3: Spike test - sudden burst of users
    spike: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '10s', target: 50 },
        { duration: '30s', target: 50 },
        { duration: '10s', target: 0 },
      ],
      exec: 'browsingUser',
      startTime: '1m',
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<3000'],
    http_req_failed: ['rate<0.1'],
    errors: ['rate<0.15'],
  },
};

const headers = getHeaders();

// Browsing user: reads lists and details
export function browsingUser() {
  group('Browse Schools', () => {
    const res = http.get(`${BASE_URL}/api/schools?page=1&pageSize=20`, { headers });
    responseDuration.add(res.timings.duration);
    check(res, {
      'browse schools ok': (r) => r.status === 200,
    }) || errorRate.add(1);
  });

  sleep(Math.random() * 2 + 1); // 1-3s think time

  group('Browse Classes', () => {
    const res = http.get(`${BASE_URL}/api/classes?page=1&pageSize=20`, { headers });
    responseDuration.add(res.timings.duration);
    check(res, {
      'browse classes ok': (r) => r.status === 200,
    }) || errorRate.add(1);
  });

  sleep(Math.random() * 2 + 1);

  group('Browse Groups', () => {
    const res = http.get(`${BASE_URL}/api/groups?page=1&pageSize=20`, { headers });
    responseDuration.add(res.timings.duration);
    check(res, {
      'browse groups ok': (r) => r.status === 200,
    }) || errorRate.add(1);
  });

  sleep(Math.random() * 3 + 2); // 2-5s think time between page views
}

// Admin user: performs CRUD operations
export function adminUser() {
  const ts = Date.now();

  group('Admin - Create School', () => {
    const payload = JSON.stringify({
      name: `ConcurrentTest ${__VU}-${ts}`,
      info: 'Concurrent test school',
    });
    const res = http.post(`${BASE_URL}/api/schools`, payload, { headers });
    responseDuration.add(res.timings.duration);
    const ok = check(res, {
      'admin create school ok': (r) => r.status === 200 || r.status === 201,
    });

    if (ok) {
      try {
        const body = JSON.parse(res.body);
        const schoolId = body.data?.id;
        if (schoolId) {
          sleep(0.5);

          // Read it back
          const readRes = http.get(`${BASE_URL}/api/schools/${schoolId}`, { headers });
          responseDuration.add(readRes.timings.duration);
          check(readRes, {
            'admin read school ok': (r) => r.status === 200,
          }) || errorRate.add(1);

          sleep(0.5);

          // Clean up
          http.del(`${BASE_URL}/api/schools/${schoolId}`, null, { headers });
        }
      } catch (_) { /* skip */ }
    } else {
      errorRate.add(1);
    }
  });

  sleep(Math.random() * 3 + 2);
}

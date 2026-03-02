/**
 * k6 Performance Test - CRUD Operations
 *
 * Tests create, read, update, delete operations on all resources.
 * Each VU performs a full lifecycle: create -> read -> update -> delete.
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=smoke crud-operations.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { BASE_URL, getHeaders, getStages, THRESHOLDS } from './config.js';

const errorRate = new Rate('errors');
const crudCreate = new Trend('crud_create_duration', true);
const crudRead = new Trend('crud_read_duration', true);
const crudUpdate = new Trend('crud_update_duration', true);
const crudDelete = new Trend('crud_delete_duration', true);
const opsCompleted = new Counter('crud_ops_completed');

export const options = {
  stages: getStages(),
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.1'],
    errors: ['rate<0.15'],
    crud_create_duration: [`p(95)<${THRESHOLDS.createOp}`],
    crud_read_duration: [`p(95)<${THRESHOLDS.detailPage}`],
    crud_update_duration: [`p(95)<${THRESHOLDS.updateOp}`],
    crud_delete_duration: [`p(95)<${THRESHOLDS.deleteOp}`],
  },
};

const headers = getHeaders();
const vuId = () => `${__VU}-${__ITER}`;

export default function () {
  group('School CRUD Lifecycle', () => {
    // CREATE
    const createPayload = JSON.stringify({
      name: `PerfTest School ${vuId()}-${Date.now()}`,
      info: 'Created by k6 performance test',
    });

    const createRes = http.post(`${BASE_URL}/api/schools`, createPayload, { headers });
    crudCreate.add(createRes.timings.duration);
    const createOk = check(createRes, {
      'school create success': (r) => r.status === 200 || r.status === 201,
      'school create < 1.5s': (r) => r.timings.duration < THRESHOLDS.createOp,
    });

    if (!createOk) {
      errorRate.add(1);
      return; // Skip rest if create failed
    }
    opsCompleted.add(1);

    let schoolId;
    try {
      const body = JSON.parse(createRes.body);
      schoolId = body.data?.id;
    } catch (_) {
      return;
    }

    if (!schoolId) return;

    // READ
    const readRes = http.get(`${BASE_URL}/api/schools/${schoolId}`, { headers });
    crudRead.add(readRes.timings.duration);
    check(readRes, {
      'school read success': (r) => r.status === 200,
      'school read < 1s': (r) => r.timings.duration < THRESHOLDS.detailPage,
    }) || errorRate.add(1);
    opsCompleted.add(1);

    // UPDATE
    const updatePayload = JSON.stringify({
      name: `PerfTest School Updated ${vuId()}`,
      info: 'Updated by k6 performance test',
    });

    const updateRes = http.put(`${BASE_URL}/api/schools/${schoolId}`, updatePayload, { headers });
    crudUpdate.add(updateRes.timings.duration);
    check(updateRes, {
      'school update success': (r) => r.status === 200,
      'school update < 1.5s': (r) => r.timings.duration < THRESHOLDS.updateOp,
    }) || errorRate.add(1);
    opsCompleted.add(1);

    // DELETE
    const deleteRes = http.del(`${BASE_URL}/api/schools/${schoolId}`, null, { headers });
    crudDelete.add(deleteRes.timings.duration);
    check(deleteRes, {
      'school delete success': (r) => r.status === 200 || r.status === 204,
      'school delete < 1.5s': (r) => r.timings.duration < THRESHOLDS.deleteOp,
    }) || errorRate.add(1);
    opsCompleted.add(1);
  });

  group('Group CRUD Lifecycle', () => {
    const createPayload = JSON.stringify({
      name: `PerfTest Group ${vuId()}-${Date.now()}`,
      description: 'Created by k6 performance test',
    });

    const createRes = http.post(`${BASE_URL}/api/groups`, createPayload, { headers });
    crudCreate.add(createRes.timings.duration);
    const createOk = check(createRes, {
      'group create success': (r) => r.status === 200 || r.status === 201,
    });

    if (!createOk) {
      errorRate.add(1);
      return;
    }
    opsCompleted.add(1);

    let groupId;
    try {
      const body = JSON.parse(createRes.body);
      groupId = body.data?.id;
    } catch (_) {
      return;
    }

    if (!groupId) return;

    // READ
    const readRes = http.get(`${BASE_URL}/api/groups/${groupId}`, { headers });
    crudRead.add(readRes.timings.duration);
    check(readRes, {
      'group read success': (r) => r.status === 200,
      'group read < 1s': (r) => r.timings.duration < THRESHOLDS.detailPage,
    }) || errorRate.add(1);
    opsCompleted.add(1);

    // UPDATE
    const updatePayload = JSON.stringify({
      name: `PerfTest Group Updated ${vuId()}`,
      description: 'Updated by k6 performance test',
    });

    const updateRes = http.put(`${BASE_URL}/api/groups/${groupId}`, updatePayload, { headers });
    crudUpdate.add(updateRes.timings.duration);
    check(updateRes, {
      'group update success': (r) => r.status === 200,
    }) || errorRate.add(1);
    opsCompleted.add(1);

    // DELETE
    const deleteRes = http.del(`${BASE_URL}/api/groups/${groupId}`, null, { headers });
    crudDelete.add(deleteRes.timings.duration);
    check(deleteRes, {
      'group delete success': (r) => r.status === 200 || r.status === 204,
    }) || errorRate.add(1);
    opsCompleted.add(1);
  });

  sleep(1);
}

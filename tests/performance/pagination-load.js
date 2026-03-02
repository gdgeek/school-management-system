/**
 * k6 Performance Test - Pagination & Large Dataset
 *
 * Tests pagination performance with various page sizes and page numbers.
 * Validates that database queries with pagination remain fast even on
 * later pages (eager loading, indexed queries).
 *
 * Usage:
 *   k6 run -e AUTH_TOKEN=<jwt> -e TEST_TYPE=smoke pagination-load.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, getHeaders, getStages, THRESHOLDS } from './config.js';

const errorRate = new Rate('errors');
const paginationDuration = new Trend('pagination_duration', true);

export const options = {
  stages: getStages(),
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    errors: ['rate<0.1'],
    pagination_duration: [`p(95)<${THRESHOLDS.listPage}`],
  },
};

const headers = getHeaders();

const PAGE_SIZES = [10, 20, 50, 100];
const ENDPOINTS = [
  '/api/schools',
  '/api/classes',
  '/api/teachers',
  '/api/students',
  '/api/groups',
];

export default function () {
  // Test different page sizes
  group('Pagination - Various Page Sizes', () => {
    for (const endpoint of ENDPOINTS) {
      for (const pageSize of PAGE_SIZES) {
        const res = http.get(
          `${BASE_URL}${endpoint}?page=1&pageSize=${pageSize}`,
          { headers, tags: { endpoint, pageSize: String(pageSize) } }
        );
        paginationDuration.add(res.timings.duration);
        check(res, {
          [`${endpoint} pageSize=${pageSize} status ok`]: (r) => r.status === 200,
          [`${endpoint} pageSize=${pageSize} < 2s`]: (r) => r.timings.duration < THRESHOLDS.listPage,
        }) || errorRate.add(1);
      }
    }
  });

  // Test deeper pages (page 2, 5, 10) to check index performance
  group('Pagination - Deep Pages', () => {
    const deepPages = [1, 2, 5, 10];
    for (const page of deepPages) {
      const res = http.get(
        `${BASE_URL}/api/schools?page=${page}&pageSize=20`,
        { headers, tags: { page: String(page) } }
      );
      paginationDuration.add(res.timings.duration);
      check(res, {
        [`schools page=${page} status ok`]: (r) => r.status === 200,
        [`schools page=${page} < 2s`]: (r) => r.timings.duration < THRESHOLDS.listPage,
      }) || errorRate.add(1);
    }
  });

  // Test search with pagination
  group('Search + Pagination', () => {
    const searchTerms = ['test', 'school', 'a'];
    for (const term of searchTerms) {
      const res = http.get(
        `${BASE_URL}/api/schools?page=1&pageSize=20&search=${encodeURIComponent(term)}`,
        { headers, tags: { search: term } }
      );
      paginationDuration.add(res.timings.duration);
      check(res, {
        [`search "${term}" status ok`]: (r) => r.status === 200,
        [`search "${term}" < 2s`]: (r) => r.timings.duration < THRESHOLDS.listPage,
      }) || errorRate.add(1);
    }
  });

  // Test max page size (100 records per page as per requirement)
  group('Max Page Size (100)', () => {
    for (const endpoint of ENDPOINTS) {
      const res = http.get(
        `${BASE_URL}${endpoint}?page=1&pageSize=100`,
        { headers, tags: { endpoint, test: 'max_page' } }
      );
      paginationDuration.add(res.timings.duration);
      check(res, {
        [`${endpoint} max page size status ok`]: (r) => r.status === 200,
        [`${endpoint} max page size < 2s`]: (r) => r.timings.duration < THRESHOLDS.listPage,
      }) || errorRate.add(1);
    }
  });

  sleep(1);
}

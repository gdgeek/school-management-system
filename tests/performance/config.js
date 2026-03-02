/**
 * Performance Test Configuration
 * Shared configuration for all k6 test scripts
 */

// Base URLs - override via environment variables
export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8084';
export const FRONTEND_URL = __ENV.FRONTEND_URL || 'http://localhost:3002';

// Authentication - provide a valid JWT token via environment variable
export const AUTH_TOKEN = __ENV.AUTH_TOKEN || '';

// Common headers
export function getHeaders() {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (AUTH_TOKEN) {
    headers['Authorization'] = `Bearer ${AUTH_TOKEN}`;
  }
  return headers;
}

// Performance thresholds (in milliseconds)
export const THRESHOLDS = {
  listPage: 2000,   // List pages should respond within 2s
  detailPage: 1000, // Detail pages should respond within 1s
  createOp: 1500,   // Create operations within 1.5s
  updateOp: 1500,   // Update operations within 1.5s
  deleteOp: 1500,   // Delete operations within 1.5s
  healthCheck: 500,  // Health check within 500ms
};

// Load test stages
export const LOAD_STAGES = {
  smoke: [
    { duration: '30s', target: 1 },
  ],
  load: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 10 },
    { duration: '30s', target: 0 },
  ],
  stress: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 50 },
    { duration: '30s', target: 100 },
    { duration: '1m', target: 100 },
    { duration: '30s', target: 0 },
  ],
};

/**
 * Get stages based on TEST_TYPE env variable
 * Usage: k6 run -e TEST_TYPE=load script.js
 */
export function getStages() {
  const testType = __ENV.TEST_TYPE || 'smoke';
  return LOAD_STAGES[testType] || LOAD_STAGES.smoke;
}

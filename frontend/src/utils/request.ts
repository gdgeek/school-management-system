import axios, {
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type AxiosError,
  type CancelTokenSource,
} from 'axios'
import { ElMessage } from 'element-plus'

// API响应接口
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
  timestamp?: number
}

// 分页响应接口
export interface PaginationResponse<T = any> {
  items: T[]
  pagination: {
    total: number
    page: number
    pageSize: number
    totalPages: number
  }
}

// ─── Request Deduplication ────────────────────────────────────────────────────
// Tracks in-flight GET requests by their cache key; cancels duplicates.
const pendingRequests = new Map<string, CancelTokenSource>()

function buildRequestKey(config: AxiosRequestConfig): string {
  const params = config.params ? JSON.stringify(config.params) : ''
  return `${config.method?.toUpperCase()}:${config.url}:${params}`
}

function addPendingRequest(config: AxiosRequestConfig & { _dedup?: boolean }): void {
  if (config.method?.toUpperCase() !== 'GET' || config._dedup === false) return
  const key = buildRequestKey(config)
  if (pendingRequests.has(key)) {
    const source = pendingRequests.get(key)!
    source.cancel(`Duplicate request cancelled: ${key}`)
  }
  const source = axios.CancelToken.source()
  config.cancelToken = source.token
  pendingRequests.set(key, source)
}

function removePendingRequest(config: AxiosRequestConfig): void {
  if (config.method?.toUpperCase() !== 'GET') return
  const key = buildRequestKey(config)
  pendingRequests.delete(key)
}

// ─── Response Cache ───────────────────────────────────────────────────────────
interface CacheEntry {
  data: any
  expiresAt: number
}

const responseCache = new Map<string, CacheEntry>()
const DEFAULT_CACHE_TTL = 30_000 // 30 seconds

function getCached(key: string): any | null {
  const entry = responseCache.get(key)
  if (!entry) return null
  if (Date.now() > entry.expiresAt) {
    responseCache.delete(key)
    return null
  }
  return entry.data
}

function setCache(key: string, data: any, ttl = DEFAULT_CACHE_TTL): void {
  responseCache.set(key, { data, expiresAt: Date.now() + ttl })
}

/** Manually invalidate cache entries matching a URL prefix */
export function invalidateCache(urlPrefix: string): void {
  for (const key of responseCache.keys()) {
    if (key.includes(urlPrefix)) {
      responseCache.delete(key)
    }
  }
}

// ─── Request Queue / Throttle for bulk operations ────────────────────────────
interface QueuedRequest {
  fn: () => Promise<any>
  resolve: (value: any) => void
  reject: (reason?: any) => void
}

class RequestQueue {
  private queue: QueuedRequest[] = []
  private running = 0
  private readonly concurrency: number

  constructor(concurrency = 3) {
    this.concurrency = concurrency
  }

  enqueue<T>(fn: () => Promise<T>): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      this.queue.push({ fn, resolve, reject })
      this.run()
    })
  }

  private run(): void {
    while (this.running < this.concurrency && this.queue.length > 0) {
      const item = this.queue.shift()!
      this.running++
      item.fn()
        .then(item.resolve)
        .catch(item.reject)
        .finally(() => {
          this.running--
          this.run()
        })
    }
  }
}

/** Shared queue for bulk/batch API operations (max 3 concurrent) */
export const bulkQueue = new RequestQueue(3)

// ─── Axios Instance ───────────────────────────────────────────────────────────
const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Request interceptor
service.interceptors.request.use(
  (config: any) => {
    // Inject JWT token
    const token = localStorage.getItem('access_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Check response cache for GET requests
    if (config.method?.toUpperCase() === 'GET' && config._cache !== false) {
      const cacheKey = buildRequestKey(config)
      const cached = getCached(cacheKey)
      if (cached) {
        // Attach cached data so the response interceptor can return it
        config._cachedData = cached
      }
    }

    // Deduplication for GET requests
    addPendingRequest(config)

    if (import.meta.env.DEV) {
      console.log('[Request]', config.method?.toUpperCase(), config.url, {
        params: config.params,
        data: config.data,
      })
    }

    return config
  },
  (error) => {
    console.error('[Request Error]', error)
    return Promise.reject(error)
  }
)

// Response interceptor
service.interceptors.response.use(
  (response: AxiosResponse<ApiResponse> & { config: any }) => {
    removePendingRequest(response.config)

    if (import.meta.env.DEV) {
      console.log('[Response]', response.config.url, response.data)
    }

    const res = response.data

    if (res.code !== 200) {
      ElMessage.error(res.message || 'Request failed')
      return Promise.reject(new Error(res.message || 'Request failed'))
    }

    // Cache successful GET responses
    if (response.config.method?.toUpperCase() === 'GET' && response.config._cache !== false) {
      const cacheKey = buildRequestKey(response.config)
      const ttl = response.config._cacheTtl ?? DEFAULT_CACHE_TTL
      setCache(cacheKey, response, ttl)
    }

    return response
  },
  async (error: AxiosError<ApiResponse>) => {
    // Silently ignore cancelled duplicate requests
    if (axios.isCancel(error)) {
      return Promise.reject(error)
    }

    if (error.config) {
      removePendingRequest(error.config)
    }

    console.error('[Response Error]', error)

    if (!error.response) {
      ElMessage.error('Network error. Please check your connection.')
      return Promise.reject(error)
    }

    const { status, data } = error.response

    if (status === 401) {
      const refreshToken = localStorage.getItem('refresh_token')
      if (refreshToken && !error.config?.url?.includes('/auth/refresh')) {
        try {
          const response = await axios.post<ApiResponse<{ access_token: string }>>(
            `${import.meta.env.VITE_API_BASE_URL}/auth/refresh`,
            { refresh_token: refreshToken }
          )
          if (response.data.code === 200) {
            const newToken = response.data.data.access_token
            localStorage.setItem('access_token', newToken)
            if (error.config) {
              error.config.headers = error.config.headers ?? {}
              error.config.headers.Authorization = `Bearer ${newToken}`
              return service.request(error.config)
            }
          }
        } catch (refreshError) {
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')
          ElMessage.error('Session expired. Please login again.')
          const mainSystemUrl = import.meta.env.VITE_MAIN_SYSTEM_URL || '/'
          window.location.href = mainSystemUrl
          return Promise.reject(refreshError)
        }
      } else {
        ElMessage.error('Unauthorized. Please login.')
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
        const mainSystemUrl = import.meta.env.VITE_MAIN_SYSTEM_URL || '/'
        window.location.href = mainSystemUrl
      }
    }

    if (status === 403) ElMessage.error(data?.message || 'Access denied.')
    if (status === 404) ElMessage.error(data?.message || 'Resource not found.')
    if (status === 422) ElMessage.error(data?.message || 'Validation failed.')
    if (status >= 500) ElMessage.error('Server error. Please try again later.')

    return Promise.reject(error)
  }
)

export default service

// ─── Convenience methods ──────────────────────────────────────────────────────
export const request = {
  get<T = any>(url: string, config?: AxiosRequestConfig & { _cache?: boolean; _cacheTtl?: number }): Promise<T> {
    return service.get<ApiResponse<T>>(url, config).then((res) => res.data.data)
  },

  post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.post<ApiResponse<T>>(url, data, config).then((res) => res.data.data)
  },

  put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.put<ApiResponse<T>>(url, data, config).then((res) => res.data.data)
  },

  delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    return service.delete<ApiResponse<T>>(url, config).then((res) => res.data.data)
  },

  patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    return service.patch<ApiResponse<T>>(url, data, config).then((res) => res.data.data)
  },
}

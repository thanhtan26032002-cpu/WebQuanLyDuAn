const TOKEN_KEY = 'ringnet_api_token'
const USER_KEY = 'currentUser'

export const getAuthToken = () => localStorage.getItem(TOKEN_KEY)
export const hasAuthSession = () => Boolean(getAuthToken())
export const getStoredUser = () => {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  } catch {
    return null
  }
}

export function setAuthSession(token, user) {
  localStorage.setItem(TOKEN_KEY, token)
  updateStoredUser(user)
}

export function updateStoredUser(user) {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearAuthSession() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export async function apiFetch(input, options = {}) {
  const headers = new Headers(options.headers || {})
  headers.set('Accept', 'application/json')
  if (!(options.body instanceof FormData) && options.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  const token = getAuthToken()
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const response = await window.fetch(input, { ...options, headers })
  if (response.status === 401 && !String(input).endsWith('/login')) {
    clearAuthSession()
    window.dispatchEvent(new CustomEvent('ringnet:unauthorized'))
  }

  return response
}

export async function parseApiError(response, fallback = 'Có lỗi xảy ra. Vui lòng thử lại.') {
  const payload = await response.json().catch(() => ({}))
  const firstValidationError = payload.errors ? Object.values(payload.errors).flat().find(Boolean) : null
  return firstValidationError || payload.message || fallback
}

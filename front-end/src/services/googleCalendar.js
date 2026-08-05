/**
 * Google Calendar Integration Service
 * Sử dụng Google Identity Services (GIS) để xác thực OAuth2
 * và Google Calendar API v3 để lấy danh sách sự kiện.
 */

// ⚠️ Thay bằng Client ID thật của bạn từ Google Cloud Console
const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID || 'YOUR_CLIENT_ID.apps.googleusercontent.com'
const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3'
const SCOPES = 'https://www.googleapis.com/auth/calendar.readonly'

let tokenClient = null
let accessToken = null

/**
 * Khởi tạo Google OAuth Token Client
 * @returns {Promise<void>}
 */
export function initGoogleAuth() {
  return new Promise((resolve, reject) => {
    if (tokenClient) {
      resolve()
      return
    }

    if (!window.google?.accounts?.oauth2) {
      reject(new Error('Google Identity Services SDK chưa được tải. Vui lòng kiểm tra kết nối mạng.'))
      return
    }

    try {
      tokenClient = window.google.accounts.oauth2.initTokenClient({
        client_id: GOOGLE_CLIENT_ID,
        scope: SCOPES,
        callback: () => {}, // Will be overridden in requestCalendarAccess
      })
      resolve()
    } catch (error) {
      reject(new Error('Không thể khởi tạo Google OAuth: ' + error.message))
    }
  })
}

/**
 * Yêu cầu quyền truy cập Google Calendar qua OAuth popup
 * @returns {Promise<string>} access token
 */
export function requestCalendarAccess() {
  return new Promise((resolve, reject) => {
    if (!tokenClient) {
      reject(new Error('Chưa khởi tạo Google Auth. Gọi initGoogleAuth() trước.'))
      return
    }

    tokenClient.callback = (response) => {
      if (response.error) {
        reject(new Error(response.error_description || 'Xác thực Google thất bại.'))
        return
      }
      accessToken = response.access_token
      resolve(accessToken)
    }

    tokenClient.error_callback = (error) => {
      if (error.type === 'popup_closed') {
        reject(new Error('popup_closed'))
      } else {
        reject(new Error('Lỗi OAuth: ' + (error.message || 'Không xác định')))
      }
    }

    // Nếu đã có token, dùng lại (consent prompt chỉ hiện lần đầu)
    if (accessToken) {
      tokenClient.requestAccessToken({ prompt: '' })
    } else {
      tokenClient.requestAccessToken({ prompt: 'consent' })
    }
  })
}

/**
 * Lấy danh sách sự kiện từ Google Calendar
 * @param {number} daysAhead - Số ngày phía trước để lấy sự kiện (mặc định 30)
 * @returns {Promise<Array>} danh sách sự kiện
 */
export async function fetchCalendarEvents(daysAhead = 30) {
  if (!accessToken) {
    throw new Error('Chưa có access token. Vui lòng kết nối Google Calendar trước.')
  }

  const now = new Date()
  const timeMin = now.toISOString()
  const timeMax = new Date(now.getTime() + daysAhead * 24 * 60 * 60 * 1000).toISOString()

  const params = new URLSearchParams({
    timeMin,
    timeMax,
    maxResults: '50',
    singleEvents: 'true',
    orderBy: 'startTime',
  })

  const response = await fetch(`${CALENDAR_API_BASE}/calendars/primary/events?${params}`, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  })

  if (!response.ok) {
    if (response.status === 401) {
      accessToken = null
      throw new Error('Token đã hết hạn. Vui lòng kết nối lại Google Calendar.')
    }
    throw new Error('Không thể lấy sự kiện từ Google Calendar. Mã lỗi: ' + response.status)
  }

  const data = await response.json()
  return (data.items || []).filter(event => event.status !== 'cancelled')
}

/**
 * Chuyển đổi Google Calendar event thành dữ liệu form nhiệm vụ
 * @param {Object} event - Google Calendar event object
 * @returns {Object} task form data
 */
export function mapEventToTask(event) {
  const startDate = extractDate(event.start)
  const endDate = extractDate(event.end)

  // Tính estimated hours nếu có thời gian cụ thể
  let estimatedHours = ''
  if (event.start?.dateTime && event.end?.dateTime) {
    const diffMs = new Date(event.end.dateTime) - new Date(event.start.dateTime)
    const hours = Math.round((diffMs / (1000 * 60 * 60)) * 2) / 2 // Làm tròn 0.5h
    if (hours > 0 && hours <= 999) estimatedHours = String(hours)
  }

  return {
    title: event.summary || '',
    description: buildDescription(event),
    startDate,
    dueDate: endDate || startDate,
    estimatedHours,
  }
}

/**
 * Trích xuất ngày dạng YYYY-MM-DD từ Google Calendar event start/end
 */
function extractDate(dateObj) {
  if (!dateObj) return ''
  if (dateObj.date) return dateObj.date // All-day event: already YYYY-MM-DD
  if (dateObj.dateTime) {
    return dateObj.dateTime.split('T')[0]
  }
  return ''
}

/**
 * Tạo mô tả nhiệm vụ từ thông tin event
 */
function buildDescription(event) {
  const parts = []
  if (event.description) parts.push(event.description)
  if (event.location) parts.push(`📍 Địa điểm: ${event.location}`)
  if (event.hangoutLink) parts.push(`🔗 Meet: ${event.hangoutLink}`)
  if (event.attendees?.length) {
    const attendeeList = event.attendees
      .filter(a => !a.self)
      .map(a => a.displayName || a.email)
      .join(', ')
    if (attendeeList) parts.push(`👥 Người tham gia: ${attendeeList}`)
  }
  return parts.join('\n')
}

/**
 * Format thời gian event để hiển thị
 * @param {Object} event - Google Calendar event
 * @returns {string} formatted time string
 */
export function formatEventTime(event) {
  if (event.start?.date) {
    // All-day event
    const start = formatViDate(event.start.date)
    if (event.end?.date && event.end.date !== event.start.date) {
      // Trừ 1 ngày vì Google Calendar dùng exclusive end date cho all-day events
      const endDate = new Date(event.end.date)
      endDate.setDate(endDate.getDate() - 1)
      const end = formatViDate(endDate.toISOString().split('T')[0])
      if (end !== start) return `${start} → ${end}`
    }
    return `${start} (cả ngày)`
  }

  if (event.start?.dateTime) {
    const startDt = new Date(event.start.dateTime)
    const endDt = event.end?.dateTime ? new Date(event.end.dateTime) : null

    const dateStr = formatViDate(event.start.dateTime.split('T')[0])
    const startTime = startDt.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })

    if (endDt) {
      const endTime = endDt.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
      return `${dateStr}, ${startTime} – ${endTime}`
    }
    return `${dateStr}, ${startTime}`
  }

  return ''
}

/**
 * Format ngày theo tiếng Việt ngắn gọn
 */
function formatViDate(dateStr) {
  const date = new Date(dateStr + 'T00:00:00')
  const day = date.getDate()
  const month = date.getMonth() + 1
  return `${day}/${month}`
}

/**
 * Xóa token đã lưu (disconnect)
 */
export function disconnectGoogle() {
  if (accessToken && window.google?.accounts?.oauth2) {
    window.google.accounts.oauth2.revoke(accessToken, () => {})
  }
  accessToken = null
  tokenClient = null
}

/**
 * Kiểm tra đã kết nối chưa
 */
export function isGoogleConnected() {
  return Boolean(accessToken)
}

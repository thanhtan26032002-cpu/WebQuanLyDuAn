export function notificationTimeAgo(dateValue) {
  const timestamp = new Date(dateValue)
  if (Number.isNaN(timestamp.getTime())) return 'Không rõ thời gian'

  const seconds = Math.max(0, Math.floor((Date.now() - timestamp.getTime()) / 1000))
  if (seconds < 60) return 'Vừa xong'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes} phút trước`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours} giờ trước`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days} ngày trước`

  return timestamp.toLocaleDateString('vi-VN')
}

export function notificationDestination(notification) {
  const targetType = notification?.targetType || notification?.target_type
  const targetCode = notification?.targetCode || notification?.target_code
  if (!targetCode) return null

  if (targetType === 'Task') {
    return { name: 'tasks', query: { task: targetCode } }
  }
  if (targetType === 'Project') {
    return { name: 'project-detail', params: { id: targetCode } }
  }

  return null
}

export function notificationExactTime(dateValue) {
  const timestamp = new Date(dateValue)
  return Number.isNaN(timestamp.getTime())
    ? ''
    : timestamp.toLocaleString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      })
}

import { computed, ref } from 'vue'
import { apiFetch, clearAuthSession, hasAuthSession } from '../services/api'

const fetch = apiFetch

// ========== CẤU HÌNH HỆ THỐNG (Không còn phụ thuộc file dữ liệu ảo) ==========
const navigationItems = [
  { id: 'dashboard', label: 'Tổng quan', icon: 'grid', path: '/' },
  { id: 'projects', label: 'Dự án', icon: 'briefcase', path: '/projects' },
  { id: 'tasks', label: 'Nhiệm vụ', icon: 'circleCheck', path: '/tasks' },
  { id: 'team', label: 'Nhóm', icon: 'users', path: '/team' },
  { id: 'calendar', label: 'Lịch', icon: 'calendar', path: '/calendar' },
]

const projectStatusMap = {
  active: { label: 'Đang triển khai', className: 'status-active' },
  planning: { label: 'Lập kế hoạch', className: 'status-planning' },
  on_hold: { label: 'Tạm dừng', className: 'status-hold' },
  completed: { label: 'Hoàn thành', className: 'status-completed' },
}

const taskStatusMap = {
  todo: { label: 'Cần làm', color: 'slate' },
  in_progress: { label: 'Đang làm', color: 'amber' },
  done: { label: 'Hoàn thành', color: 'emerald' },
}

const priorityMap = {
  high: { label: 'Cao', className: 'priority-high' },
  medium: { label: 'Trung bình', className: 'priority-medium' },
  low: { label: 'Thấp', className: 'priority-low' },
}

// Dùng URL tương đối để bản production hoạt động trên mọi tên miền.
// Khi API nằm ở tên miền khác, cấu hình VITE_API_URL lúc build.
const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const BASE_URL = import.meta.env.VITE_BASE_URL || API_URL.replace(/\/api\/?$/, '')

// ========== CHUYỂN ĐỔI DỮ LIỆU API → FRONTEND ==========
// Helper function to format bytes for attachments
function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function mapAttachment(a) {
  return {
    code: a.code,
    name: a.file_name,
    url: a.file_path,
    size: a.size_bytes ? formatBytes(a.size_bytes) : 'Không xác định',
    uploadedAt: a.created_at || new Date().toISOString(),
    uploadedBy: a.uploaded_by
  }
}

// API trả về snake_case (due_date), Frontend dùng camelCase (dueDate)
function mapProject(p) {
  return {
    ...p,
    id: p.code, // Map code sang id để frontend không bị lỗi
    dueDate: p.due_date || p.dueDate,
    startDate: p.start_date || p.startDate,
    customerId: p.customer_code || p.customerId || null,
    managerId: p.manager_code || p.managerId || null,
    customer: p.customer || null,
    manager: p.manager || null,
    createdAt: p.created_at || p.createdAt,
    deletedAt: p.deleted_at || p.deletedAt || null,
    restoreUntil: p.restore_until || p.restoreUntil || null,
    canRestore: p.can_restore ?? p.canRestore ?? false,
    memberIds: p.members ? p.members.map(m => m.code) : (p.memberIds || []),
    progress: p.progress || 0,
    health: p.health || 'on_track',
    updateCadence: p.update_cadence || p.updateCadence || 'weekly',
    updates: p.updates || [],
    milestones: p.milestones || [],
    automations: p.automations || [],
    color: p.color || 'indigo',
    files: p.attachments ? p.attachments.map(mapAttachment) : (p.files || []),
  }
}

function mapChecklist(item) {
  return {
    ...item,
    id: item.code || item.id,
    completed: Boolean(item.completed),
  }
}

function mapWorkLog(log) {
  const completedItems = log.completed_items || log.completedItems || []
  return {
    ...log,
    id: log.code || log.id,
    memberId: log.reporter_code || log.memberId || null,
    completedItems,
    checklists: completedItems.map(item => item.id),
    files: log.files || [],
    createdAt: log.created_at || log.createdAt,
    durationMinutes: log.duration_minutes ?? log.durationMinutes ?? null,
  }
}

function mapTask(t) {
  let parsedTags = []
  if (Array.isArray(t.tags)) {
    parsedTags = t.tags
  } else if (typeof t.tags === 'string' && t.tags.trim()) {
    parsedTags = t.tags.split(',').map(s => s.trim()).filter(Boolean)
  }
  return {
    ...t,
    id: t.code,
    projectId: t.project_code || t.projectId,
    assigneeId: t.assignee_code || t.assigneeId,
    startDate: t.start_date || t.startDate,
    dueDate: t.due_date || t.dueDate,
    type: t.type || 'task',
    estimatedHours: t.estimated_hours ?? t.estimatedHours ?? null,
    actualMinutes: t.actual_minutes ?? t.actualMinutes ?? 0,
    remainingHours: t.remaining_hours ?? t.remainingHours ?? null,
    milestoneId: t.milestone_code || t.milestoneId || null,
    isBlocked: Boolean(t.is_blocked ?? t.isBlocked),
    blockedReason: t.blocked_reason || t.blockedReason || '',
    blockedOverride: Boolean(t.blocked_override ?? t.blockedOverride),
    recurrence: t.recurrence || null,
    recurrenceUntil: t.recurrence_until || t.recurrenceUntil || null,
    dependencies: t.dependencies || [],
    blocking: t.blocking || [],
    watchers: t.watchers || [],
    createdAt: t.created_at || t.createdAt,
    deletedAt: t.deleted_at || t.deletedAt || null,
    restoreUntil: t.restore_until || t.restoreUntil || null,
    canRestore: t.can_restore ?? t.canRestore ?? false,
    tags: parsedTags,
    progress: t.progress || 0,
    files: t.attachments ? t.attachments.map(mapAttachment) : (t.files || []),
    checklists: (t.checklists || []).map(mapChecklist),
    workLogs: (t.work_logs || t.workLogs || []).map(mapWorkLog),
  }
}

function mapActivity(a) {
  const actorCode = a.user?.code || a.user_code || a.memberId || null
  const actorName = a.user?.name || 'Người dùng hệ thống'
  const actorColors = ['purple', 'blue', 'pink', 'orange', 'green', 'sky']
  const colorIndex = actorCode
    ? actorCode.charCodeAt(actorCode.length - 1) % actorColors.length
    : 0

  return {
    ...a,
    id: a.code,
    userId: actorCode,
    targetType: a.target_type || a.targetType,
    targetId: a.target_code || a.targetId,
    projectId: (a.target_type || a.targetType)?.toLowerCase() === 'project'
      ? (a.target_code || a.targetId)
      : null,
    createdAt: a.created_at || a.createdAt,
    actor: {
      id: actorCode,
      name: actorName,
      initials: actorName
        .split(' ')
        .filter(Boolean)
        .map(part => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2) || 'HT',
      avatar: a.user?.avatar || null,
      color: a.user?.color || actorColors[colorIndex],
    },
  }
}

function mapMember(m) {
  const colorIndex = m.code ? m.code.charCodeAt(m.code.length - 1) % 6 : 0;
  return {
    ...m,
    id: m.code,
    joinDate: m.join_date || m.joinDate,
    initials: m.name ? m.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '??',
    color: m.color || ['purple', 'blue', 'pink', 'orange', 'green', 'sky'][colorIndex],
    createdAt: m.created_at || m.createdAt,
  }
}

function mapGroup(g) {
  return {
    ...g,
    id: g.code,
    memberIds: g.member_ids || g.memberIds || [],
    icon: g.icon || '🚀',
    color: g.color || 'violet',
  }
}

function mapCustomer(customer) {
  return { ...customer, id: customer.code || customer.id }
}

function validateCustomerDraft(payload) {
  const errors = {}
  const name = String(payload.name || '').trim()
  const email = String(payload.email || '').trim()
  const phone = String(payload.phone || '').trim()
  if (!name) errors.customer_name = 'Vui lòng nhập tên khách hàng.'
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.email = 'Email khách hàng không đúng định dạng.'
  }
  if (phone) {
    const digitCount = phone.replace(/\D/g, '').length
    if (!/^[0-9+\s().-]+$/.test(phone) || digitCount < 8 || digitCount > 15) {
      errors.phone = 'Số điện thoại phải có từ 8 đến 15 chữ số và chỉ chứa ký tự + ( ) . -.'
    }
  }
  return errors
}

// ========== STATE (Khởi tạo rỗng, dữ liệu sẽ được tải từ DB) ==========
const projects = ref([])
const tasks = ref([])
const deletedProjects = ref([])
const deletedTasks = ref([])
const members = ref([])
const groups = ref([])
const customers = ref([])
const comments = ref([])
const activities = ref([])
const users = ref([])
const apiConnectionError = ref('')
const isWorkspaceLoading = ref(false)

// ========== FETCH DỮ LIỆU TỪ DATABASE ==========
const loadDataFromAPI = async () => {
  if (!hasAuthSession()) return
  isWorkspaceLoading.value = true
  try {
    const [resProjects, resMembers, resGroups, resTasks, resActivities, resNotifications, resUsers, resCustomers] = await Promise.all([
      fetch(`${API_URL}/projects`).catch(() => null),
      fetch(`${API_URL}/members`).catch(() => null),
      fetch(`${API_URL}/groups`).catch(() => null),
      fetch(`${API_URL}/tasks`).catch(() => null),
      fetch(`${API_URL}/activities`).catch(() => null),
      fetch(`${API_URL}/notifications`).catch(() => null),
      fetch(`${API_URL}/users`).catch(() => null),
      fetch(`${API_URL}/customers`).catch(() => null)
    ])

    const responses = [resProjects, resMembers, resGroups, resTasks, resActivities, resNotifications, resUsers, resCustomers]
    apiConnectionError.value = responses.some(response => !response?.ok)
      ? 'Không thể tải đầy đủ dữ liệu từ máy chủ. Vui lòng kiểm tra kết nối API.'
      : ''

    if (resUsers && resUsers.ok) {
      const rawUsers = await resUsers.json()
      users.value = rawUsers
      const current = rawUsers.find(u => u.code === currentUser.value.code)
      if (current) {
        currentUser.value = { ...currentUser.value, ...current }
        localStorage.setItem('currentUser', JSON.stringify(currentUser.value))
      }
    }

    if (resProjects && resProjects.ok) {
      const raw = await resProjects.json()
      projects.value = raw.map(mapProject)
    }
    if (resMembers && resMembers.ok) {
      const raw = await resMembers.json()
      members.value = raw.map(mapMember)
    }
    if (resGroups && resGroups.ok) {
      const raw = await resGroups.json()
      groups.value = raw.map(mapGroup)
    }
    if (resTasks && resTasks.ok) {
      const raw = await resTasks.json()
      tasks.value = raw.map(mapTask)
    }
    if (resActivities && resActivities.ok) {
      const raw = await resActivities.json()
      activities.value = raw.map(mapActivity)
    }
    if (resNotifications && resNotifications.ok) {
      notifications.value = await resNotifications.json()
    }
    if (resCustomers && resCustomers.ok) {
      const raw = await resCustomers.json()
      customers.value = raw.map(mapCustomer)
    }

    if (!apiConnectionError.value) {
      console.log('✅ Đã tải dữ liệu từ Database thành công')
    }
  } catch (error) {
    apiConnectionError.value = 'Không thể kết nối máy chủ. Vui lòng thử lại sau.'
    console.error('❌ Lỗi kết nối Database:', error)
  } finally {
    isWorkspaceLoading.value = false
  }
}

// const refreshActivitiesAndNotifications = async () => {
//   try {
//     const [resActivities, resNotifications] = await Promise.all([
//       fetch(`${API_URL}/activities`).catch(() => null),
//       fetch(`${API_URL}/notifications`).catch(() => null)
//     ])
//     if (resActivities && resActivities.ok) {
//       const raw = await resActivities.json()
//       activities.value = raw.map(mapActivity)
//     }
//     if (resNotifications && resNotifications.ok) {
//       notifications.value = await resNotifications.json()
//     }
//   } catch (e) {
//     console.error(e)
//   }
// }

const globalSearch = ref('')
const darkMode = ref(false)
const sidebarOpen = ref(false)
const notificationDropdownOpen = ref(false)
const globalSearchModalOpen = ref(false)
const projectModalOpen = ref(false)
const taskModalOpen = ref(false)
const newTaskProjectId = ref('')
const toastMessage = ref('')

function openTaskModal(projectId = '') {
  newTaskProjectId.value = projectId || ''
  taskModalOpen.value = true
}

function closeTaskModal() {
  newTaskProjectId.value = ''
  taskModalOpen.value = false
}

// New states for v2.0
const activeTaskId = ref(null)
const editingProjectId = ref(null)
const memberDetailModalOpen = ref(false)
const activeMemberId = ref(null)
const addMemberModalOpen = ref(false)
const addMemberTargetGroupId = ref(null)
const addGroupModalOpen = ref(false)
const projectSettingsModalOpen = ref(false)
const fileUploadModalOpen = ref(false)
const manageMembersModalOpen = ref(false)
const editGroupModalOpen = ref(false)
const activeEditGroupId = ref(null)
const importProjectModalOpen = ref(false)

function openAddMemberModal(groupId = null) {
  addMemberTargetGroupId.value = groupId || null
  addMemberModalOpen.value = true
}

function closeAddMemberModal() {
  addMemberModalOpen.value = false
  addMemberTargetGroupId.value = null
}

const notifications = ref([])

// Lấy thông tin user đang đăng nhập từ localStorage
const currentUser = ref(JSON.parse(localStorage.getItem('currentUser') || 'null'))

async function refreshLiveData() {
  if (!hasAuthSession()) return
  try {
    const [activityResponse, notificationResponse] = await Promise.all([
      fetch(`${API_URL}/activities`),
      fetch(`${API_URL}/notifications`),
    ])
    if (activityResponse.ok) activities.value = (await activityResponse.json()).map(mapActivity)
    if (notificationResponse.ok) notifications.value = await notificationResponse.json()
  } catch (error) {
    console.error('Không thể làm mới dữ liệu trực tiếp:', error)
  }
}

if (hasAuthSession()) {
  loadDataFromAPI()
  window.setInterval(refreshLiveData, 45000)
}

window.addEventListener('ringnet:unauthorized', () => {
  clearAuthSession()
  if (window.location.pathname !== '/login') window.location.assign('/login')
})

async function logout() {
  try {
    await fetch(`${API_URL}/logout`, { method: 'POST' })
  } finally {
    clearAuthSession()
    window.location.assign('/login')
  }
}

let toastTimer

// Helper: Dịch lỗi xác thực từ Backend sang tiếng Việt
function translateValidationMessage(msg, field = '') {
  if (!msg || typeof msg !== 'string') return 'Dữ liệu không hợp lệ.'
  const normalizedMessage = msg.toLowerCase()
  
  const fieldNames = {
    name: 'Tên',
    title: 'Tiêu đề',
    email: 'Email',
    phone: 'Số điện thoại',
    role: 'Vị trí',
    department: 'Phòng ban',
    description: 'Mô tả',
    customer_code: 'Khách hàng',
    manager_code: 'Quản lý dự án',
    due_date: 'Hạn chót',
    start_date: 'Ngày bắt đầu',
    estimated_hours: 'Giờ ước lượng',
    type: 'Loại nhiệm vụ',
    status: 'Trạng thái',
    priority: 'Mức độ ưu tiên',
    code: 'Mã',
    color: 'Màu sắc'
  }
  const fieldName = fieldNames[field] || 'Trường này'

  if (normalizedMessage.includes('is required') || normalizedMessage.includes('required')) {
    return `Vui lòng nhập ${fieldName.toLowerCase()}.`
  }
  if (normalizedMessage.includes('already been taken') || normalizedMessage.includes('taken')) {
    return `${fieldName} này đã tồn tại trong hệ thống.`
  }
  if (normalizedMessage.includes('valid email address') || normalizedMessage.includes('email') && normalizedMessage.includes('valid')) {
    return 'Vui lòng nhập địa chỉ email hợp lệ.'
  }
  if (normalizedMessage.includes('after or equal to today') || normalizedMessage.includes('after_or_equal')) {
    return 'Hạn chót phải từ ngày hôm nay trở đi.'
  }
  if (
    normalizedMessage.includes('must be a date') ||
    normalizedMessage.includes('must be a valid date') ||
    normalizedMessage.includes('invalid date') ||
    normalizedMessage.includes('date format')
  ) {
    return 'Ngày tháng không hợp lệ.'
  }
  if (msg.includes('must not be greater than') || msg.includes('max')) {
    return `${fieldName} vượt quá độ dài hoặc giá trị cho phép.`
  }
  if (msg.includes('must be at least') || msg.includes('min')) {
    return `${fieldName} chưa đạt độ dài hoặc giá trị tối thiểu.`
  }
  if (msg.includes('must be an integer') || msg.includes('integer') || msg.includes('numeric')) {
    return `Vui lòng nhập số hợp lệ cho ${fieldName.toLowerCase()}.`
  }
  if (msg.includes('selected') && (msg.includes('is invalid') || msg.includes('invalid'))) {
    return 'Giá trị được chọn không hợp lệ.'
  }
  if (msg === 'The given data was invalid.') {
    return 'Dữ liệu nhập vào không hợp lệ.'
  }
  return msg
}

// Helper: Phân tích lỗi 422 từ Backend Laravel
async function parseValidationErrors(response) {
  try {
    const data = await response.json()
    if (data.errors) {
      // Laravel trả về { errors: { field: ['msg1', 'msg2'] } }
      const result = {}
      for (const [field, messages] of Object.entries(data.errors)) {
        result[field] = translateValidationMessage(messages[0], field) // Lấy lỗi đầu tiên và dịch sang tiếng Việt
      }
      return result
    }
    if (response.status >= 500) {
      console.error('Lỗi máy chủ:', data.message || data)
      return { _general: 'Máy chủ gặp lỗi khi lưu dữ liệu. Vui lòng kiểm tra migration và nhật ký máy chủ.' }
    }
    return { _general: translateValidationMessage(data.message || 'Đã xảy ra lỗi.') }
  } catch {
    return { _general: 'Đã xảy ra lỗi không xác định.' }
  }
}

export function useProjectWorkspace() {
  const planningProjects = computed(() => projects.value.filter((project) => project.status === 'planning'))
  const activeProjects = computed(() => projects.value.filter((project) => project.status === 'active'))
  const operatingProjects = computed(() => projects.value.filter((project) => project.status === 'planning' || project.status === 'active'))
  const completedProjects = computed(() => projects.value.filter((project) => project.status === 'completed'))
  const projectCompletionRate = computed(() => projects.value.length ? Math.round((completedProjects.value.length / projects.value.length) * 100) : 0)
  
  const completedTasks = computed(() => tasks.value.filter((task) => task.status === 'done'))
  const completionRate = computed(() => tasks.value.length ? Math.round((completedTasks.value.length / tasks.value.length) * 100) : 0)

  function findMember(memberId) {
    if (!memberId) {
      return { id: null, name: 'Chưa phân công', initials: '--', color: 'slate', role: '', avatar: null }
    }
    return members.value.find((member) => member.id === memberId) || { id: null, name: 'Khách', initials: '??', color: 'slate', role: '', avatar: null }
  }

  function findProject(projectId) {
    return projects.value.find((project) => project.id === projectId)
  }

  function findCustomer(customerId) {
    return customers.value.find((customer) => customer.id === customerId)
  }

  function formatDate(dateValue) {
    if (!dateValue) return 'Chưa đặt'
    const [year, month, day] = dateValue.split('T')[0].split('-')
    return `${day}/${month}/${year}`
  }

  function formatDateTime(dateValue) {
    if (!dateValue) return 'Chưa đặt'
    const date = new Date(dateValue)
    if (isNaN(date.getTime())) return 'Ngày không hợp lệ'
    const day = String(date.getDate()).padStart(2, '0')
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const year = date.getFullYear()
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    return `${hours}:${minutes} - ${day}/${month}/${year}`
  }

  function getTaskDeadlineState(dueDate, status) {
    if (!dueDate || status === 'done' || status === 'completed') return 'normal'

    const dueDateKey = String(dueDate).split('T')[0]
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dueDateKey)) return 'normal'

    const now = new Date()
    const todayKey = [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, '0'),
      String(now.getDate()).padStart(2, '0'),
    ].join('-')

    if (dueDateKey === todayKey) return 'due'
    if (dueDateKey < todayKey) return 'overdue'
    return 'upcoming'
  }

  function notify(message) {
    toastMessage.value = message
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { toastMessage.value = '' }, 2600)
  }

  // ========== DỰ ÁN (Projects) - Gọi API ==========

  async function loadTrash() {
    try {
      const [projectResponse, taskResponse] = await Promise.all([
        fetch(`${API_URL}/projects-trash`, { headers: { 'Accept': 'application/json' } }),
        fetch(`${API_URL}/tasks-trash`, { headers: { 'Accept': 'application/json' } }),
      ])

      if (!projectResponse.ok || !taskResponse.ok) {
        notify('Không thể tải dữ liệu trong thùng rác')
        return false
      }

      deletedProjects.value = (await projectResponse.json()).map(mapProject)
      deletedTasks.value = (await taskResponse.json()).map(mapTask)
      return true
    } catch {
      notify('Lỗi kết nối: Không thể tải thùng rác')
      return false
    }
  }

  async function addProject(payload) {
    try {
      const res = await fetch(`${API_URL}/projects`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          name: payload.name,
          description: payload.description || '',
          customer_code: payload.customerId || null,
          manager_code: payload.managerId || null,
          color: payload.color || 'indigo',
          status: payload.status || 'planning',
          start_date: payload.startDate || new Date().toISOString().split('T')[0],
          due_date: payload.dueDate || null,
          progress: Number(payload.progress || 0),
          member_ids: Array.isArray(payload.memberIds) ? payload.memberIds : [],
          template: payload.template || 'blank',
          user_code: currentUser.value.code,
        })
      })
      if (res.ok) {
        const data = await res.json()
        const newProject = mapProject(data.project)
        if (Array.isArray(data.project.tasks)) {
          tasks.value.unshift(...data.project.tasks.map(mapTask))
        }
        if (!newProject.files) newProject.files = []
        
        if (payload.files && payload.files.length > 0) {
          for (const fileObj of payload.files) {
            const uploadRes = await uploadFile(fileObj, 'Project', newProject.id)
            if (uploadRes && uploadRes.attachment) {
              newProject.files.push(mapAttachment(uploadRes.attachment))
            }
          }
        }

        projects.value.unshift(newProject)
        projectModalOpen.value = false
        notify('Đã tạo dự án mới thành công')
        return { success: true, project: newProject }
      } else {
        const errors = await parseValidationErrors(res)
        if (errors._general) notify('Lỗi: ' + errors._general)
        return { success: false, errors }
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể tạo dự án')
      return { success: false, errors: { _general: 'Lỗi kết nối' } }
    }
  }

  async function updateProject(projectId, updates) {
    try {
      const payload = {}
      if (updates.name !== undefined) payload.name = updates.name
      if (updates.description !== undefined) payload.description = updates.description
      if (updates.customerId !== undefined) payload.customer_code = updates.customerId || null
      if (updates.managerId !== undefined) payload.manager_code = updates.managerId || null
      if (updates.color !== undefined) payload.color = updates.color
      if (updates.status !== undefined) payload.status = updates.status
      if (updates.progress !== undefined) payload.progress = Number(updates.progress)
      if (updates.startDate !== undefined || updates.start_date !== undefined) {
        payload.start_date = updates.start_date !== undefined
          ? (updates.start_date || null)
          : (updates.startDate || null)
      }
      if (updates.dueDate !== undefined || updates.due_date !== undefined) {
        payload.due_date = updates.due_date !== undefined
          ? (updates.due_date || null)
          : (updates.dueDate || null)
      }

      const res = await fetch(`${API_URL}/projects/${projectId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
      if (res.ok) {
        const data = await res.json()
        const idx = projects.value.findIndex(p => p.id === projectId)
        if (idx >= 0) Object.assign(projects.value[idx], mapProject(data.project))
        //refreshActivitiesAndNotifications()
        notify('Đã cập nhật thông tin dự án')
        return { success: true, project: idx >= 0 ? projects.value[idx] : mapProject(data.project) }
      }
      const errors = await parseValidationErrors(res)
      return { success: false, errors }
    } catch (e) {
      notify('Lỗi kết nối: Không thể cập nhật dự án')
      return { success: false, errors: { _general: 'Không thể kết nối tới máy chủ.' } }
    }
  }

  async function addCustomer(payload) {
    try {
      const res = await fetch(`${API_URL}/customers`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      })
      if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
      const data = await res.json()
      const customer = mapCustomer(data.customer)
      customers.value.push(customer)
      customers.value.sort((a, b) => a.name.localeCompare(b.name, 'vi'))
      notify('Đã thêm khách hàng mới')
      return { success: true, customer }
    } catch {
      return { success: false, errors: { _general: 'Không thể kết nối tới máy chủ.' } }
    }
  }

  async function deleteProject(projectId) {
    try {
      const deletedProject = projects.value.find(p => p.id === projectId)
      const res = await fetch(`${API_URL}/projects/${projectId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      })
      if (res.ok) {
        projects.value = projects.value.filter(p => p.id !== projectId)
        tasks.value = tasks.value.filter(t => t.projectId !== projectId)
        if (deletedProject && !deletedProjects.value.some(p => p.id === projectId)) {
          deletedProjects.value.unshift({ ...deletedProject, deletedAt: new Date().toISOString(), canRestore: true })
        }
        //refreshActivitiesAndNotifications()
        notify('Đã chuyển dự án vào thùng rác')
        return true
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể xóa dự án')
    }
    return false
  }

  async function restoreProject(projectId) {
    try {
      const res = await fetch(`${API_URL}/projects/${projectId}/restore`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ user_code: currentUser.value.code }),
      })
      if (!res.ok) {
        notify(res.status === 410 ? 'Dự án đã quá hạn khôi phục 30 ngày' : 'Không thể khôi phục dự án')
        return false
      }

      const data = await res.json()
      projects.value.unshift(mapProject(data.project))
      deletedProjects.value = deletedProjects.value.filter(project => project.id !== projectId)

      const taskResponse = await fetch(`${API_URL}/tasks`)
      if (taskResponse.ok) tasks.value = (await taskResponse.json()).map(mapTask)

      notify('Đã khôi phục dự án')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể khôi phục dự án')
      return false
    }
  }

  // ========== NHIỆM VỤ (Tasks) - Gọi API ==========

  async function addTask(payload) {
    try {
      const res = await fetch(`${API_URL}/tasks`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          project_code: payload.projectId || null,
          milestone_code: payload.milestoneId || null,
          title: payload.title,
          description: payload.description || '',
          type: payload.type || 'task',
          status: payload.status || 'todo',
          priority: payload.priority || 'medium',
          start_date: payload.startDate || null,
          due_date: payload.dueDate || null,
          estimated_hours: payload.estimatedHours === '' ? null : payload.estimatedHours,
          blocked_reason: payload.blockedReason || null,
          recurrence: payload.recurrence || null,
          recurrence_until: payload.recurrenceUntil || null,
          assignee_code: payload.assigneeId || null,
          tags: payload.tags !== undefined ? (Array.isArray(payload.tags) ? payload.tags.join(', ') : payload.tags) : null,
          user_code: currentUser.value.code,
        })
      })
      if (res.ok) {
        const data = await res.json()
        tasks.value.unshift(mapTask(data.task))
        closeTaskModal()
        notify('Đã tạo nhiệm vụ mới thành công')
        return { success: true }
      } else {
        const errors = await parseValidationErrors(res)
        if (errors._general) notify('Lỗi: ' + errors._general)
        return { success: false, errors }
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể tạo nhiệm vụ')
      return { success: false, errors: { _general: 'Lỗi kết nối' } }
    }
  }

  async function moveTask(taskId, status) {
    const task = tasks.value.find((item) => item.id === taskId)
    if (!task || task.status === status) return
    const oldStatus = task.status
    task.status = status // Cập nhật giao diện ngay lập tức
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ status })
      })
      if (res.ok) {
        //refreshActivitiesAndNotifications()
        notify('Đã cập nhật trạng thái nhiệm vụ')
      } else {
        task.status = oldStatus // Hoàn tác nếu API lỗi
        notify('Lỗi: Không thể cập nhật trạng thái')
      }
    } catch (e) {
      task.status = oldStatus
      notify('Lỗi kết nối')
    }
  }

  async function toggleTaskComplete(task) {
    const newStatus = task.status === 'done' ? 'todo' : 'done'
    await moveTask(task.id, newStatus)
  }

  async function updateTask(taskId, updates) {
    try {
      const payload = {}
      if (updates.title !== undefined) payload.title = updates.title
      if (updates.description !== undefined) payload.description = updates.description
      if (updates.type !== undefined) payload.type = updates.type
      if (updates.status !== undefined) payload.status = updates.status
      if (updates.priority !== undefined) payload.priority = updates.priority
      if (updates.progress !== undefined) payload.progress = Number(updates.progress)
      if (updates.projectId !== undefined || updates.project_code !== undefined) {
        payload.project_code = updates.project_code !== undefined ? updates.project_code : (updates.projectId || null)
      }
      if (updates.milestoneId !== undefined || updates.milestone_code !== undefined) {
        payload.milestone_code = updates.milestone_code !== undefined ? updates.milestone_code : (updates.milestoneId || null)
      }
      if (updates.assigneeId !== undefined || updates.assignee_code !== undefined) {
        payload.assignee_code = updates.assignee_code !== undefined ? updates.assignee_code : (updates.assigneeId || null)
      }
      if (updates.dueDate !== undefined || updates.due_date !== undefined) {
        payload.due_date = updates.due_date !== undefined ? updates.due_date : (updates.dueDate || null)
      }
      if (updates.startDate !== undefined || updates.start_date !== undefined) {
        payload.start_date = updates.start_date !== undefined ? updates.start_date : (updates.startDate || null)
      }
      if (updates.estimatedHours !== undefined || updates.estimated_hours !== undefined) {
        payload.estimated_hours = updates.estimated_hours !== undefined ? updates.estimated_hours : (updates.estimatedHours || null)
      }
      if (updates.tags !== undefined) {
        payload.tags = Array.isArray(updates.tags) ? updates.tags.join(', ') : (updates.tags || null)
      }
      if (updates.blockedReason !== undefined) payload.blocked_reason = updates.blockedReason || null
      if (updates.blockedOverride !== undefined) payload.blocked_override = Boolean(updates.blockedOverride)
      if (updates.recurrence !== undefined) payload.recurrence = updates.recurrence || null
      if (updates.recurrenceUntil !== undefined) payload.recurrence_until = updates.recurrenceUntil || null

      const res = await fetch(`${API_URL}/tasks/${taskId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
      if (res.ok) {
        const data = await res.json()
        const idx = tasks.value.findIndex(t => t.id === taskId)
        if (idx >= 0) Object.assign(tasks.value[idx], mapTask(data.task))
        //refreshActivitiesAndNotifications()
        notify('Đã cập nhật nhiệm vụ')
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể cập nhật nhiệm vụ')
    }
  }

  async function updateTaskStatus(taskId, status) {
    return moveTask(taskId, status)
  }

  async function deleteTask(taskId) {
    try {
      const deletedTask = tasks.value.find(t => t.id === taskId)
      const res = await fetch(`${API_URL}/tasks/${taskId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      })
      if (res.ok) {
        tasks.value = tasks.value.filter(t => t.id !== taskId)
        if (deletedTask && !deletedTasks.value.some(t => t.id === taskId)) {
          deletedTasks.value.unshift({ ...deletedTask, deletedAt: new Date().toISOString(), canRestore: true })
        }
        if (activeTaskId.value === taskId) {
          activeTaskId.value = null
        }
        //refreshActivitiesAndNotifications()
        notify('Đã chuyển nhiệm vụ vào thùng rác')
        return true
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể xóa nhiệm vụ')
    }
    return false
  }

  async function restoreTask(taskId) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/restore`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ user_code: currentUser.value.code }),
      })
      if (!res.ok) {
        if (res.status === 410) notify('Nhiệm vụ đã quá hạn khôi phục 30 ngày')
        else if (res.status === 409) notify('Hãy khôi phục dự án chứa nhiệm vụ này trước')
        else notify('Không thể khôi phục nhiệm vụ')
        return false
      }

      const data = await res.json()
      tasks.value.unshift(mapTask(data.task))
      deletedTasks.value = deletedTasks.value.filter(task => task.id !== taskId)
      notify('Đã khôi phục nhiệm vụ')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể khôi phục nhiệm vụ')
      return false
    }
  }

  async function addTaskChecklist(taskId, text) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/checklists`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ text }),
      })
      if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }

      const data = await res.json()
      const task = tasks.value.find(item => item.id === taskId)
      if (task) {
        task.checklists.push(mapChecklist(data.checklist))
        task.progress = data.progress
      }
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể thêm công việc con')
      return { success: false }
    }
  }

  async function updateTaskChecklist(taskId, checklistId, updates) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/checklists/${checklistId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(updates),
      })
      if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }

      const data = await res.json()
      const task = tasks.value.find(item => item.id === taskId)
      if (task) {
        const index = task.checklists.findIndex(item => item.id === checklistId)
        if (index >= 0) task.checklists[index] = mapChecklist(data.checklist)
        task.progress = data.progress
      }
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể cập nhật công việc con')
      return { success: false }
    }
  }

  async function deleteTaskChecklist(taskId, checklistId) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/checklists/${checklistId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' },
      })
      if (!res.ok) return false

      const data = await res.json()
      const task = tasks.value.find(item => item.id === taskId)
      if (task) {
        task.checklists = task.checklists.filter(item => item.id !== checklistId)
        task.progress = data.progress
      }
      return true
    } catch {
      notify('Lỗi kết nối: Không thể xóa công việc con')
      return false
    }
  }

  async function addTaskWorkLog(taskId, payload) {
    const task = tasks.value.find(item => item.id === taskId)
    if (!task?.assigneeId) {
      notify('Vui lòng phân công người phụ trách trước khi báo cáo tiến độ')
      return { success: false, errors: { assignee_code: 'Nhiệm vụ chưa có người phụ trách.' } }
    }

    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/work-logs`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          time: payload.time,
          duration_minutes: payload.durationMinutes || null,
          note: payload.note || null,
          checklist_ids: payload.checklistIds || [],
          files: payload.files || [],
          user_code: currentUser.value.code,
        }),
      })

      if (!res.ok) {
        const errors = await parseValidationErrors(res)
        notify(res.status === 422 && errors.assignee_code
          ? 'Vui lòng phân công người phụ trách trước khi báo cáo tiến độ'
          : (errors._general || 'Không thể lưu báo cáo tiến độ'))
        return { success: false, errors }
      }

      const data = await res.json()
      task.workLogs.unshift(mapWorkLog(data.work_log))
      task.checklists = (data.checklists || []).map(mapChecklist)
      task.progress = data.progress
      if (payload.files?.length) task.files.unshift(...payload.files)
      notify('Đã lưu báo cáo tiến độ công việc')
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể lưu báo cáo tiến độ')
      return { success: false }
    }
  }

  async function addComment(taskId, text, fileUrl = null, fileName = null) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/comments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ 
          text, 
          file_url: fileUrl, 
          file_name: fileName,
          user_code: currentUser.value.code
        })
      })
      if (res.ok) {
        const data = await res.json()
        const c = data.comment
        comments.value.unshift({
          ...c,
          id: c.code,
          taskId: c.task_code,
          userId: c.user_code,
          user: c.user,
          createdAt: c.created_at
        })
        notify('Đã gửi bình luận')
        return { success: true }
      } else {
        const errors = await parseValidationErrors(res)
        if (errors._general) notify('Lỗi: ' + errors._general)
        return { success: false, errors }
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể gửi bình luận')
      return { success: false, errors: { _general: 'Lỗi kết nối' } }
    }
  }

  async function loadUsers() {
    try {
      const response = await fetch(`${API_URL}/users`)
      if (response.ok) {
        users.value = await response.json()
      }
    } catch (e) {
      console.error('Failed to load users:', e)
    }
  }

  async function updateUserProfile(code, formData) {
    try {
      const response = await fetch(`${API_URL}/users/${code}`, {
        method: 'POST',
        body: formData // Using FormData for file upload
      })
      
      if (!response.ok) {
        return { success: false, errors: await parseValidationErrors(response) }
      }
      
      const result = await response.json()
      
      // Update currentUser state
      currentUser.value = { ...currentUser.value, ...result.user }
      localStorage.setItem('currentUser', JSON.stringify(currentUser.value))
      
      // Update in users/members lists if applicable
      const userIndex = users.value.findIndex(u => u.code === code)
      if (userIndex !== -1) {
        users.value[userIndex] = { ...users.value[userIndex], ...result.user }
      }
      
      const memberIndex = members.value.findIndex(m => m.code === code || m.id === code)
      if (memberIndex !== -1) {
        members.value[memberIndex] = mapMember({ ...members.value[memberIndex], ...result.user })
      }
      
      return { success: true, user: result.user }
    } catch (e) {
      console.error('Failed to update profile:', e)
      return { success: false, errors: { _general: 'Lỗi mạng khi cập nhật hồ sơ.' } }
    }
  }

  async function loadComments(taskId) {
    try {
      const res = await fetch(`${API_URL}/tasks/${taskId}/comments`)
      if (res.ok) {
        const raw = await res.json()
        const mapped = raw.map(c => ({
          ...c,
          id: c.code,
          taskId: c.task_code,
          userId: c.user_code,
          user: c.user,
          createdAt: c.created_at
        }))
        comments.value = comments.value.filter(c => c.taskId !== taskId)
        comments.value.push(...mapped)
      }
    } catch (e) {
      console.error(e)
    }
  }

  async function uploadFile(file, targetType, targetCode) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('target_type', targetType)
    formData.append('target_code', targetCode)
    formData.append('user_code', currentUser.value.code)
    try {
      const res = await fetch(`${API_URL}/upload`, {
        method: 'POST',
        body: formData
      })
      if (res.ok) {
        return await res.json()
      }
      return null
    } catch (e) {
      return null
    }
  }

  async function downloadArchive(targetType, targetCode, fileName, format = '.zip') {
    try {
      const res = await fetch(`${API_URL}/download-archive`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/zip, application/json'
        },
        body: JSON.stringify({ target_type: targetType, target_code: targetCode, file_name: fileName, format: format })
      })

      if (res.ok) {
        const blob = await res.blob()
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = fileName.endsWith(format) ? fileName : `${fileName}${format}`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        window.URL.revokeObjectURL(url)
        return true
      } else {
        const err = await res.json()
        notify(err.message || 'Lỗi khi tải xuống.')
        return false
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể tải xuống')
      return false
    }
  }

  async function downloadSingleFile(url, fileName) {
    try {
      if (!url) {
        notify('Không tìm thấy đường dẫn tệp.')
        return
      }
      // url = '/storage/attachments/...', so we need full URL
      const fullUrl = url.startsWith('http') || url.startsWith('blob:') ? url : `${BASE_URL}${url.startsWith('/') ? '' : '/'}${url}`
      
      // Tải xuống bằng thẻ <a> trực tiếp để không bao giờ bị chặn bởi chính sách CORS của trình duyệt đối với tệp tĩnh
      const a = document.createElement('a')
      a.href = fullUrl
      if (!url.startsWith('blob:')) {
        a.target = '_blank'
      }
      a.download = fileName || url.split('/').pop() || 'download'
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
    } catch (error) {
      console.error('Download error:', error)
      notify('Lỗi kết nối khi tải tệp xuống.')
    }
  }

  // ========== THÀNH VIÊN (Members) - Gọi API ==========

  async function addMember(payload) {
    try {
      const requestPayload = { ...payload, group_code: payload.groupId || null }
      delete requestPayload.groupId
      const res = await fetch(`${API_URL}/members`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(requestPayload)
      })
      if (res.ok) {
        const data = await res.json()
        members.value.push(mapMember(data.user))
        if (Array.isArray(data.groups)) groups.value = data.groups.map(mapGroup)
        addMemberModalOpen.value = false
        notify('Đã thêm thành viên mới')
        return { success: true }
      } else {
        const errors = await parseValidationErrors(res)
        if (errors._general) notify('Lỗi: ' + errors._general)
        return { success: false, errors }
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể thêm thành viên')
      return { success: false, errors: { _general: 'Lỗi kết nối' } }
    }
  }

  async function updateMember(memberId, updates) {
    try {
      const requestPayload = { ...updates }
      if (updates.groupId !== undefined) {
        requestPayload.group_code = updates.groupId || null
        delete requestPayload.groupId
      }
      const res = await fetch(`${API_URL}/members/${memberId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(requestPayload)
      })
      if (!res.ok) {
        const errors = await parseValidationErrors(res)
        notify('Lỗi: ' + (errors._general || Object.values(errors)[0]))
        return { success: false, errors }
      }

      const data = await res.json()
      const index = members.value.findIndex(m => m.id === memberId)
      if (index !== -1) members.value[index] = mapMember(data.member)
      if (Array.isArray(data.groups)) groups.value = data.groups.map(mapGroup)
      notify('Đã cập nhật thông tin thành viên')
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể cập nhật thành viên')
      return { success: false }
    }
  }

  // ========== NHÓM (Groups) ==========

  async function addGroup(payload) {
    try {
      const res = await fetch(`${API_URL}/groups`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
      if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
      const data = await res.json()
      groups.value.push(mapGroup(data.group))
      addGroupModalOpen.value = false
      notify('Đã tạo nhóm mới thành công')
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể tạo nhóm')
      return { success: false }
    }
  }

  async function updateGroup(groupId, payload) {
    try {
      const res = await fetch(`${API_URL}/groups/${groupId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
      if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
      const data = await res.json()
      const index = groups.value.findIndex(g => g.id === groupId)
      if (index !== -1) groups.value[index] = mapGroup(data.group)
      editGroupModalOpen.value = false
      notify('Đã cập nhật thông tin nhóm')
      return { success: true }
    } catch {
      notify('Lỗi kết nối: Không thể cập nhật nhóm')
      return { success: false }
    }
  }

  async function deleteGroup(groupId) {
    try {
      const res = await fetch(`${API_URL}/groups/${groupId}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } })
      if (!res.ok) return false
      groups.value = groups.value.filter(g => g.id !== groupId)
      editGroupModalOpen.value = false
      notify('Đã xóa nhóm')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể xóa nhóm')
      return false
    }
  }

  async function assignMemberToGroup(memberId, targetGroupId) {
    try {
      const res = await fetch(`${API_URL}/groups/members/${memberId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ group_code: targetGroupId || null })
      })
      if (!res.ok) return false
      groups.value = (await res.json()).map(mapGroup)
      notify(targetGroupId ? 'Đã đưa thành viên vào nhóm' : 'Đã loại thành viên khỏi nhóm')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể phân nhóm thành viên')
      return false
    }
  }

  // ========== FILE & MISC ==========

  function uploadFilesToProject(projectId, files) {
    const project = projects.value.find(p => p.id === projectId)
    if (project) {
      if (!project.files) project.files = []
      project.files.push(...files)
      notify(`Đã tải lên ${files.length} tệp`)
    }
  }

  async function removeFileFromProject(projectId, fileIndex) {
    const project = projects.value.find(p => p.id === projectId)
    const file = project?.files?.[fileIndex]
    if (!file?.code) return false

    try {
      const res = await fetch(`${API_URL}/attachments/${file.code}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      })
      if (!res.ok) return false
      project.files.splice(fileIndex, 1)
      notify('Đã xóa tệp đính kèm')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể xóa tệp')
      return false
    }
  }

  async function updateProjectMembers(projectId, memberIds) {
    const project = projects.value.find(p => p.id === projectId)
    if (!project) return false

    try {
      const res = await fetch(`${API_URL}/projects/${projectId}/members`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ member_ids: memberIds })
      })
      if (!res.ok) return false
      const data = await res.json()
      Object.assign(project, mapProject(data.project))
      notify('Đã cập nhật thành viên dự án')
      return true
    } catch {
      notify('Lỗi kết nối: Không thể cập nhật thành viên dự án')
      return false
    }
  }

  async function removeMemberFromProject(projectId, memberId) {
    const project = projects.value.find(p => p.id === projectId)
    if (!project) return false
    return updateProjectMembers(projectId, project.memberIds.filter(id => id !== memberId))
  }

  async function addProjectUpdate(projectId, payload) {
    const res = await fetch(`${API_URL}/projects/${projectId}/updates`, {
      method: 'POST', body: JSON.stringify(payload),
    })
    if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
    const data = await res.json()
    const project = projects.value.find(item => item.id === projectId)
    if (project) {
      project.health = payload.health
      project.updates = [data.update, ...(project.updates || [])]
    }
    notify('Đã đăng cập nhật dự án')
    return { success: true, update: data.update }
  }

  async function addProjectMilestone(projectId, payload) {
    const res = await fetch(`${API_URL}/projects/${projectId}/milestones`, {
      method: 'POST', body: JSON.stringify(payload),
    })
    if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
    const data = await res.json()
    const project = projects.value.find(item => item.id === projectId)
    if (project) project.milestones = [...(project.milestones || []), data.milestone]
    notify('Đã tạo cột mốc dự án')
    return { success: true, milestone: data.milestone }
  }

  async function deleteProjectMilestone(projectId, milestoneId) {
    const res = await fetch(`${API_URL}/projects/${projectId}/milestones/${milestoneId}`, { method: 'DELETE' })
    if (!res.ok) return false
    const project = projects.value.find(item => item.id === projectId)
    if (project) project.milestones = (project.milestones || []).filter(item => item.code !== milestoneId)
    tasks.value.filter(task => task.milestoneId === milestoneId).forEach(task => { task.milestoneId = null })
    notify('Đã xóa cột mốc')
    return true
  }

  async function setProjectAutomation(projectId, rule, enabled) {
    const res = await fetch(`${API_URL}/projects/${projectId}/automations`, {
      method: 'POST', body: JSON.stringify({ rule, enabled, config: {} }),
    })
    if (!res.ok) {
      notify('Không thể cập nhật tự động hóa')
      return false
    }
    const data = await res.json()
    const project = projects.value.find(item => item.id === projectId)
    if (project) {
      project.automations = [...(project.automations || []).filter(item => item.rule !== rule), data.automation]
    }
    notify(enabled ? 'Đã bật tự động hóa' : 'Đã tắt tự động hóa')
    return true
  }

  async function syncTaskDependencies(taskId, dependencyIds) {
    const res = await fetch(`${API_URL}/tasks/${taskId}/dependencies`, {
      method: 'PUT', body: JSON.stringify({ dependency_ids: dependencyIds }),
    })
    if (!res.ok) return { success: false, errors: await parseValidationErrors(res) }
    const data = await res.json()
    const task = tasks.value.find(item => item.id === taskId)
    if (task) {
      task.dependencies = data.dependencies || []
      task.isBlocked = task.dependencies.some(item => item.status !== 'done') && !task.blockedOverride
    }
    return { success: true }
  }

  async function toggleTaskWatcher(taskId) {
    const res = await fetch(`${API_URL}/tasks/${taskId}/watch`, { method: 'POST' })
    if (!res.ok) return false
    const data = await res.json()
    notify(data.watching ? 'Đã theo dõi nhiệm vụ' : 'Đã bỏ theo dõi nhiệm vụ')
    return data.watching
  }

  function setTheme(isDark) {
    darkMode.value = isDark
    if (isDark) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
    localStorage.setItem('theme', isDark ? 'dark' : 'light')
    notify(isDark ? 'Đã chuyển sang giao diện tối' : 'Đã chuyển sang giao diện sáng')
  }

  // Khôi phục theme từ localStorage hoặc hệ thống
  if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    darkMode.value = true
    document.documentElement.classList.add('dark')
  } else {
    darkMode.value = false
    document.documentElement.classList.remove('dark')
  }

  const markNotificationAsRead = async (id) => {
    try {
      const res = await fetch(`${API_URL}/notifications/${id}/read`, { method: 'PUT' })
      if (res.ok) {
        const notif = notifications.value.find(n => n.id === id)
        if (notif) notif.read = true
      }
    } catch (e) {
      console.error('Failed to mark notification as read', e)
    }
  }

  const markAllNotificationsAsRead = async () => {
    try {
      const res = await fetch(`${API_URL}/notifications/read-all`, { method: 'PUT' })
      if (res.ok) {
        notifications.value.forEach(n => n.read = true)
      }
    } catch (e) {
      console.error('Failed to mark all notifications as read', e)
    }
  }

  return {
    projects,
    tasks,
    deletedProjects,
    deletedTasks,
    members,
    groups,
    customers,
    comments,
    activities,
    apiConnectionError,
    isWorkspaceLoading,
    currentUser,
    loadDataFromAPI,
    refreshLiveData,
    logout,
    navigationItems,
    projectStatusMap,
    taskStatusMap,
    priorityMap,
    globalSearch,
    darkMode,
    sidebarOpen,
    notificationDropdownOpen,
    globalSearchModalOpen,
    projectModalOpen,
    taskModalOpen,
    newTaskProjectId,
    openTaskModal,
    closeTaskModal,
    toastMessage,
    activeTaskId,
    editingProjectId,
    memberDetailModalOpen,
    activeMemberId,
    addMemberModalOpen,
    addMemberTargetGroupId,
    openAddMemberModal,
    closeAddMemberModal,
    addGroupModalOpen,
    editGroupModalOpen,
    activeEditGroupId,
    projectSettingsModalOpen,
    fileUploadModalOpen,
    manageMembersModalOpen,
    importProjectModalOpen,
    notifications,
    planningProjects,
    activeProjects,
    operatingProjects,
    completedProjects,
    projectCompletionRate,
    completedTasks,
    completionRate,
    findMember,
    findProject,
    findCustomer,
    formatDate,
    formatDateTime,
    getTaskDeadlineState,
    formatBytes,
    notify,
    addCustomer,
    validateCustomerDraft,
    addProject,
    updateProject,
    deleteProject,
    restoreProject,
    loadTrash,
    loadUsers,
    updateUserProfile,
    downloadArchive,
    downloadSingleFile,
    addTask,
    moveTask,
    toggleTaskComplete,
    updateTask,
    updateTaskStatus,
    deleteTask,
    restoreTask,
    addTaskChecklist,
    updateTaskChecklist,
    deleteTaskChecklist,
    addTaskWorkLog,
    loadComments,
    addComment,
    uploadFile,
    
    // Member APIs
    addMember,
    updateMember,
    addGroup,
    updateGroup,
    deleteGroup,
    assignMemberToGroup,
    uploadFilesToProject,
    removeFileFromProject,
    updateProjectMembers,
    removeMemberFromProject,
    addProjectUpdate,
    addProjectMilestone,
    deleteProjectMilestone,
    setProjectAutomation,
    syncTaskDependencies,
    toggleTaskWatcher,
    setTheme,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    BASE_URL
  }
}

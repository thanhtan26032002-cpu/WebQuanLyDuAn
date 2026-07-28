import { computed, ref } from 'vue'

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

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
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
    createdAt: p.created_at || p.createdAt,
    memberIds: p.members ? p.members.map(m => m.code) : (p.memberIds || []),
    progress: p.progress || 0,
    color: p.color || 'indigo',
    files: p.attachments ? p.attachments.map(mapAttachment) : (p.files || []),
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
    dueDate: t.due_date || t.dueDate,
    createdAt: t.created_at || t.createdAt,
    tags: parsedTags,
    progress: t.progress || 0,
    files: t.attachments ? t.attachments.map(mapAttachment) : (t.files || []),
    checklists: t.checklists || [],
    workLogs: t.workLogs || [],
  }
}

function mapActivity(a) {
  return {
    ...a,
    id: a.code,
    memberId: a.user_code || a.memberId,
    targetType: a.target_type || a.targetType,
    targetId: a.target_code || a.targetId,
    createdAt: a.created_at || a.createdAt,
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

// ========== STATE (Khởi tạo rỗng, dữ liệu sẽ được tải từ DB) ==========
const projects = ref([])
const tasks = ref([])
const members = ref([])
const groups = ref([])
const comments = ref([])
const activities = ref([])
const users = ref([])

// ========== FETCH DỮ LIỆU TỪ DATABASE ==========
const loadDataFromAPI = async () => {
  try {
    const [resProjects, resMembers, resTasks, resActivities, resNotifications, resUsers] = await Promise.all([
      fetch(`${API_URL}/projects`).catch(() => null),
      fetch(`${API_URL}/members`).catch(() => null),
      fetch(`${API_URL}/tasks`).catch(() => null),
      fetch(`${API_URL}/activities`).catch(() => null),
      fetch(`${API_URL}/notifications`).catch(() => null),
      fetch(`${API_URL}/users`).catch(() => null)
    ])

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

    console.log('✅ Đã tải dữ liệu từ Database thành công')
  } catch (error) {
    console.error('❌ Lỗi kết nối Database:', error)
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

// Tự động gọi khi khởi tạo
loadDataFromAPI()


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
const addGroupModalOpen = ref(false)
const projectSettingsModalOpen = ref(false)
const fileUploadModalOpen = ref(false)
const manageMembersModalOpen = ref(false)
const editGroupModalOpen = ref(false)
const activeEditGroupId = ref(null)
const importProjectModalOpen = ref(false)

const notifications = ref([])

// Lấy thông tin user đang đăng nhập từ localStorage
const currentUser = ref(JSON.parse(localStorage.getItem('currentUser') || 'null') || { code: 'US0001', name: 'Quản trị viên' })

let toastTimer

// Helper: Dịch lỗi xác thực từ Backend sang tiếng Việt
function translateValidationMessage(msg, field = '') {
  if (!msg || typeof msg !== 'string') return 'Dữ liệu không hợp lệ.'
  
  const fieldNames = {
    name: 'Tên',
    title: 'Tiêu đề',
    email: 'Email',
    phone: 'Số điện thoại',
    role: 'Vị trí',
    department: 'Phòng ban',
    description: 'Mô tả',
    due_date: 'Hạn chót',
    start_date: 'Ngày bắt đầu',
    status: 'Trạng thái',
    priority: 'Mức độ ưu tiên',
    code: 'Mã',
    color: 'Màu sắc'
  }
  const fieldName = fieldNames[field] || 'Trường này'

  if (msg.includes('is required') || msg.includes('required')) {
    return `Vui lòng nhập ${fieldName.toLowerCase()}.`
  }
  if (msg.includes('already been taken') || msg.includes('taken')) {
    return `${fieldName} này đã tồn tại trong hệ thống.`
  }
  if (msg.includes('valid email address') || msg.includes('email') && msg.includes('valid')) {
    return 'Vui lòng nhập địa chỉ email hợp lệ.'
  }
  if (msg.includes('after or equal to today') || msg.includes('after_or_equal')) {
    return 'Hạn chót phải từ ngày hôm nay trở đi.'
  }
  if (msg.includes('must be a date') || msg.includes('date') || msg.includes('invalid date')) {
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
    return members.value.find((member) => member.id === memberId) || members.value[0] || { id: null, name: 'Khách', initials: '??', color: 'slate', role: '' }
  }

  function findProject(projectId) {
    return projects.value.find((project) => project.id === projectId)
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

  function notify(message) {
    toastMessage.value = message
    clearTimeout(toastTimer)
    toastTimer = setTimeout(() => { toastMessage.value = '' }, 2600)
  }

  // ========== DỰ ÁN (Projects) - Gọi API ==========

  async function addProject(payload) {
    try {
      const res = await fetch(`${API_URL}/projects`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          name: payload.name,
          description: payload.description || '',
          status: payload.status || 'planning',
          start_date: payload.startDate || new Date().toISOString().split('T')[0],
          due_date: payload.dueDate || null,
          user_code: currentUser.value.code,
        })
      })
      if (res.ok) {
        const data = await res.json()
        const newProject = mapProject(data.project)
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
      const res = await fetch(`${API_URL}/projects/${projectId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(updates)
      })
      if (res.ok) {
        const data = await res.json()
        const idx = projects.value.findIndex(p => p.id === projectId)
        if (idx >= 0) Object.assign(projects.value[idx], mapProject(data.project))
        //refreshActivitiesAndNotifications()
        notify('Đã cập nhật thông tin dự án')
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể cập nhật dự án')
    }
  }

  async function deleteProject(projectId) {
    try {
      const res = await fetch(`${API_URL}/projects/${projectId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      })
      if (res.ok) {
        projects.value = projects.value.filter(p => p.id !== projectId)
        tasks.value = tasks.value.filter(t => t.projectId !== projectId)
        //refreshActivitiesAndNotifications()
        notify('Đã xóa dự án thành công')
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể xóa dự án')
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
          title: payload.title,
          description: payload.description || '',
          status: payload.status || 'todo',
          priority: payload.priority || 'medium',
          due_date: payload.dueDate || null,
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
      if (updates.status !== undefined) payload.status = updates.status
      if (updates.priority !== undefined) payload.priority = updates.priority
      if (updates.progress !== undefined) payload.progress = Number(updates.progress)
      if (updates.projectId !== undefined || updates.project_code !== undefined) {
        payload.project_code = updates.project_code !== undefined ? updates.project_code : (updates.projectId || null)
      }
      if (updates.assigneeId !== undefined || updates.assignee_code !== undefined) {
        payload.assignee_code = updates.assignee_code !== undefined ? updates.assignee_code : (updates.assigneeId || null)
      }
      if (updates.dueDate !== undefined || updates.due_date !== undefined) {
        payload.due_date = updates.due_date !== undefined ? updates.due_date : (updates.dueDate || null)
      }
      if (updates.tags !== undefined) {
        payload.tags = Array.isArray(updates.tags) ? updates.tags.join(', ') : (updates.tags || null)
      }

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
      const res = await fetch(`${API_URL}/tasks/${taskId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      })
      if (res.ok) {
        tasks.value = tasks.value.filter(t => t.id !== taskId)
        if (activeTaskId.value === taskId) {
          activeTaskId.value = null
        }
        //refreshActivitiesAndNotifications()
        notify('Đã xóa nhiệm vụ')
      }
    } catch (e) {
      notify('Lỗi kết nối: Không thể xóa nhiệm vụ')
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
      
      const memberIndex = teamMembers.value.findIndex(m => m.code === code)
      if (memberIndex !== -1) {
        teamMembers.value[memberIndex] = { ...teamMembers.value[memberIndex], ...result.user }
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
      const res = await fetch(`${API_URL}/members`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      })
      if (res.ok) {
        const data = await res.json()
        members.value.push(mapMember(data.user))
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

  function updateMember(memberId, updates) {
    const member = members.value.find(m => m.id === memberId)
    if (member) {
      Object.assign(member, updates)
      notify('Đã cập nhật thông tin thành viên')
    }
  }

  // ========== NHÓM (Groups) - Tạm lưu local ==========

  function addGroup(payload) {
    groups.value.push({
      id: Date.now(),
      name: payload.name,
      icon: payload.icon || '🚀',
      description: payload.description || 'Nhóm mới',
      color: payload.color || 'violet',
      memberIds: []
    })
    addGroupModalOpen.value = false
    notify('Đã tạo nhóm mới thành công')
  }

  function updateGroup(groupId, payload) {
    const group = groups.value.find(g => g.id === groupId)
    if (group) {
      group.name = payload.name
      group.icon = payload.icon || group.icon
      group.description = payload.description || group.description
      group.color = payload.color || group.color
      editGroupModalOpen.value = false
      notify('Đã cập nhật thông tin nhóm')
    }
  }

  function deleteGroup(groupId) {
    const idx = groups.value.findIndex(g => g.id === groupId)
    if (idx !== -1) {
      groups.value.splice(idx, 1)
      editGroupModalOpen.value = false
      notify('Đã xóa nhóm')
    }
  }

  function assignMemberToGroup(memberId, targetGroupId) {
    groups.value.forEach(group => {
      group.memberIds = group.memberIds.filter(id => id !== memberId)
    })
    
    if (targetGroupId) {
      const targetGroup = groups.value.find(g => g.id === targetGroupId)
      if (targetGroup && !targetGroup.memberIds.includes(memberId)) {
        targetGroup.memberIds.push(memberId)
      }
      notify('Đã đưa thành viên vào nhóm')
    } else {
      notify('Đã loại thành viên khỏi nhóm')
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

  function removeFileFromProject(projectId, fileIndex) {
    const project = projects.value.find(p => p.id === projectId)
    if (project && project.files) {
      project.files.splice(fileIndex, 1)
      notify('Đã xóa tệp đính kèm')
    }
  }

  function updateProjectMembers(projectId, memberIds) {
    const project = projects.value.find(p => p.id === projectId)
    if (project) {
      project.memberIds = [...memberIds]
      notify('Đã cập nhật thành viên dự án')
    }
  }

  function removeMemberFromProject(projectId, memberId) {
    const project = projects.value.find(p => p.id === projectId)
    if (project) {
      project.memberIds = project.memberIds.filter(id => id !== memberId)
      notify('Đã xóa thành viên khỏi dự án')
    }
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
    members,
    groups,
    comments,
    activities,
    currentUser,
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
    formatDate,
    formatDateTime,
    formatBytes,
    notify,
    addProject,
    updateProject,
    deleteProject,
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
    setTheme,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    BASE_URL
  }
}

<script setup>
import { computed, nextTick, reactive, ref, onMounted, onUnmounted, watch } from 'vue'
import { X, CheckSquare, Plus, Calendar, Loader2, ExternalLink, Clock, MapPin, ChevronDown, Unplug } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'
import {
  initGoogleAuth,
  requestCalendarAccess,
  fetchCalendarEvents,
  mapEventToTask,
  formatEventTime,
  disconnectGoogle,
  isGoogleConnected,
} from '../../services/googleCalendar'

const { projects, tasks, members, currentUser, taskModalOpen, addTask, newTaskProjectId, closeTaskModal } = useProjectWorkspace()
const isEmployee = computed(() => currentUser.value?.role === 'member')
const selectableProjects = computed(() => {
  const role = currentUser.value?.role
  const userCode = currentUser.value?.code
  if (!userCode) return []
  if (role === 'admin') return projects.value
  return projects.value.filter(project =>
    project.created_by === userCode ||
    project.managerId === userCode ||
    project.memberIds?.includes(userCode)
  )
})
const taskTypes = [
  ['task', 'Công việc chung'], ['analysis', 'Phân tích yêu cầu'], ['ui_ux', 'Thiết kế UI/UX'],
  ['frontend', 'Phát triển Frontend'], ['backend', 'Phát triển Backend'],
  ['api', 'API / Tích hợp hệ thống'], ['database', 'Cơ sở dữ liệu'], ['devops', 'DevOps / Hạ tầng'],
  ['testing', 'Kiểm thử / QA'], ['security', 'Bảo mật'], ['documentation', 'Viết tài liệu'],
  ['research', 'Nghiên cứu kỹ thuật'], ['maintenance', 'Bảo trì / Nâng cấp'], ['feature', 'Tính năng mới'],
  ['bug', 'Sửa lỗi'], ['milestone', 'Cột mốc'],
]
const defaultTags = ['Frontend', 'Backend', 'API', 'Database', 'UI/UX', 'Mobile', 'DevOps', 'QA', 'Security', 'Documentation', 'Research', 'Hotfix', 'Performance', 'Technical debt']
const form = reactive({
  title: '',
  description: '',
  type: 'task',
  status: 'todo',
  priority: 'medium',
  projectId: newTaskProjectId.value || '',
  assigneeId: '',
  startDate: '',
  dueDate: '',
  estimatedHours: '',
  tags: [],
})

const errors = ref({})
const modalBody = ref(null)
const tagSearch = ref('')
const tagDropdownOpen = ref(false)
const errorMessages = computed(() => [...new Set(Object.values(errors.value).filter(Boolean))])
const availableTags = computed(() => [...new Set([...defaultTags, ...tasks.value.flatMap(task => task.tags || [])])].sort((a, b) => a.localeCompare(b, 'vi')))
const filteredTags = computed(() => availableTags.value.filter(tag => !form.tags.includes(tag) && (!tagSearch.value || tag.toLowerCase().includes(tagSearch.value.toLowerCase()))))

// ── Google Calendar State ──
const gcalLoading = ref(false)
const gcalEvents = ref([])
const gcalError = ref('')
const gcalPanelOpen = ref(false)
const gcalConnected = ref(false)
const gcalSelectedEventId = ref(null)

function addTag(value = tagSearch.value) {
  const tag = String(value || '').trim().replace(/^,|,$/g, '')
  if (tag && !form.tags.some(item => item.toLowerCase() === tag.toLowerCase())) form.tags.push(tag)
  tagSearch.value = ''
  tagDropdownOpen.value = true
}

function removeTag(tag) {
  form.tags = form.tags.filter(item => item !== tag)
}

function handleTagKeydown(event) {
  if (event.key === 'Enter' || event.key === ',') {
    event.preventDefault()
    addTag()
  }
}

function closeTagDropdown() {
  window.setTimeout(() => { tagDropdownOpen.value = false }, 150)
}

watch(taskModalOpen, isOpen => {
  if (isOpen) {
    form.projectId = newTaskProjectId.value || form.projectId || ''
    if (isEmployee.value) form.assigneeId = currentUser.value?.code || ''
  }
})

function showErrors(nextErrors) {
  errors.value = nextErrors
  nextTick(() => modalBody.value?.scrollTo({ top: 0, behavior: 'smooth' }))
}

// ── Google Calendar Methods ──
async function connectGoogleCalendar() {
  gcalError.value = ''
  gcalLoading.value = true
  gcalPanelOpen.value = true

  try {
    await initGoogleAuth()
    await requestCalendarAccess()
    gcalConnected.value = true
    const events = await fetchCalendarEvents(30)
    gcalEvents.value = events
    if (events.length === 0) {
      gcalError.value = 'Không tìm thấy sự kiện nào trong 30 ngày tới.'
    }
  } catch (error) {
    if (error.message === 'popup_closed') {
      gcalPanelOpen.value = false
    } else {
      gcalError.value = error.message || 'Không thể kết nối Google Calendar.'
    }
  } finally {
    gcalLoading.value = false
  }
}

function selectCalendarEvent(event) {
  const taskData = mapEventToTask(event)
  form.title = taskData.title
  form.description = taskData.description
  form.startDate = taskData.startDate
  form.dueDate = taskData.dueDate
  if (taskData.estimatedHours) form.estimatedHours = taskData.estimatedHours
  gcalSelectedEventId.value = event.id
  gcalPanelOpen.value = false
  errors.value = {}
}

function handleDisconnect() {
  disconnectGoogle()
  gcalConnected.value = false
  gcalEvents.value = []
  gcalPanelOpen.value = false
  gcalSelectedEventId.value = null
}

function toggleGcalPanel() {
  if (!gcalConnected.value) {
    connectGoogleCalendar()
  } else {
    gcalPanelOpen.value = !gcalPanelOpen.value
  }
}

async function submit() {
  errors.value = {}
  if (!form.title.trim()) {
    showErrors({ title: 'Vui lòng nhập tiêu đề nhiệm vụ.' })
    return
  }
  if (form.startDate && form.dueDate && form.dueDate < form.startDate) {
    showErrors({ due_date: 'Hạn chót phải bằng hoặc sau ngày bắt đầu.' })
    return
  }
  
  const res = await addTask({ ...form, title: form.title.trim() })
  if (res && res.success === false && res.errors) {
    showErrors(res.errors)
  } else if (res && res.success) {
    // modal is closed in useProjectWorkspace
    form.title = ''
    form.description = ''
    form.type = 'task'
    form.startDate = ''
    form.dueDate = ''
    form.estimatedHours = ''
    form.tags = []
    form.projectId = ''
    form.assigneeId = ''
    gcalSelectedEventId.value = null
  }
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') closeTaskModal()
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeTaskModal"></div>
      
      <!-- Modal Content -->
      <form @submit.prevent="submit" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-full overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <CheckSquare class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Tạo nhiệm vụ mới</h2>
              <p class="text-sm text-slate-500">Thêm đầy đủ thông tin để đội ngũ bắt đầu nhanh chóng.</p>
            </div>
          </div>
          <button type="button" @click="closeTaskModal" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div ref="modalBody" class="p-6 overflow-y-auto space-y-5 custom-scrollbar flex-1">
          <div v-if="errorMessages.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Không thể tạo nhiệm vụ. Vui lòng kiểm tra:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
          </div>

          <!-- ═══════ Google Calendar Section ═══════ -->
          <div class="gcal-section">
            <div class="flex items-center gap-3 mb-2">
              <button
                type="button"
                @click="toggleGcalPanel"
                :class="[
                  'gcal-btn group flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200',
                  gcalConnected
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:border-emerald-300'
                    : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-300 hover:bg-blue-50/50 hover:text-blue-700 shadow-sm'
                ]"
              >
                <svg v-if="!gcalLoading" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none">
                  <rect x="3" y="4" width="18" height="18" rx="3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                  <path d="M3 9h18" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="12" cy="15" r="2" fill="#4285F4"/>
                  <circle cx="8" cy="15" r="1.2" fill="#EA4335"/>
                  <circle cx="16" cy="15" r="1.2" fill="#34A853"/>
                  <circle cx="12" cy="12" r="1.2" fill="#FBBC05"/>
                </svg>
                <Loader2 v-else class="w-5 h-5 shrink-0 animate-spin" />
                <span>{{ gcalConnected ? 'Chọn sự kiện từ Calendar' : 'Lấy từ Google Calendar' }}</span>
                <ChevronDown v-if="gcalConnected" :class="['w-4 h-4 transition-transform duration-200', gcalPanelOpen ? 'rotate-180' : '']" />
              </button>

              <button
                v-if="gcalConnected"
                type="button"
                @click="handleDisconnect"
                class="p-2 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 transition-all"
                title="Ngắt kết nối Google Calendar"
              >
                <Unplug class="w-4 h-4" />
              </button>
            </div>

            <!-- Selected event indicator -->
            <div v-if="gcalSelectedEventId && !gcalPanelOpen" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 border border-blue-100 text-xs text-blue-700">
              <Calendar class="w-3.5 h-3.5 shrink-0" />
              <span class="font-medium">Đã điền từ sự kiện Google Calendar</span>
              <button type="button" @click="gcalPanelOpen = true" class="ml-auto text-blue-500 hover:text-blue-700 font-bold underline underline-offset-2">Đổi</button>
            </div>

            <!-- Events Panel -->
            <Transition
              enter-active-class="transition-all duration-300 ease-out"
              enter-from-class="opacity-0 -translate-y-2 max-h-0"
              enter-to-class="opacity-100 translate-y-0 max-h-[400px]"
              leave-active-class="transition-all duration-200 ease-in"
              leave-from-class="opacity-100 translate-y-0 max-h-[400px]"
              leave-to-class="opacity-0 -translate-y-2 max-h-0"
            >
              <div v-if="gcalPanelOpen" class="mt-3 rounded-xl border border-slate-200 bg-slate-50/50 overflow-hidden">
                <!-- Loading State -->
                <div v-if="gcalLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                  <div class="gcal-spinner w-10 h-10 rounded-full border-[3px] border-slate-200 border-t-blue-500"></div>
                  <p class="text-sm text-slate-500 font-medium">Đang kết nối Google Calendar...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="gcalError" class="p-4">
                  <div class="flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-amber-800">{{ gcalError }}</p>
                      <button type="button" @click="connectGoogleCalendar" class="mt-2 text-xs font-bold text-amber-700 hover:text-amber-900 underline underline-offset-2">Thử lại</button>
                    </div>
                  </div>
                </div>

                <!-- Events List -->
                <div v-else-if="gcalEvents.length" class="max-h-[320px] overflow-y-auto custom-scrollbar">
                  <div class="p-2 border-b border-slate-100 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
                    <p class="text-xs font-bold text-slate-500 px-2">{{ gcalEvents.length }} sự kiện trong 30 ngày tới</p>
                  </div>
                  <div class="p-2 space-y-1">
                    <button
                      v-for="event in gcalEvents"
                      :key="event.id"
                      type="button"
                      @click="selectCalendarEvent(event)"
                      :class="[
                        'gcal-event-card w-full text-left rounded-lg p-3 transition-all duration-150',
                        gcalSelectedEventId === event.id
                          ? 'bg-blue-50 border-blue-200 border shadow-sm ring-2 ring-blue-500/20'
                          : 'bg-white border border-slate-100 hover:border-blue-200 hover:bg-blue-50/30 hover:shadow-sm'
                      ]"
                    >
                      <div class="flex items-start gap-3">
                        <div :class="[
                          'w-9 h-9 rounded-lg flex items-center justify-center shrink-0 text-sm font-bold',
                          gcalSelectedEventId === event.id
                            ? 'bg-blue-500 text-white'
                            : 'bg-slate-100 text-slate-500'
                        ]">
                          <Calendar class="w-4 h-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                          <p :class="['text-sm font-semibold truncate', gcalSelectedEventId === event.id ? 'text-blue-900' : 'text-slate-800']">
                            {{ event.summary || '(Không có tiêu đề)' }}
                          </p>
                          <div class="flex items-center gap-3 mt-1">
                            <span class="flex items-center gap-1 text-xs text-slate-500">
                              <Clock class="w-3 h-3" />
                              {{ formatEventTime(event) }}
                            </span>
                            <span v-if="event.location" class="flex items-center gap-1 text-xs text-slate-500 truncate">
                              <MapPin class="w-3 h-3 shrink-0" />
                              <span class="truncate">{{ event.location }}</span>
                            </span>
                          </div>
                          <p v-if="event.description" class="text-xs text-slate-400 mt-1 line-clamp-1">
                            {{ event.description }}
                          </p>
                        </div>
                        <div v-if="gcalSelectedEventId === event.id" class="shrink-0">
                          <div class="w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                          </div>
                        </div>
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Tiêu đề *</label>
            <input v-model="form.title" autofocus placeholder="Ví dụ: Thiết kế wireframe trang chủ" @input="errors.title = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', errors.title ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
            <p v-if="errors.title" class="text-xs font-medium text-red-500">{{ errors.title }}</p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Mô tả</label>
            <textarea v-model="form.description" rows="3" placeholder="Mô tả chi tiết nhiệm vụ..." :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none resize-none', errors.description ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']"></textarea>
            <p v-if="errors.description" class="text-xs font-medium text-red-500">{{ errors.description }}</p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Loại nhiệm vụ</label>
            <select v-model="form.type" class="w-full cursor-pointer appearance-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-800 outline-none transition-all focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-500/10">
              <option v-for="item in taskTypes" :key="item[0]" :value="item[0]">{{ item[1] }}</option>
            </select>
            <p class="text-[11px] text-slate-400">Danh mục chuyên môn thường dùng trong dự án công nghệ thông tin.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Trạng thái</label>
              <select v-model="form.status" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.status ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="todo">Cần làm</option>
                <option value="in_progress">Đang làm</option>
                <option value="done">Hoàn thành</option>
              </select>
              <p v-if="errors.status" class="text-xs font-medium text-red-500">{{ errors.status }}</p>
            </div>
            
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Ưu tiên</label>
              <div :class="['flex gap-2 bg-slate-50 p-1 border rounded-xl', errors.priority ? 'border-red-300' : 'border-slate-200']">
                <button type="button" @click="form.priority = 'low'" :class="['flex-1 py-1.5 rounded-lg text-sm font-medium transition-all flex justify-center items-center gap-1.5', form.priority === 'low' ? 'bg-white shadow-sm text-sky-600 border border-slate-100' : 'text-slate-500 hover:text-slate-700']">
                  <div :class="['w-2 h-2 rounded-full', form.priority === 'low' ? 'bg-sky-500' : 'bg-slate-300']"></div>Thấp
                </button>
                <button type="button" @click="form.priority = 'medium'" :class="['flex-1 py-1.5 rounded-lg text-sm font-medium transition-all flex justify-center items-center gap-1.5', form.priority === 'medium' ? 'bg-white shadow-sm text-amber-600 border border-slate-100' : 'text-slate-500 hover:text-slate-700']">
                  <div :class="['w-2 h-2 rounded-full', form.priority === 'medium' ? 'bg-amber-500' : 'bg-slate-300']"></div>TB
                </button>
                <button type="button" @click="form.priority = 'high'" :class="['flex-1 py-1.5 rounded-lg text-sm font-medium transition-all flex justify-center items-center gap-1.5', form.priority === 'high' ? 'bg-white shadow-sm text-rose-600 border border-slate-100' : 'text-slate-500 hover:text-slate-700']">
                  <div :class="['w-2 h-2 rounded-full', form.priority === 'high' ? 'bg-rose-500' : 'bg-slate-300']"></div>Cao
                </button>
              </div>
              <p v-if="errors.priority" class="text-xs font-medium text-red-500">{{ errors.priority }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Dự án</label>
              <select v-model="form.projectId" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.project_code ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="">— Không có —</option>
                <option v-for="project in selectableProjects" :key="project.id" :value="project.id">{{ project.name }}</option>
              </select>
              <p v-if="errors.project_code" class="text-xs font-medium text-red-500">{{ errors.project_code }}</p>
            </div>
            
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Người phụ trách</label>
              <div v-if="isEmployee" class="rounded-xl border border-violet-100 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-700">
                {{ currentUser?.name || 'Chính tôi' }}
                <p class="mt-0.5 text-[11px] font-normal text-violet-500">Nhiệm vụ do nhân viên tạo được gắn với chính người tạo.</p>
              </div>
              <select v-else v-model="form.assigneeId" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.assignee_code ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="">— Không có —</option>
                <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
              </select>
              <p v-if="errors.assignee_code" class="text-xs font-medium text-red-500">{{ errors.assignee_code }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Ngày bắt đầu</label>
              <input v-model="form.startDate" type="date" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
              <input v-model="form.dueDate" type="date" @input="errors.due_date = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none cursor-pointer', errors.due_date ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.due_date" class="text-xs font-medium text-red-500">{{ errors.due_date }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Ước lượng (giờ)</label>
              <input v-model="form.estimatedHours" type="number" min="0" step="0.5" placeholder="8" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
            </div>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Nhãn</label>
            <div class="relative">
              <div :class="['flex min-h-11 flex-wrap items-center gap-1.5 rounded-xl border bg-slate-50 px-2.5 py-2 transition-all', tagDropdownOpen ? 'border-violet-300 bg-white ring-4 ring-violet-500/10' : 'border-slate-200']">
                <span v-for="tag in form.tags" :key="tag" class="inline-flex items-center gap-1 rounded-lg border border-violet-100 bg-violet-50 px-2 py-1 text-xs font-bold text-violet-700">
                  {{ tag }}
                  <button type="button" @click.stop="removeTag(tag)" class="rounded p-0.5 hover:bg-violet-100" :aria-label="`Bỏ nhãn ${tag}`"><X class="h-3 w-3" /></button>
                </span>
                <input
                  v-model="tagSearch"
                  @focus="tagDropdownOpen = true"
                  @blur="closeTagDropdown"
                  @keydown="handleTagKeydown"
                  placeholder="Chọn hoặc nhập nhãn mới..."
                  class="min-w-[180px] flex-1 bg-transparent px-1 py-0.5 text-sm text-slate-800 outline-none placeholder:text-slate-400"
                />
              </div>
              <div v-if="tagDropdownOpen" class="absolute z-30 mt-2 max-h-52 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                <button v-for="tag in filteredTags" :key="tag" type="button" @mousedown.prevent="addTag(tag)" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-violet-50 hover:text-violet-700">
                  <span>{{ tag }}</span><Plus class="h-3.5 w-3.5" />
                </button>
                <button v-if="tagSearch.trim() && !availableTags.some(tag => tag.toLowerCase() === tagSearch.trim().toLowerCase())" type="button" @mousedown.prevent="addTag()" class="flex w-full items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-left text-sm font-bold text-violet-700 hover:bg-violet-50">
                  <Plus class="h-4 w-4" /> Tạo nhãn "{{ tagSearch.trim() }}"
                </button>
                <p v-if="!filteredTags.length && !tagSearch.trim()" class="px-3 py-3 text-center text-xs text-slate-400">Đã chọn tất cả nhãn có sẵn.</p>
              </div>
            </div>
            <p class="text-[11px] text-slate-400">Có thể chọn nhiều nhãn; nhấn Enter hoặc dấu phẩy để tạo nhãn mới.</p>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="closeTaskModal" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Hủy
          </button>
          <button type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
            <Plus class="w-4 h-4" /> Tạo nhiệm vụ
          </button>
        </footer>
      </form>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}

/* Google Calendar spinner */
@keyframes gcal-spin {
  to { transform: rotate(360deg); }
}
.gcal-spinner {
  animation: gcal-spin 0.8s linear infinite;
}

/* Event card hover animation */
.gcal-event-card {
  transform: translateY(0);
}
.gcal-event-card:hover {
  transform: translateY(-1px);
}

/* Line clamp utility */
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>

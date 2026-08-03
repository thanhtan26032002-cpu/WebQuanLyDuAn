<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FolderKanban, Calendar, Clock, ArrowLeft, Plus, MoreVertical, ListTodo, Paperclip, MoreHorizontal, Check, Settings, ShieldAlert, LogOut, CheckCircle2, Users, CalendarDays, Tag, LayoutGrid, List, Activity, Building2, UserRound, HeartPulse, Flag, Trash2, Zap } from '@lucide/vue'
import draggable from 'vuedraggable'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'
import TaskCard from '../components/common/TaskCard.vue'
import TaskCalendar from '../components/common/TaskCalendar.vue'
import DownloadArchiveModal from '../components/modals/DownloadArchiveModal.vue'
import UserAvatar from '../components/common/UserAvatar.vue'
import { apiFetch } from '../services/api'

const route = useRoute()
const router = useRouter()
const { projects, tasks, members, projectStatusMap, priorityMap, taskStatusMap, findMember, formatDate, getTaskDeadlineState, taskModalOpen, openTaskModal, projectSettingsModalOpen, fileUploadModalOpen, manageMembersModalOpen, editingProjectId, removeFileFromProject, removeMemberFromProject, moveTask, activeTaskId, downloadArchive, downloadSingleFile, activeMemberId, memberDetailModalOpen, addProjectUpdate, addProjectMilestone, deleteProjectMilestone, setProjectAutomation, currentUser } = useProjectWorkspace()

const updateDraft = ref({ health: 'on_track', completed: '', risks: '', next_steps: '' })
const milestoneDraft = ref({ name: '', target_date: '' })
const showUpdateForm = ref(false)
const showMilestoneForm = ref(false)
const savingPlanning = ref(false)
const healthLabels = { on_track: 'Đúng tiến độ', at_risk: 'Có rủi ro', off_track: 'Chậm tiến độ' }
const healthClasses = { on_track: 'bg-emerald-50 text-emerald-700', at_risk: 'bg-amber-50 text-amber-700', off_track: 'bg-rose-50 text-rose-700' }
const automationRules = [
  { rule: 'deadline_reminder', title: 'Nhắc trước hạn', description: 'Nhắc người phụ trách về nhiệm vụ sắp đến hạn.' },
  { rule: 'completion_notify_manager', title: 'Báo quản lý khi hoàn thành', description: 'Gửi thông báo khi một nhiệm vụ được hoàn tất.' },
  { rule: 'status_handover', title: 'Bàn giao theo trạng thái', description: 'Thông báo người theo dõi khi nhiệm vụ đổi trạng thái.' },
]

const automationEnabled = (rule) => Boolean(project.value?.automations?.find(item => item.rule === rule)?.enabled)
const toggleAutomation = (rule) => setProjectAutomation(projectId.value, rule, !automationEnabled(rule))

async function submitProjectUpdate() {
  savingPlanning.value = true
  const result = await addProjectUpdate(projectId.value, updateDraft.value)
  savingPlanning.value = false
  if (result.success) {
    showUpdateForm.value = false
    updateDraft.value = { health: project.value.health, completed: '', risks: '', next_steps: '' }
  }
}

async function submitMilestone() {
  if (!milestoneDraft.value.name.trim()) return
  savingPlanning.value = true
  const result = await addProjectMilestone(projectId.value, milestoneDraft.value)
  savingPlanning.value = false
  if (result.success) {
    showMilestoneForm.value = false
    milestoneDraft.value = { name: '', target_date: '' }
  }
}

const deadlineDateClass = (task) => {
  const state = getTaskDeadlineState(task.dueDate, task.status)
  if (state === 'overdue') return 'text-rose-600 bg-rose-50 border-rose-200'
  if (state === 'due') return 'text-amber-700 bg-amber-50 border-amber-200'
  return 'text-slate-400 border-transparent'
}

const projectId = computed(() => route.params.id)
const isDownloadModalOpen = ref(false)
const project = computed(() => projects.value.find(p => p.id === projectId.value))
const canManageProject = computed(() => {
  const role = currentUser.value?.role
  const userCode = currentUser.value?.code
  return role === 'admin' || (role === 'project_manager' && [project.value?.managerId, project.value?.created_by].includes(userCode))
})
const canAccessProjectFiles = computed(() => {
  const role = currentUser.value?.role
  const userCode = currentUser.value?.code
  return role === 'admin' ||
    [project.value?.managerId, project.value?.created_by].includes(userCode) ||
    project.value?.memberIds?.includes(userCode)
})
const canCreateTaskInProject = computed(() => {
  const userCode = currentUser.value?.code
  return Boolean(userCode) && (
    canManageProject.value ||
    project.value?.memberIds?.includes(userCode)
  )
})
const projectTasks = computed(() => tasks.value.filter(t => t.projectId === projectId.value))
const projectDeadlineLabel = computed(() => {
  if (project.value?.deadlineState === 'overdue') return `Quá hạn ${project.value.overdueDays} ngày`
  if (project.value?.deadlineState === 'completed_late') return `Hoàn thành trễ ${project.value.lateDays} ngày`
  if (project.value?.deadlineState === 'due') return 'Đến hạn hôm nay'
  return ''
})

const taskViewMode = ref('board') // 'list' or 'board'
const columns = [
  { id: 'todo', title: 'Cần làm', color: 'bg-slate-400' },
  { id: 'in_progress', title: 'Đang làm', color: 'bg-amber-500' },
  { id: 'done', title: 'Hoàn thành', color: 'bg-emerald-500' }
]

const getTasksByStatus = (status) => {
  return projectTasks.value.filter(t => t.status === status)
}

const onTaskChange = (event, newStatus) => {
  if (event.added) {
    const task = event.added.element
    moveTask(task.id, newStatus)
  }
}

const openTask = (task) => {
  activeTaskId.value = task.id
}

const openMemberDetail = (memberId) => {
  activeMemberId.value = memberId
  memberDetailModalOpen.value = true
}

const handleEdit = () => {
  editingProjectId.value = project.value.id
  projectSettingsModalOpen.value = true
}

const openFileUpload = () => {
  editingProjectId.value = project.value.id
  fileUploadModalOpen.value = true
}

const handleDownloadArchive = async (payload) => {
  await downloadArchive(payload.targetType, payload.targetCode, payload.fileName, payload.format)
  isDownloadModalOpen.value = false
}

const openManageMembers = () => {
  editingProjectId.value = project.value.id
  manageMembersModalOpen.value = true
}

const confirmRemoveFile = (idx) => {
  if (confirm('Bạn có chắc chắn muốn xóa tệp này khỏi dự án?')) {
    removeFileFromProject(project.value.id, idx)
  }
}

const confirmRemoveMember = (memberId) => {
  if (confirm('Bạn có chắc chắn muốn loại bỏ thành viên này khỏi dự án?')) {
    removeMemberFromProject(project.value.id, memberId)
  }
}
const projectMembers = computed(() => project.value ? project.value.memberIds.map(id => findMember(id)) : [])
const projectFiles = computed(() => project.value?.files || [])
const projectActivities = ref([])
const activityMeta = ref({ currentPage: 0, lastPage: 1, total: 0 })
const activitiesLoading = ref(false)
const showProjectActivities = ref(true)

const mapProjectActivity = (activity) => {
  const actorName = activity.user?.name || 'Người dùng hệ thống'
  return {
    ...activity,
    id: activity.code,
    userId: activity.user?.code || activity.user_code || null,
    action: activity.action,
    target: activity.target_label || '',
    detail: activity.detail,
    createdAt: activity.created_at,
    actor: {
      name: actorName,
      color: activity.user?.color || 'slate',
      avatar: activity.user?.avatar || null,
    },
  }
}

const loadProjectActivityPage = async (page = 1) => {
  if (!projectId.value || activitiesLoading.value) return
  activitiesLoading.value = true
  try {
    const response = await apiFetch(`/api/projects/${projectId.value}/activities?per_page=50&page=${page}`)
    if (!response.ok) return
    const payload = await response.json()
    const mapped = (payload.data || []).map(mapProjectActivity)
    projectActivities.value = page === 1
      ? mapped
      : [...projectActivities.value, ...mapped.filter(item => !projectActivities.value.some(existing => existing.id === item.id))]
    activityMeta.value = {
      currentPage: payload.current_page || page,
      lastPage: payload.last_page || 1,
      total: payload.total || mapped.length,
    }
  } finally {
    activitiesLoading.value = false
  }
}

const toggleProjectActivities = async () => {
  showProjectActivities.value = !showProjectActivities.value
  if (showProjectActivities.value) await loadProjectActivityPage(1)
}

let activityRefreshTimer
const handleActivityChanged = () => {
  window.clearTimeout(activityRefreshTimer)
  activityRefreshTimer = window.setTimeout(() => loadProjectActivityPage(1), 250)
}

watch(projectId, () => {
  projectActivities.value = []
  activityMeta.value = { currentPage: 0, lastPage: 1, total: 0 }
  loadProjectActivityPage(1)
}, { immediate: true })
onMounted(() => window.addEventListener('ringnet:activity-changed', handleActivityChanged))
onUnmounted(() => {
  window.removeEventListener('ringnet:activity-changed', handleActivityChanged)
  window.clearTimeout(activityRefreshTimer)
})

// Calculate time ago string
const timeAgo = (dateStr) => {
  const timestamp = new Date(dateStr)
  if (Number.isNaN(timestamp.getTime())) return 'Không rõ thời gian'
  const diffMins = Math.max(0, Math.floor((Date.now() - timestamp.getTime()) / 60000))
  if (diffMins < 1) return 'Vừa xong'
  if (diffMins < 60) return `${diffMins} phút trước`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours} giờ trước`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 30) return `${diffDays} ngày trước`
  return timestamp.toLocaleDateString('vi-VN')
}

const formatActivityTime = (dateStr) => {
  const timestamp = new Date(dateStr)
  return Number.isNaN(timestamp.getTime())
    ? ''
    : timestamp.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' })
}

const activityTargetLabel = (targetType) => ({
  Project: 'Dự án',
  Task: 'Nhiệm vụ',
  TaskComment: 'Bình luận nhiệm vụ',
}[targetType] || 'Hoạt động')

const stats = computed(() => [
  { label: 'Cần làm', value: projectTasks.value.filter(t => t.status === 'todo').length, icon: ListTodo, color: 'text-slate-600', bg: 'bg-slate-100' },
  { label: 'Đang làm', value: projectTasks.value.filter(t => t.status === 'in_progress').length, icon: Clock, color: 'text-amber-600', bg: 'bg-amber-100' },
  { label: 'Hoàn thành', value: projectTasks.value.filter(t => t.status === 'done').length, icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-100' },
])

const projectHeroClass = computed(() => {
  if (project.value?.deadlineState === 'overdue' || project.value?.health === 'off_track') return 'from-rose-600 to-red-700'
  if (project.value?.status === 'on_hold' || project.value?.health === 'at_risk') return 'from-amber-500 to-orange-600'
  if (project.value?.status === 'completed') return 'from-emerald-600 to-teal-700'
  if (project.value?.status === 'planning') return 'from-slate-600 to-slate-700'
  return 'from-indigo-600 to-violet-700'
})

const priorityClasses = {
  high: 'bg-rose-50 text-rose-600 border border-rose-100',
  medium: 'bg-amber-50 text-amber-600 border border-amber-100',
  low: 'bg-sky-50 text-sky-600 border border-sky-100'
}

const statusClasses = {
  todo: 'bg-slate-100 text-slate-600',
  in_progress: 'bg-amber-50 text-amber-600',
  done: 'bg-emerald-50 text-emerald-600',
}

const taskTypeLabels = { task: 'Công việc', analysis: 'Phân tích', ui_ux: 'UI/UX', frontend: 'Frontend', backend: 'Backend', api: 'API', database: 'Cơ sở dữ liệu', devops: 'DevOps', testing: 'Kiểm thử', security: 'Bảo mật', documentation: 'Tài liệu', research: 'Nghiên cứu', maintenance: 'Bảo trì', bug: 'Lỗi', feature: 'Tính năng', milestone: 'Cột mốc' }

const projectStatusClasses = {
  active: 'bg-emerald-50 text-emerald-700',
  planning: 'bg-slate-100 text-slate-600',
  on_hold: 'bg-amber-50 text-amber-700',
  completed: 'bg-violet-50 text-violet-700',
}
</script>

<template>
  <div>
    <!-- Not found -->
    <div v-if="!project" class="flex flex-col items-center justify-center py-24 text-center">
    <FolderKanban class="w-16 h-16 text-slate-300 mb-4" />
    <h2 class="text-2xl font-bold text-slate-900 mb-2">Không tìm thấy dự án</h2>
    <p class="text-slate-500 mb-6">Dự án này không tồn tại hoặc đã bị xóa.</p>
    <button @click="router.push('/projects')" class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-medium shadow-md shadow-violet-500/25">
      Quay lại danh sách dự án
    </button>
  </div>

  <div v-else class="space-y-6 pb-12">
    <!-- Back button -->
    <button @click="router.push('/projects')" class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">
      <ArrowLeft class="w-4 h-4" /> Dự án
    </button>

    <!-- Project Hero -->
    <div :class="['rounded-2xl bg-gradient-to-br text-white p-6 shadow-lg relative overflow-hidden', projectHeroClass]">
      <!-- Decorative circles -->
      <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
      <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-black/10 rounded-full blur-2xl"></div>

      <div class="relative flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
              <FolderKanban class="w-6 h-6 text-white" />
            </div>
            <span :class="['px-2.5 py-1 rounded-full text-xs font-bold backdrop-blur-sm bg-white/20']">
              {{ projectStatusMap[project.status]?.label }}
            </span>
            <span v-if="projectDeadlineLabel" class="rounded-full bg-rose-500 px-2.5 py-1 text-xs font-bold text-white shadow-sm">
              {{ projectDeadlineLabel }}
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-bold mb-2">{{ project.name }}</h1>
          <p class="text-white/80 text-sm max-w-lg">{{ project.description }}</p>
        </div>
        <button 
          v-if="canManageProject"
          @click="handleEdit"
          class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition-colors shrink-0"
          title="Chỉnh sửa dự án"
        >
          <MoreHorizontal class="w-5 h-5" />
        </button>
      </div>

      <!-- Meta -->
      <div class="relative mt-6 flex flex-wrap gap-4 text-sm font-medium text-white/90">
        <div class="flex items-center gap-1.5">
          <CalendarDays class="w-4 h-4" />
          Hạn: {{ formatDate(project.dueDate) }}
        </div>
        <div class="flex items-center gap-1.5">
          <Users class="w-4 h-4" />
          {{ project.memberIds.length }} thành viên
        </div>
        <div class="flex items-center gap-1.5">
          <CheckCircle2 class="w-4 h-4" />
          {{ project.progress }}% hoàn thành
        </div>
        <div class="flex items-center gap-1.5">
          <Building2 class="w-4 h-4" />
          {{ project.customer?.name || 'Chưa gắn khách hàng' }}
        </div>
      </div>

      <!-- Progress bar -->
      <div class="relative mt-4">
        <div class="h-2 w-full bg-white/20 rounded-full overflow-hidden">
          <div class="h-full bg-white/80 rounded-full transition-all duration-500" :style="{ width: `${project.progress}%` }"></div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-400">Khách hàng</p><p class="mt-1 truncate text-sm font-bold text-slate-800">{{ project.customer?.name || 'Chưa cập nhật' }}</p><p class="truncate text-xs text-slate-500">{{ project.customer?.company || project.customer?.email || '—' }}</p></div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-400">Quản lý dự án</p><p class="mt-1 truncate text-sm font-bold text-slate-800">{{ project.manager?.name || findMember(project.managerId).name }}</p><p class="truncate text-xs text-slate-500">{{ project.manager?.role || 'Chưa phân công' }}</p></div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-400">Ngày bắt đầu</p><p class="mt-1 text-sm font-bold text-slate-800">{{ formatDate(project.startDate) }}</p></div>
      <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-slate-400">Hạn hoàn thành</p><p class="mt-1 text-sm font-bold text-slate-800">{{ formatDate(project.dueDate) }}</p></div>
    </div>

    <section
      v-if="project.deadlineState === 'overdue' || project.delayReason || project.recoveryPlan || project.deadlineExtensions?.length"
      class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm"
    >
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="flex items-center gap-2 font-bold text-slate-900"><ShieldAlert class="h-5 w-5 text-rose-500" /> Kiểm soát quá hạn</h2>
          <p class="mt-1 text-sm text-slate-500">Nguyên nhân, phương án phục hồi và toàn bộ lần thay đổi hạn chót.</p>
        </div>
        <button v-if="canManageProject" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100" @click="handleEdit">
          Cập nhật kế hoạch
        </button>
      </header>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-xl bg-rose-50 p-4">
          <p class="text-xs font-bold uppercase tracking-wide text-rose-600">Lý do chậm</p>
          <p class="mt-2 text-sm leading-6 text-slate-700">{{ project.delayReason || 'Chưa được quản lý dự án cập nhật.' }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 p-4">
          <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Kế hoạch khắc phục</p>
          <p class="mt-2 text-sm leading-6 text-slate-700">{{ project.recoveryPlan || 'Chưa được quản lý dự án cập nhật.' }}</p>
        </div>
      </div>
      <div v-if="project.deadlineExtensions?.length" class="mt-5 border-t border-slate-100 pt-4">
        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Lịch sử gia hạn</p>
        <div class="grid gap-3 lg:grid-cols-2">
          <article v-for="item in project.deadlineExtensions" :key="item.id" class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-sm font-bold text-slate-800">{{ formatDate(item.oldDueDate) }} → {{ formatDate(item.newDueDate) }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ item.reason }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ item.actor?.name || 'Người quản lý' }} · {{ formatDate(item.createdAt) }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div :class="['w-12 h-12 rounded-xl flex items-center justify-center shrink-0', stat.bg, stat.color]">
          <component :is="stat.icon" class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-2xl font-bold text-slate-900 leading-tight">{{ stat.value }}</h3>
          <p class="text-sm text-slate-500">{{ stat.label }}</p>
        </div>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <header class="flex items-start justify-between gap-4">
          <div><h2 class="flex items-center gap-2 text-lg font-bold"><HeartPulse class="h-5 w-5 text-violet-500" /> Sức khỏe dự án</h2><p class="mt-1 text-sm text-slate-500">Tình trạng, rủi ro và bước tiếp theo.</p></div>
          <span :class="['rounded-full px-3 py-1 text-xs font-bold', healthClasses[project.health] || healthClasses.on_track]">{{ healthLabels[project.health] || healthLabels.on_track }}</span>
        </header>
        <button v-if="canManageProject && !showUpdateForm" class="mt-5 w-full rounded-xl border border-dashed border-violet-200 bg-violet-50/50 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50" @click="showUpdateForm = true; updateDraft.health = project.health">+ Đăng cập nhật định kỳ</button>
        <form v-else class="mt-5 space-y-3 rounded-xl bg-slate-50 p-4" @submit.prevent="submitProjectUpdate">
          <select v-model="updateDraft.health" class="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-sm font-semibold"><option value="on_track">Đúng tiến độ</option><option value="at_risk">Có rủi ro</option><option value="off_track">Chậm tiến độ</option></select>
          <textarea v-model="updateDraft.completed" rows="2" placeholder="Những việc đã hoàn thành" class="w-full rounded-lg border border-slate-200 p-3 text-sm"></textarea>
          <textarea v-model="updateDraft.risks" rows="2" placeholder="Rủi ro hoặc vướng mắc" class="w-full rounded-lg border border-slate-200 p-3 text-sm"></textarea>
          <textarea v-model="updateDraft.next_steps" rows="2" placeholder="Việc tiếp theo" class="w-full rounded-lg border border-slate-200 p-3 text-sm"></textarea>
          <div class="flex justify-end gap-2"><button type="button" class="px-3 py-2 text-sm text-slate-500" @click="showUpdateForm = false">Hủy</button><button :disabled="savingPlanning" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Đăng cập nhật</button></div>
        </form>
        <div v-if="project.updates?.length" class="mt-5 space-y-3">
          <article v-for="update in project.updates.slice(0, 3)" :key="update.code" class="rounded-xl border border-slate-100 p-4 text-sm">
            <div class="flex justify-between"><span :class="['rounded px-2 py-1 text-[10px] font-bold', healthClasses[update.health]]">{{ healthLabels[update.health] }}</span><span class="text-xs text-slate-400">{{ formatDate(update.created_at) }}</span></div>
            <p v-if="update.completed" class="mt-3 text-slate-700"><b>Đã làm:</b> {{ update.completed }}</p><p v-if="update.risks" class="mt-1 text-slate-600"><b>Rủi ro:</b> {{ update.risks }}</p><p v-if="update.next_steps" class="mt-1 text-slate-600"><b>Tiếp theo:</b> {{ update.next_steps }}</p>
          </article>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <header class="flex items-start justify-between gap-4"><div><h2 class="flex items-center gap-2 text-lg font-bold"><Flag class="h-5 w-5 text-violet-500" /> Cột mốc</h2><p class="mt-1 text-sm text-slate-500">Các giai đoạn chính của dự án.</p></div><button v-if="canManageProject" class="rounded-lg bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-700" @click="showMilestoneForm = !showMilestoneForm">+ Thêm</button></header>
        <form v-if="showMilestoneForm" class="mt-4 flex flex-col gap-2 rounded-xl bg-slate-50 p-3 sm:flex-row" @submit.prevent="submitMilestone"><input v-model="milestoneDraft.name" required placeholder="Tên cột mốc" class="min-w-0 flex-1 rounded-lg border border-slate-200 p-2.5 text-sm" /><input v-model="milestoneDraft.target_date" type="date" class="rounded-lg border border-slate-200 p-2.5 text-sm" /><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Lưu</button></form>
        <div v-if="project.milestones?.length" class="mt-5 space-y-3">
          <article v-for="milestone in project.milestones" :key="milestone.code" class="group rounded-xl border border-slate-100 p-4">
            <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-800">{{ milestone.name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ formatDate(milestone.target_date) }}</p></div><button v-if="canManageProject" title="Xóa cột mốc" aria-label="Xóa cột mốc" class="rounded-lg p-1.5 text-slate-300 opacity-0 hover:bg-rose-50 hover:text-rose-500 group-hover:opacity-100" @click="deleteProjectMilestone(project.id, milestone.code)"><Trash2 class="h-4 w-4" /></button></div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" :style="{ width: `${milestone.progress || 0}%` }"></div></div><p class="mt-1 text-right text-[10px] font-bold text-slate-400">{{ milestone.progress || 0 }}%</p>
          </article>
        </div><p v-else class="mt-8 text-center text-sm text-slate-400">Chưa có cột mốc nào.</p>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
      <header><h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><Zap class="h-5 w-5 text-violet-500" /> Tự động hóa đơn giản</h2><p class="mt-1 text-sm text-slate-500">Giảm thao tác lặp lại nhưng vẫn giữ quy trình dễ kiểm soát.</p></header>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <button v-for="item in automationRules" :key="item.rule" type="button" :disabled="!canManageProject" class="flex items-start justify-between gap-4 rounded-xl border p-4 text-left transition disabled:cursor-default" :class="automationEnabled(item.rule) ? 'border-violet-200 bg-violet-50/60' : 'border-slate-100 hover:border-slate-200'" @click="toggleAutomation(item.rule)">
          <span><strong class="block text-sm text-slate-800">{{ item.title }}</strong><span class="mt-1 block text-xs leading-5 text-slate-500">{{ item.description }}</span></span>
          <span :class="['mt-0.5 flex h-6 w-11 shrink-0 rounded-full p-0.5 transition', automationEnabled(item.rule) ? 'justify-end bg-violet-500' : 'justify-start bg-slate-200']"><span class="h-5 w-5 rounded-full bg-white shadow-sm"></span></span>
        </button>
      </div>
    </section>

    <!-- Grid Members & Files -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Members -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-slate-900">Thành viên</h2>
          <button v-if="canManageProject" @click="openManageMembers" class="text-sm font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
            <Plus class="w-4 h-4" /> Thêm
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 content-start">
          <div
            v-for="member in projectMembers"
            :key="member.id"
            @click="openMemberDetail(member.id)"
            class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-slate-100/80 shadow-sm shadow-slate-200/50 group transition-all duration-300 hover:-translate-y-1 hover:shadow-md hover:shadow-violet-500/10 hover:border-violet-200 relative overflow-hidden cursor-pointer"
          >
            <div class="absolute inset-0 bg-gradient-to-r from-violet-500/0 via-violet-500/0 to-violet-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-sm shrink-0 relative z-10', `bg-gradient-to-br from-${member.color}-400 to-${member.color}-600`]">
              {{ member.initials }}
            </div>
            <div class="min-w-0 flex-1 relative z-10">
              <p class="text-sm font-bold text-slate-800 truncate group-hover:text-violet-700 transition-colors">{{ member.name }}</p>
              <p class="text-xs font-medium text-slate-500 truncate">{{ member.role }}</p>
            </div>
            <button v-if="canManageProject" @click.stop="confirmRemoveMember(member.id)" title="Loại khỏi dự án" class="relative z-10 shrink-0 text-slate-300 hover:text-rose-500 p-2 rounded-xl hover:bg-rose-50 transition-all opacity-0 group-hover:opacity-100 hover:scale-110">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          
          <!-- Empty State for Members -->
          <div v-if="projectMembers.length === 0" class="w-full text-center py-6 text-slate-400 text-sm">
            Chưa có thành viên nào tham gia.
          </div>
        </div>
      </div>

      <!-- Files -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-slate-900">Tệp đính kèm</h2>
          <div class="flex gap-2">
            <button v-if="canAccessProjectFiles" @click="isDownloadModalOpen = true" :disabled="!projectFiles.length" class="text-sm font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50">
              Tải xuống tất cả
            </button>
            <button v-if="canManageProject" @click="openFileUpload" class="text-sm font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
              <Plus class="w-4 h-4" /> Tải lên
            </button>
          </div>
        </div>
        
        <div v-if="projectFiles.length > 0" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-2 max-h-[300px]">
          <div v-for="(file, idx) in projectFiles" :key="idx" class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl p-3 hover:border-violet-200 transition-colors group">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate leading-tight mb-0.5 group-hover:text-violet-600 transition-colors cursor-pointer">{{ file.name }}</p>
                <p class="text-[10px] text-slate-500 uppercase tracking-wide font-medium">{{ file.size }} • {{ formatDate(file.uploadedAt) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button v-if="canAccessProjectFiles" @click.prevent="downloadSingleFile(file.url, file.name, file.code)" title="Tải xuống" class="text-slate-300 hover:text-violet-600 p-2 rounded-lg hover:bg-violet-50 transition-colors flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              </button>
              <button v-if="canManageProject" @click="confirmRemoveFile(idx)" title="Xóa tệp" class="text-slate-300 hover:text-rose-500 p-2 rounded-lg hover:bg-rose-50 transition-colors flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-8 text-center flex-1 bg-slate-50 rounded-xl border border-dashed border-slate-200 transition-colors" :class="canManageProject ? 'cursor-pointer hover:bg-slate-100' : ''" @click="canManageProject && openFileUpload()">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300 mb-2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <p class="text-sm font-medium text-slate-600 mb-1">Chưa có tệp nào</p>
          <p class="text-xs text-slate-400">Nhấn vào đây để tải lên tài liệu.</p>
        </div>
      </div>
      
      <!-- Activity Timeline -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col md:col-span-2 lg:col-span-3">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <Activity class="w-5 h-5 text-violet-500" /> Nhật ký hoạt động
          </h2>
          <button
            @click="toggleProjectActivities"
            class="text-xs font-semibold text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-2.5 py-1 rounded-lg transition-colors cursor-pointer shadow-2xs"
          >
            {{ showProjectActivities ? 'Ẩn' : `Xem (${activityMeta.total})` }}
          </button>
        </div>
        
        <div v-if="showProjectActivities && projectActivities.length" class="max-h-[500px] overflow-y-auto custom-scrollbar pr-4 -mr-4">
          <div class="relative pb-2">
            <div class="absolute top-2 bottom-2 left-[19px] w-px bg-slate-200"></div>
            <div class="space-y-6 relative">
              <div v-for="activity in projectActivities" :key="activity.id" class="flex gap-4">
                <!-- Avatar Node -->
                <UserAvatar v-if="activity.userId" :member-id="activity.userId" size="md" :show-popover="false" class="relative z-10 ring-4 ring-white rounded-full" />
                <div v-else class="relative z-10 w-10 h-10 rounded-full flex items-center justify-center shrink-0 border-4 border-white bg-slate-500 shadow-sm text-xs font-bold text-white">HT</div>
                <!-- Content -->
                <div class="min-w-0 flex-1 pt-2">
                  <p class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-sm leading-relaxed text-slate-900">
                    <span class="font-semibold">{{ activity.actor?.name || 'Người dùng hệ thống' }}</span>
                    <span class="text-slate-500">{{ activity.action }}</span>
                    <span class="font-medium text-slate-800">{{ activity.target }}</span>
                  </p>
                  <div class="mt-2 flex flex-wrap items-start gap-2">
                    <span class="inline-flex rounded-md bg-violet-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-600">
                      {{ activityTargetLabel(activity.target_type) }}
                    </span>
                    <div v-if="activity.detail" class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-600">
                      {{ activity.detail }}
                    </div>
                  </div>
                  <p class="text-xs text-slate-400 mt-1.5 font-medium" :title="formatActivityTime(activity.createdAt)">
                    {{ timeAgo(activity.createdAt) }} · {{ formatActivityTime(activity.createdAt) }}
                  </p>
                </div>
              </div>
            </div>
            <button
              v-if="activityMeta.currentPage < activityMeta.lastPage"
              :disabled="activitiesLoading"
              class="mt-6 w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 text-sm font-semibold text-slate-600 hover:border-violet-200 hover:text-violet-700 disabled:opacity-50"
              @click="loadProjectActivityPage(activityMeta.currentPage + 1)"
            >
              {{ activitiesLoading ? 'Đang tải...' : `Xem thêm (${projectActivities.length}/${activityMeta.total})` }}
            </button>
          </div>
        </div>
        <div v-else class="text-center py-8 text-slate-400 text-sm font-medium">
          {{ activitiesLoading ? 'Đang tải nhật ký hoạt động...' : (showProjectActivities ? 'Chưa có hoạt động nào trong dự án.' : 'Nhật ký hoạt động đang được ẩn.') }}
        </div>
      </div>

    </div>

    <!-- Tasks -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col min-h-[500px]">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between px-6 py-4 border-b border-slate-100 gap-4">
        <div>
          <h2 class="text-lg font-bold text-slate-900">Nhiệm vụ</h2>
          <p class="text-sm text-slate-500">{{ projectTasks.length }} nhiệm vụ trong dự án này</p>
        </div>
        <div class="flex items-center gap-3">
          <!-- View Toggle -->
          <div class="flex items-center bg-slate-100 rounded-lg p-1">
            <button 
              @click="taskViewMode = 'list'"
              :class="['p-1.5 rounded-md transition-all', taskViewMode === 'list' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700']"
              title="Xem dạng danh sách"
            >
              <List class="w-4 h-4" />
            </button>
            <button 
              @click="taskViewMode = 'board'"
              :class="['p-1.5 rounded-md transition-all', taskViewMode === 'board' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700']"
              title="Xem dạng bảng (Kanban)"
            >
              <LayoutGrid class="w-4 h-4" />
            </button>
            <button
              @click="taskViewMode = 'calendar'"
              :class="['p-1.5 rounded-md transition-all', taskViewMode === 'calendar' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700']"
              title="Xem dạng lịch"
            >
              <CalendarDays class="w-4 h-4" />
            </button>
          </div>
          <button
            v-if="canCreateTaskInProject"
            @click="openTaskModal(project?.id)"
            class="flex items-center gap-2 bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-lg transition-all shrink-0"
          >
            <Plus class="w-4 h-4" /> Thêm nhiệm vụ
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="projectTasks.length === 0" class="py-16 flex flex-col items-center text-center flex-1 justify-center">
        <ListTodo class="w-12 h-12 text-slate-300 mb-3" />
        <p class="text-slate-500 font-medium">Chưa có nhiệm vụ nào trong dự án này.</p>
        <button v-if="canCreateTaskInProject" @click="openTaskModal(project?.id)" class="mt-4 text-sm text-violet-600 font-medium hover:text-violet-700">
          Tạo nhiệm vụ đầu tiên →
        </button>
      </div>

      <!-- Task Content -->
      <div v-else class="flex-1 bg-slate-50/50">
        <!-- List View -->
        <div v-if="taskViewMode === 'list'" class="p-4 sm:p-6">
          <div class="mb-2 hidden grid-cols-[minmax(0,1fr)_110px_125px_150px_44px] gap-4 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:grid">
            <span>Nhiệm vụ</span><span>Tiến độ</span><span>Trạng thái</span><span>Thời hạn</span><span></span>
          </div>
          <div
            v-for="task in projectTasks"
            :key="task.id"
            @click="openTask(task)"
            class="group mb-2 grid cursor-pointer grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md lg:grid-cols-[minmax(0,1fr)_110px_125px_150px_44px]"
          >
            <div class="col-span-2 flex min-w-0 items-start gap-3 lg:col-span-1">
              <div :class="['mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-opacity-20', task.priority === 'high' ? 'bg-rose-500 ring-rose-200' : task.priority === 'medium' ? 'bg-amber-500 ring-amber-200' : 'bg-sky-500 ring-sky-200']"></div>
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-violet-700">{{ task.title }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">{{ taskTypeLabels[task.type] || 'Công việc' }}</span>
                <span v-for="tag in task.tags?.slice(0, 3)" :key="tag" class="px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-medium uppercase tracking-wide">
                  {{ tag }}
                </span>
                  <span v-if="task.estimatedHours" class="text-[10px] font-semibold text-slate-400">• {{ task.estimatedHours }} giờ</span>
                </div>
              </div>
            </div>
            <div class="hidden lg:block">
              <div class="mb-1 flex justify-between text-[10px] font-semibold text-slate-500"><span>{{ task.progress }}%</span></div>
              <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500" :style="{ width: `${task.progress}%` }"></div></div>
            </div>
            <span :class="['hidden w-fit rounded-full px-2.5 py-1 text-xs font-semibold lg:inline-flex', statusClasses[task.status]]">
              {{ taskStatusMap[task.status]?.label }}
            </span>
            <div :class="['hidden w-fit items-center gap-1 rounded-lg border px-2 py-1 text-xs font-semibold lg:flex', deadlineDateClass(task)]">
              <CalendarDays class="w-3.5 h-3.5" />
              {{ formatDate(task.dueDate) }}
            </div>
            <div
              :class="['h-8 w-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm ring-2 ring-white', `bg-${findMember(task.assigneeId).color}-500`]"
              :title="findMember(task.assigneeId).name"
            >
              {{ findMember(task.assigneeId).initials }}
            </div>
            <div class="col-span-3 mt-1 flex items-center justify-between border-t border-slate-100 pt-3 text-xs lg:hidden">
              <span :class="['rounded-full px-2 py-1 font-semibold', statusClasses[task.status]]">{{ taskStatusMap[task.status]?.label }}</span>
              <span :class="['flex items-center gap-1 rounded-lg border px-2 py-1 font-semibold', deadlineDateClass(task)]"><CalendarDays class="h-3.5 w-3.5" />{{ formatDate(task.dueDate) }}</span>
            </div>
          </div>
        </div>

        <!-- Board View (Kanban) -->
        <div v-if="taskViewMode === 'board'" class="p-6 h-full min-h-[500px]">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 h-full">
            <div v-for="col in columns" :key="col.id" class="bg-slate-100/50 rounded-2xl p-4 border border-slate-100 flex flex-col h-full">
              <div class="flex items-center justify-between mb-4 px-1 shrink-0">
                <div class="flex items-center gap-2">
                  <div :class="['w-2.5 h-2.5 rounded-full', col.color]"></div>
                  <h3 class="font-bold text-slate-700">{{ col.title }}</h3>
                  <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full">
                    {{ getTasksByStatus(col.id).length }}
                  </span>
                </div>
              </div>
              
              <div class="flex-1 overflow-y-auto custom-scrollbar -mx-2 px-2 min-h-[150px]">
                <draggable 
                  class="h-full space-y-3 pb-4"
                  :list="getTasksByStatus(col.id)"
                  :disabled="!canManageProject"
                  group="project-tasks"
                  item-key="id"
                  ghost-class="opacity-50"
                  @change="onTaskChange($event, col.id)"
                >
                  <template #item="{ element }">
                    <div @click="openTask(element)">
                      <TaskCard :task="element" />
                    </div>
                  </template>
                </draggable>
              </div>
              
              <button v-if="canCreateTaskInProject" @click="openTaskModal(project?.id)" class="w-full mt-2 py-2 flex items-center justify-center gap-2 text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 rounded-xl transition-colors font-medium text-sm shrink-0">
                <Plus class="w-4 h-4" /> Thêm thẻ
              </button>
            </div>
          </div>
        </div>

        <div v-if="taskViewMode === 'calendar'" class="p-5">
          <TaskCalendar :tasks="projectTasks" :show-project="false" @select="openTask" />
        </div>
      </div>
    </div>
  </div>
  <DownloadArchiveModal
    :is-open="isDownloadModalOpen"
    target-type="Project"
    :target-code="project?.id"
    @close="isDownloadModalOpen = false"
    @download="handleDownloadArchive"
  />
</div>
</template>

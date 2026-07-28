<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { FolderKanban, Calendar, Clock, ArrowLeft, Plus, MoreVertical, ListTodo, Paperclip, MoreHorizontal, Check, Settings, ShieldAlert, LogOut, CheckCircle2, Users, CalendarDays, Tag, LayoutGrid, List, Activity } from '@lucide/vue'
import draggable from 'vuedraggable'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'
import TaskCard from '../components/common/TaskCard.vue'
import { ref } from 'vue'
import DownloadArchiveModal from '../components/modals/DownloadArchiveModal.vue'

const route = useRoute()
const router = useRouter()
const { projects, tasks, members, activities, projectStatusMap, priorityMap, taskStatusMap, findMember, formatDate, getTaskDeadlineState, taskModalOpen, openTaskModal, projectSettingsModalOpen, fileUploadModalOpen, manageMembersModalOpen, editingProjectId, removeFileFromProject, removeMemberFromProject, moveTask, activeTaskId, downloadArchive, downloadSingleFile } = useProjectWorkspace()

const deadlineDateClass = (task) => {
  const state = getTaskDeadlineState(task.dueDate, task.status)
  if (state === 'overdue') return 'text-rose-600 bg-rose-50 border-rose-200'
  if (state === 'due') return 'text-amber-700 bg-amber-50 border-amber-200'
  return 'text-slate-400 border-transparent'
}

const projectId = computed(() => route.params.id)
const isDownloadModalOpen = ref(false)
const project = computed(() => projects.value.find(p => p.id === projectId.value))
const projectTasks = computed(() => tasks.value.filter(t => t.projectId === projectId.value))

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
const projectActivities = computed(() => activities.value.filter(a => a.projectId === projectId.value).slice(0, 5))
const showProjectActivities = ref(false)

// Calculate time ago string
const timeAgo = (dateStr) => {
  const diffMs = new Date() - new Date(dateStr)
  const diffMins = Math.round(diffMs / 60000)
  if (diffMins < 60) return `${diffMins} phút trước`
  const diffHours = Math.round(diffMins / 60)
  if (diffHours < 24) return `${diffHours} giờ trước`
  return `${Math.round(diffHours / 24)} ngày trước`
}

const stats = computed(() => [
  { label: 'Cần làm', value: projectTasks.value.filter(t => t.status === 'todo').length, icon: ListTodo, color: 'text-slate-600', bg: 'bg-slate-100' },
  { label: 'Đang làm', value: projectTasks.value.filter(t => t.status === 'in_progress').length, icon: Clock, color: 'text-amber-600', bg: 'bg-amber-100' },
  { label: 'Hoàn thành', value: projectTasks.value.filter(t => t.status === 'done').length, icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-100' },
])

const gradientMap = {
  purple: 'from-violet-500 to-indigo-600',
  violet: 'from-violet-500 to-indigo-600',
  indigo: 'from-indigo-500 to-violet-600',
  emerald: 'from-emerald-500 to-teal-600',
  amber: 'from-amber-500 to-orange-600',
  rose: 'from-rose-500 to-pink-600',
  sky: 'from-sky-500 to-blue-600',
  green: 'from-green-500 to-emerald-600',
  orange: 'from-orange-500 to-amber-600',
  pink: 'from-pink-500 to-rose-600',
  blue: 'from-blue-500 to-indigo-600',
}

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
    <div :class="['rounded-2xl bg-gradient-to-br text-white p-6 shadow-lg relative overflow-hidden', gradientMap[project.color] || gradientMap.indigo]">
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
          </div>
          <h1 class="text-2xl sm:text-3xl font-bold mb-2">{{ project.name }}</h1>
          <p class="text-white/80 text-sm max-w-lg">{{ project.description }}</p>
        </div>
        <button 
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
      </div>

      <!-- Progress bar -->
      <div class="relative mt-4">
        <div class="h-2 w-full bg-white/20 rounded-full overflow-hidden">
          <div class="h-full bg-white/80 rounded-full transition-all duration-500" :style="{ width: `${project.progress}%` }"></div>
        </div>
      </div>
    </div>

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

    <!-- Grid Members & Files -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Members -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-slate-900">Thành viên</h2>
          <button @click="openManageMembers" class="text-sm font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
            <Plus class="w-4 h-4" /> Thêm
          </button>
        </div>
        <div class="flex flex-wrap gap-3 flex-1 content-start">
          <div
            v-for="member in projectMembers"
            :key="member.id"
            class="flex items-center gap-3 pl-3 pr-2 py-2 rounded-xl bg-slate-50 border border-slate-100 group transition-all hover:border-violet-200"
          >
            <UserAvatar :member-id="member.id" size="sm" :show-popover="false" />
            <div>
              <p class="text-sm font-semibold text-slate-900 leading-tight">{{ member.name }}</p>
              <p class="text-xs text-slate-500">{{ member.role }}</p>
            </div>
            <button @click="confirmRemoveMember(member.id)" title="Loại khỏi dự án" class="ml-1 text-slate-300 hover:text-rose-500 p-1.5 rounded-lg hover:bg-rose-50 transition-colors opacity-0 group-hover:opacity-100">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
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
            <button @click="isDownloadModalOpen = true" :disabled="!projectFiles.length" class="text-sm font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50">
              Tải xuống tất cả
            </button>
            <button @click="openFileUpload" class="text-sm font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
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
              <button @click.prevent="downloadSingleFile(file.url, file.name)" title="Tải xuống" class="text-slate-300 hover:text-violet-600 p-2 rounded-lg hover:bg-violet-50 transition-colors flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              </button>
              <button @click="confirmRemoveFile(idx)" title="Xóa tệp" class="text-slate-300 hover:text-rose-500 p-2 rounded-lg hover:bg-rose-50 transition-colors flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-8 text-center flex-1 bg-slate-50 rounded-xl border border-dashed border-slate-200 cursor-pointer hover:bg-slate-100 transition-colors" @click="openFileUpload">
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
            @click="showProjectActivities = !showProjectActivities"
            class="text-xs font-semibold text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-2.5 py-1 rounded-lg transition-colors cursor-pointer shadow-2xs"
          >
            {{ showProjectActivities ? 'Ẩn' : 'Xem' }}
          </button>
        </div>
        
        <div v-if="showProjectActivities && projectActivities.length" class="relative">
          <div class="absolute top-2 bottom-2 left-[19px] w-px bg-slate-200"></div>
          <div class="space-y-6 relative">
            <div v-for="activity in projectActivities" :key="activity.id" class="flex gap-4">
              <!-- Avatar Node -->
              <div
                :class="[
                  'relative z-10 w-10 h-10 rounded-full flex items-center justify-center shrink-0 border-4 border-white shadow-sm ring-1 ring-slate-100 text-xs font-bold text-white',
                  `bg-${activity.actor?.color || 'slate'}-500`
                ]"
              >
                {{ activity.actor?.initials || 'HT' }}
              </div>
              <!-- Content -->
              <div class="pt-2">
                <p class="text-sm text-slate-900">
                  <span class="font-semibold">{{ activity.actor?.name || 'Người dùng hệ thống' }}</span>
                  <span class="text-slate-500"> {{ activity.action }} </span>
                  <span class="font-medium text-slate-800">{{ activity.target }}</span>
                </p>
                <div v-if="activity.detail" class="mt-1.5 p-2.5 bg-slate-50 border border-slate-100 rounded-lg text-sm text-slate-600 inline-block">
                  {{ activity.detail }}
                </div>
                <p class="text-xs text-slate-400 mt-1.5 font-medium">{{ timeAgo(activity.createdAt) }}</p>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-slate-400 text-sm font-medium">
          Chưa có hoạt động nào (hoặc đang ẩn).
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
          </div>
          <button
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
        <button @click="openTaskModal(project?.id)" class="mt-4 text-sm text-violet-600 font-medium hover:text-violet-700">
          Tạo nhiệm vụ đầu tiên →
        </button>
      </div>

      <!-- Task Content -->
      <div v-else class="flex-1 bg-slate-50/50">
        <!-- List View -->
        <div v-if="taskViewMode === 'list'" class="divide-y divide-slate-50">
          <div
            v-for="task in projectTasks"
            :key="task.id"
            @click="openTask(task)"
            class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 transition-colors group cursor-pointer"
          >
            <!-- Priority dot -->
            <div :class="['w-2.5 h-2.5 rounded-full shrink-0', task.priority === 'high' ? 'bg-rose-500' : task.priority === 'medium' ? 'bg-amber-500' : 'bg-sky-500']"></div>

            <!-- Title + tags -->
            <div class="flex-1 min-w-0">
              <p class="font-medium text-slate-900 truncate group-hover:text-violet-600 transition-colors">{{ task.title }}</p>
              <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                <span v-for="tag in task.tags?.slice(0, 3)" :key="tag" class="px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-medium uppercase tracking-wide">
                  {{ tag }}
                </span>
              </div>
            </div>

            <!-- Priority badge -->
            <span :class="['px-2 py-0.5 text-xs font-semibold rounded-full hidden sm:inline-flex', priorityClasses[task.priority] || priorityClasses.medium]">
              {{ priorityMap[task.priority]?.label }}
            </span>

            <!-- Status -->
            <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full hidden md:inline-flex', statusClasses[task.status]]">
              {{ taskStatusMap[task.status]?.label }}
            </span>

            <!-- Due date -->
            <div :class="['hidden sm:flex items-center gap-1 text-xs font-semibold shrink-0 rounded-lg border px-2 py-1', deadlineDateClass(task)]">
              <CalendarDays class="w-3.5 h-3.5" />
              {{ formatDate(task.dueDate) }}
            </div>

            <!-- Assignee -->
            <div
              :class="['w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm ring-2 ring-white shrink-0', `bg-${findMember(task.assigneeId).color}-500`]"
              :title="findMember(task.assigneeId).name"
            >
              {{ findMember(task.assigneeId).initials }}
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
              
              <button @click="openTaskModal(project?.id)" class="w-full mt-2 py-2 flex items-center justify-center gap-2 text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 rounded-xl transition-colors font-medium text-sm shrink-0">
                <Plus class="w-4 h-4" /> Thêm thẻ
              </button>
            </div>
          </div>
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

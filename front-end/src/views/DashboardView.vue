<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FolderKanban, Clock, CheckCircle2, Target, ArrowUpRight, ArrowDownRight, Plus, MoreHorizontal, Activity, CalendarDays, UploadCloud } from '@lucide/vue'
import draggable from 'vuedraggable'
import ProjectCard from '../components/common/ProjectCard.vue'
import TaskCard from '../components/common/TaskCard.vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const router = useRouter()
const {
  projects,
  tasks,
  planningProjects,
  activeProjects,
  operatingProjects,
  completedProjects,
  projectCompletionRate,
  completedTasks,
  completionRate,
  projectModalOpen,
  taskModalOpen,
  activities,
  findMember,
  formatDate,
  moveTask,
  activeTaskId,
  notify,
  importProjectModalOpen
} = useProjectWorkspace()

const inProgressTasks = computed(() => tasks.value.filter((task) => task.status === 'in_progress'))

const todayTasks = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return tasks.value.filter(t => t.dueDate === today && t.status !== 'done')
})

const stats = computed(() => [
  { icon: FolderKanban, color: 'text-violet-600', bg: 'bg-violet-100', value: operatingProjects.value.length, label: 'Dự án hoạt động', trend: '12%', up: true },
  { icon: Clock, color: 'text-amber-600', bg: 'bg-amber-100', value: activeProjects.value.length, label: 'Đang thực hiện', trend: '5%', up: false },
  { icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-100', value: completedProjects.value.length, label: 'Đã hoàn thành', trend: '23%', up: true },
  { icon: Target, color: 'text-sky-600', bg: 'bg-sky-100', value: `${projectCompletionRate.value}%`, label: 'Tỷ lệ hoàn thành', trend: '8%', up: true },
])

const columns = ref([
  { id: 'todo', title: 'Cần làm', color: 'bg-slate-400' },
  { id: 'in_progress', title: 'Đang làm', color: 'bg-amber-500' },
  { id: 'done', title: 'Hoàn thành', color: 'bg-emerald-500' }
])

const getTasksByStatus = (status) => {
  return tasks.value.filter(t => t.status === status)
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

const timeAgo = (dateStr) => {
  const diff = (new Date() - new Date(dateStr)) / 1000
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff/60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff/3600)} giờ trước`
  return formatDate(dateStr)
}
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Xin chào, User 👋</h1>
        <p class="text-slate-500 text-sm">Bạn có {{ todayTasks.length }} nhiệm vụ cần xử lý hôm nay</p>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <button 
          @click="importProjectModalOpen = true"
          class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-medium hover:bg-slate-50 transition-all flex items-center gap-2"
        >
          <UploadCloud class="w-5 h-5" /> Tải dự án lên
        </button>
        <button 
          @click="projectModalOpen = true"
          class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-medium hover:shadow-premium transition-all shadow-md shadow-violet-500/25 flex items-center gap-2"
        >
          <Plus class="w-5 h-5" /> Tạo dự án mới
        </button>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
      <div v-for="stat in stats" :key="stat.label" class="bg-white border border-slate-100 rounded-2xl p-5 hover:shadow-md hover:-translate-y-1 transition-all duration-300">
        <div class="flex justify-between items-start mb-4">
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', stat.bg, stat.color]">
            <component :is="stat.icon" class="w-5 h-5" />
          </div>
          <div :class="['flex items-center text-xs font-bold px-2 py-1 rounded-full', stat.up ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600']">
            <component :is="stat.up ? ArrowUpRight : ArrowDownRight" class="w-3 h-3 mr-1" />
            {{ stat.trend }}
          </div>
        </div>
        <h3 class="text-3xl font-display font-bold text-slate-900 mb-1">{{ stat.value }}</h3>
        <p class="text-sm text-slate-500 font-medium">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Left Column: Kanban -->
      <div class="lg:col-span-2 space-y-8">
        <div>
          <div class="flex items-end justify-between mb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Bảng nhiệm vụ</h2>
              <p class="text-sm text-slate-500">Kéo thả để thay đổi trạng thái</p>
            </div>
            <button @click="router.push('/tasks')" class="text-violet-600 font-medium text-sm hover:text-violet-700 flex items-center">
              Xem tất cả <ArrowUpRight class="w-4 h-4 ml-1" />
            </button>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-for="col in columns" :key="col.id" class="bg-slate-50 rounded-2xl p-4 border border-slate-100 flex flex-col h-[500px]">
              <div class="flex items-center justify-between mb-4 px-1">
                <div class="flex items-center gap-2">
                  <div :class="['w-2.5 h-2.5 rounded-full', col.color]"></div>
                  <h3 class="font-bold text-slate-700">{{ col.title }}</h3>
                  <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full">
                    {{ getTasksByStatus(col.id).length }}
                  </span>
                </div>
              </div>
              
              <div class="flex-1 overflow-y-auto custom-scrollbar">
                <draggable 
                  class="h-full space-y-3 pb-4"
                  :list="getTasksByStatus(col.id)"
                  group="tasks"
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
              
              <button @click="taskModalOpen = true" class="w-full mt-2 py-2 flex items-center justify-center gap-2 text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 rounded-xl transition-colors font-medium text-sm">
                <Plus class="w-4 h-4" /> Thêm thẻ
              </button>
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-end justify-between mb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">Dự án gần đây</h2>
              <p class="text-sm text-slate-500">Các dự án bạn đang tham gia</p>
            </div>
            <button @click="router.push('/projects')" class="text-violet-600 font-medium text-sm hover:text-violet-700 flex items-center">
              Tất cả dự án <ArrowUpRight class="w-4 h-4 ml-1" />
            </button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <ProjectCard v-for="project in projects.slice(0, 2)" :key="project.id" :project="project" />
          </div>
        </div>
      </div>

      <!-- Right Column: Widgets -->
      <div class="space-y-8">
        
        <!-- Today's Tasks Widget -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h2 class="font-bold text-slate-900 flex items-center gap-2">
              <CalendarDays class="w-5 h-5 text-rose-500" /> Đến hạn hôm nay
            </h2>
            <span class="bg-rose-100 text-rose-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ todayTasks.length }}</span>
          </div>
          <div class="p-5">
            <div v-if="todayTasks.length > 0" class="space-y-3">
              <div 
                v-for="task in todayTasks" 
                :key="task.id"
                class="group p-3 bg-slate-50 rounded-xl border border-slate-100 hover:border-violet-200 transition-colors cursor-pointer"
                @click="openTask(task)"
              >
                <h4 class="font-medium text-slate-900 text-sm mb-1 group-hover:text-violet-600 transition-colors">{{ task.title }}</h4>
                <div class="flex items-center gap-2 text-xs font-medium">
                  <span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded">Gấp</span>
                  <span class="text-slate-500">{{ findProject(task.projectId)?.name }}</span>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6">
              <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-3">
                <CheckCircle2 class="w-6 h-6 text-emerald-500" />
              </div>
              <p class="text-sm font-medium text-slate-900 mb-1">Tuyệt vời!</p>
              <p class="text-xs text-slate-500">Bạn đã hoàn thành mọi việc hôm nay.</p>
            </div>
          </div>
        </div>

        <!-- Activity Feed Widget -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col h-[500px]">
          <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="font-bold text-slate-900 flex items-center gap-2">
              <Activity class="w-5 h-5 text-violet-500" /> Hoạt động gần đây
            </h2>
          </div>
          
          <div class="p-5 flex-1 overflow-y-auto custom-scrollbar">
            <div v-if="false" class="relative border-l border-slate-200 ml-4 space-y-6 pb-4">
              <div v-for="activity in activities.slice(0, 8)" :key="activity.id" class="relative pl-6">
                <!-- Timeline dot -->
                <div class="absolute -left-[17px] top-1">
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm ring-4 ring-white', `bg-${findMember(activity.memberId).color}-500`]">
                    {{ findMember(activity.memberId).initials }}
                  </div>
                </div>
                
                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                  <p class="text-sm text-slate-600 leading-snug">
                    <span class="font-bold text-slate-900">{{ findMember(activity.memberId).name }}</span> 
                    {{ activity.action }} 
                    <span class="font-medium text-slate-900">{{ activity.target }}</span>
                  </p>
                  <p v-if="activity.detail" class="text-xs text-slate-500 mt-1 italic">"{{ activity.detail }}"</p>
                  <p class="text-[10px] font-medium text-slate-400 mt-2 uppercase tracking-wide">{{ timeAgo(activity.createdAt) }}</p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <p class="text-slate-500 text-sm">Chưa có hoạt động nào (hoặc đang ẩn).</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 10px;
}
</style>

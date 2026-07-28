<script setup>
import { computed, ref } from 'vue'
import { Plus, Search, List, KanbanSquare, MoreHorizontal, Check, CheckSquare } from '@lucide/vue'
import TaskCard from '../components/common/TaskCard.vue'
import UserAvatar from '../components/common/UserAvatar.vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const statusFilter = ref('all')
const priorityFilter = ref('all')
const viewMode = ref('kanban')
const { tasks, globalSearch, taskModalOpen, priorityMap, taskStatusMap, findMember, findProject, formatDate, getTaskDeadlineState, toggleTaskComplete, activeTaskId, updateTask } = useProjectWorkspace()

const deadlineDateClass = (task) => {
  const state = getTaskDeadlineState(task.dueDate, task.status)
  if (state === 'overdue') return 'text-rose-600 bg-rose-50 border-rose-200'
  if (state === 'due') return 'text-amber-700 bg-amber-50 border-amber-200'
  return 'text-slate-600 border-transparent'
}

const filteredTasks = computed(() => tasks.value.filter((task) => {
  const project = findProject(task.projectId)
  const queryMatch = !globalSearch.value || `${task.title} ${project?.name || ''}`.toLowerCase().includes(globalSearch.value.toLowerCase())
  return queryMatch && (statusFilter.value === 'all' || task.status === statusFilter.value) && (priorityFilter.value === 'all' || task.priority === priorityFilter.value)
}))

const priorityClasses = {
  high: 'bg-rose-50 text-rose-600',
  medium: 'bg-amber-50 text-amber-600',
  low: 'bg-sky-50 text-sky-600'
}

const openTask = (task) => {
  activeTaskId.value = task.id
}

const moveTaskStatus = (task, newStatus) => {
  updateTask(task.id, { ...task, status: newStatus })
}

const columns = [
  { id: 'todo', title: 'Cần làm', bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' },
  { id: 'in_progress', title: 'Đang làm', bg: 'bg-blue-100', text: 'text-blue-700', border: 'border-blue-200' },
  { id: 'done', title: 'Hoàn thành', bg: 'bg-emerald-100', text: 'text-emerald-700', border: 'border-emerald-200' }
]

const getTasksByStatus = (statusId) => {
  return filteredTasks.value.filter(t => t.status === statusId)
}
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 mb-1">Nhiệm vụ</h1>
        <p class="text-slate-500 text-sm">{{ tasks.length }} nhiệm vụ</p>
      </div>
      <button 
        @click="taskModalOpen = true"
        class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-medium hover:shadow-premium transition-all shadow-md shadow-violet-500/25 shrink-0 flex items-center gap-2"
      >
        <Plus class="w-5 h-5" /> Tạo nhiệm vụ
      </button>
    </div>

    <!-- Toolbar -->
    <div class="bg-white p-2 rounded-2xl border border-slate-100 flex flex-col sm:flex-row gap-2 shadow-sm">
      <div class="relative flex-1">
        <Search class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input 
          v-model="globalSearch" 
          placeholder="Tìm nhiệm vụ..." 
          class="w-full bg-slate-50 text-slate-900 placeholder:text-slate-400 rounded-xl pl-10 pr-4 py-2 border border-transparent focus:border-violet-200 focus:bg-white focus:ring-4 focus:ring-violet-500/10 transition-all outline-none"
        />
      </div>
      <div class="flex gap-2">
        <select v-model="statusFilter" class="bg-slate-50 text-slate-700 rounded-xl px-4 py-2 border border-transparent focus:border-violet-200 focus:bg-white outline-none cursor-pointer appearance-none min-w-[140px]">
          <option value="all">Tất cả trạng thái</option>
          <option value="todo">Cần làm</option>
          <option value="in_progress">Đang làm</option>
          <option value="done">Hoàn thành</option>
        </select>
        <select v-model="priorityFilter" class="bg-slate-50 text-slate-700 rounded-xl px-4 py-2 border border-transparent focus:border-violet-200 focus:bg-white outline-none cursor-pointer appearance-none min-w-[140px]">
          <option value="all">Tất cả ưu tiên</option>
          <option value="high">Ưu tiên cao</option>
          <option value="medium">Trung bình</option>
          <option value="low">Ưu tiên thấp</option>
        </select>
        
        <div class="flex items-center bg-slate-50 rounded-xl p-1 border border-slate-100">
          <button 
            @click="viewMode = 'table'" 
            :class="['p-1.5 rounded-lg transition-colors', viewMode === 'table' ? 'bg-white shadow-sm text-violet-600' : 'text-slate-400 hover:text-slate-600']"
          >
            <List class="w-5 h-5" />
          </button>
          <button 
            @click="viewMode = 'kanban'" 
            title="Dạng bảng Kanban"
            :class="['p-1.5 rounded-lg transition-colors', viewMode === 'kanban' ? 'bg-white shadow-sm text-violet-600' : 'text-slate-400 hover:text-slate-600']"
          >
            <KanbanSquare class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Table View -->
    <div v-if="viewMode === 'table'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
              <th class="p-4 font-semibold">Nhiệm vụ</th>
              <th class="p-4 font-semibold">Dự án</th>
              <th class="p-4 font-semibold">Ưu tiên</th>
              <th class="p-4 font-semibold">Trạng thái</th>
              <th class="p-4 font-semibold">Hạn chót</th>
              <th class="p-4 font-semibold">Phụ trách</th>
              <th class="p-4 font-semibold w-12"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="task in filteredTasks" :key="task.id" class="hover:bg-slate-50/50 transition-colors group cursor-pointer" @click="openTask(task)">
              <td class="p-4">
                <div class="flex items-start gap-3">
                  <button 
                    @click.stop="toggleTaskComplete(task)"
                    :class="['mt-0.5 w-5 h-5 rounded flex items-center justify-center border transition-colors shrink-0', 
                      task.status === 'done' ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-300 hover:border-violet-400 text-transparent']"
                  >
                    <Check class="w-3.5 h-3.5" />
                  </button>
                  <div>
                    <strong :class="['block font-medium text-sm mb-1 group-hover:text-violet-600 transition-colors', task.status === 'done' ? 'text-slate-400 line-through' : 'text-slate-900']">
                      {{ task.title }}
                    </strong>
                    <div class="flex gap-1" v-if="task.tags?.length">
                      <span v-for="tag in task.tags" :key="tag" class="text-[10px] uppercase tracking-wide font-medium bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">
                        {{ tag }}
                      </span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="p-4 text-sm text-slate-600">{{ findProject(task.projectId)?.name || '—' }}</td>
              <td class="p-4">
                <span :class="['px-2.5 py-1 text-xs font-semibold rounded-full', priorityClasses[task.priority] || priorityClasses.medium]">
                  {{ priorityMap[task.priority]?.label || 'Trung bình' }}
                </span>
              </td>
              <td class="p-4">
                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                  {{ taskStatusMap[task.status]?.label }}
                </span>
              </td>
              <td class="p-4 text-sm text-slate-600 whitespace-nowrap">
                <span :class="['inline-flex rounded-lg border px-2 py-1 font-semibold', deadlineDateClass(task)]">
                  {{ formatDate(task.dueDate) }}
                </span>
              </td>
              <td class="p-4">
                <div class="flex items-center gap-2">
                  <UserAvatar :member-id="task.assigneeId" size="sm" />
                  <span class="text-sm font-medium text-slate-700 hidden lg:block">{{ findMember(task.assigneeId).name }}</span>
                </div>
              </td>
              <td class="p-4">
                <button class="text-slate-400 hover:text-slate-700 opacity-0 group-hover:opacity-100 transition-opacity">
                  <MoreHorizontal class="w-5 h-5" />
                </button>
              </td>
            </tr>
            <tr v-if="filteredTasks.length === 0">
              <td colspan="7" class="p-12 text-center">
                <div class="flex flex-col items-center">
                  <CheckSquare class="w-12 h-12 text-slate-300 mb-3" />
                  <p class="text-slate-500 font-medium">Không tìm thấy nhiệm vụ nào.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Kanban View -->
    <div v-else>
      <div v-if="filteredTasks.length" class="flex flex-col md:flex-row gap-6 overflow-x-auto pb-4">
        <!-- Kanban Columns -->
        <div 
          v-for="col in columns" 
          :key="col.id" 
          class="flex-1 min-w-[300px] bg-slate-50/50 rounded-2xl border border-slate-200/60 p-4 flex flex-col"
        >
          <!-- Column Header -->
          <div class="flex items-center justify-between mb-4">
            <h3 :class="['font-bold flex items-center gap-2', col.text]">
              <span :class="['w-2.5 h-2.5 rounded-full', col.bg, col.border, 'border']"></span>
              {{ col.title }}
            </h3>
            <span class="bg-white border border-slate-200 text-slate-500 text-xs font-bold px-2 py-0.5 rounded-full shadow-sm">{{ getTasksByStatus(col.id).length }}</span>
          </div>

          <!-- Column Tasks -->
          <div class="flex-1 space-y-3">
            <div 
              v-for="task in getTasksByStatus(col.id)" 
              :key="task.id" 
              class="relative group"
            >
              <div @click="openTask(task)" class="cursor-pointer hover:ring-2 hover:ring-violet-400 hover:ring-offset-2 hover:ring-offset-slate-50 rounded-xl transition-all">
                <TaskCard :task="task" :show-status="false" />
              </div>
              
              <!-- Quick Move Actions -->
              <div class="absolute -right-3 -bottom-3 opacity-0 group-hover:opacity-100 transition-opacity bg-white border border-slate-200 shadow-lg rounded-xl p-1 flex gap-1 z-10">
                <button 
                  v-if="col.id !== 'todo'" 
                  @click.stop="moveTaskStatus(task, 'todo')"
                  class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-colors"
                  title="Chuyển sang Cần làm"
                >
                  <div class="w-3 h-3 rounded-sm bg-slate-300"></div>
                </button>
                <button 
                  v-if="col.id !== 'in_progress'" 
                  @click.stop="moveTaskStatus(task, 'in_progress')"
                  class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                  title="Chuyển sang Đang làm"
                >
                  <div class="w-3 h-3 rounded-sm bg-blue-400"></div>
                </button>
                <button 
                  v-if="col.id !== 'done'" 
                  @click.stop="moveTaskStatus(task, 'done')"
                  class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                  title="Chuyển sang Hoàn thành"
                >
                  <div class="w-3 h-3 rounded-sm bg-emerald-400"></div>
                </button>
              </div>
            </div>
            
            <div v-if="getTasksByStatus(col.id).length === 0" class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center text-slate-400 text-sm font-medium">
              Kéo thả nhiệm vụ vào đây
            </div>
          </div>
        </div>
      </div>
      
      <!-- Empty State -->
      <div v-else class="flex flex-col items-center justify-center py-24 px-4 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-6">
          <CheckSquare class="w-10 h-10 text-slate-400" />
        </div>
        <h3 class="text-xl font-bold text-slate-900 mb-2">Không tìm thấy nhiệm vụ</h3>
        <p class="text-slate-500 max-w-sm">Không có nhiệm vụ nào phù hợp với bộ lọc hiện tại.</p>
      </div>
    </div>
  </div>
</template>

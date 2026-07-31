<script setup>
import { computed, ref } from 'vue'
import { Plus, FolderSearch, UploadCloud } from '@lucide/vue'
import ProjectCard from '../components/common/ProjectCard.vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const activeFilter = ref('all')
const { projects, globalSearch, projectModalOpen, notify, importProjectModalOpen, currentUser } = useProjectWorkspace()
const canCreateProjects = computed(() => ['admin', 'project_manager', 'manager'].includes(currentUser.value?.role))

const filters = [
  { id: 'all', label: 'Tất cả' }, 
  { id: 'active', label: 'Đang triển khai' }, 
  { id: 'planning', label: 'Lập kế hoạch' },
  { id: 'on_hold', label: 'Tạm dừng' }, 
  { id: 'completed', label: 'Hoàn thành' },
]

const projectCounts = computed(() => {
  const counts = { all: projects.value.length }
  for (const filter of filters) {
    if (filter.id !== 'all') {
      counts[filter.id] = projects.value.filter(p => p.status === filter.id).length
    }
  }
  return counts
})

const filteredProjects = computed(() => projects.value.filter((project) => {
  const statusMatch = activeFilter.value === 'all' || project.status === activeFilter.value
  const queryMatch = !globalSearch.value || `${project.name} ${project.description}`.toLowerCase().includes(globalSearch.value.toLowerCase())
  return statusMatch && queryMatch
}))
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 mb-1">Dự án</h1>
        <p class="text-slate-500 text-sm">{{ projects.length }} dự án · {{ filteredProjects.length }} đang hiển thị</p>
      </div>
      <div v-if="canCreateProjects" class="flex items-center gap-3 shrink-0">
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

    <!-- Filter Tabs -->
    <div class="flex overflow-x-auto gap-2 pb-2 scrollbar-hide">
      <button 
        v-for="filter in filters" 
        :key="filter.id" 
        @click="activeFilter = filter.id"
        :class="[
          'px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all flex items-center gap-2 border',
          activeFilter === filter.id 
            ? 'bg-violet-50 text-violet-700 border-violet-200' 
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-slate-300'
        ]"
      >
        {{ filter.label }} 
        <span 
          :class="[
            'px-2 py-0.5 rounded-full text-xs font-bold',
            activeFilter === filter.id ? 'bg-violet-200 text-violet-800' : 'bg-slate-100 text-slate-500'
          ]"
        >
          {{ projectCounts[filter.id] }}
        </span>
      </button>
    </div>

    <!-- Grid -->
    <div v-if="filteredProjects.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <ProjectCard v-for="project in filteredProjects" :key="project.id" :project="project" />
      
      <button 
        v-if="canCreateProjects"
        @click="projectModalOpen = true"
        class="border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center p-8 text-slate-500 hover:text-violet-600 hover:border-violet-300 hover:bg-violet-50 transition-all group min-h-[260px]"
      >
        <div class="w-12 h-12 rounded-full bg-slate-100 group-hover:bg-violet-100 flex items-center justify-center mb-4 transition-colors">
          <Plus class="w-6 h-6" />
        </div>
        <strong class="text-lg mb-1">Tạo dự án mới</strong>
        <span class="text-sm opacity-80">Bắt đầu một ý tưởng tuyệt vời</span>
      </button>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center py-24 px-4 text-center">
      <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-6">
        <FolderSearch class="w-10 h-10 text-slate-400" />
      </div>
      <h3 class="text-xl font-bold text-slate-900 mb-2">Không tìm thấy dự án</h3>
      <p class="text-slate-500 max-w-sm">Không có dự án nào phù hợp với bộ lọc hiện tại. Hãy thử từ khóa hoặc trạng thái khác.</p>
    </div>
  </div>
</template>

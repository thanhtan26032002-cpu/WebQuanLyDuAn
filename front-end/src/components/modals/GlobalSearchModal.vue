<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, FolderKanban, CheckSquare, Users, ArrowRight } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const router = useRouter()
const { projects, tasks, members, globalSearchModalOpen } = useProjectWorkspace()

const query = ref('')
const searchInputRef = ref(null)

watch(globalSearchModalOpen, (isOpen) => {
  if (isOpen) {
    query.value = ''
    setTimeout(() => {
      if (searchInputRef.value) searchInputRef.value.focus()
    }, 50)
  }
})

const results = computed(() => {
  if (!query.value.trim()) return []
  const q = query.value.toLowerCase()
  
  const foundProjects = projects.value
    .filter(p => p.name.toLowerCase().includes(q))
    .map(p => ({ type: 'project', icon: FolderKanban, color: 'text-indigo-500', bg: 'bg-indigo-100', id: p.id, title: p.name, subtitle: p.status }))
    
  const foundTasks = tasks.value
    .filter(t => t.title.toLowerCase().includes(q))
    .map(t => ({ type: 'task', icon: CheckSquare, color: 'text-emerald-500', bg: 'bg-emerald-100', id: t.id, title: t.title, subtitle: t.status }))
    
  const foundMembers = members.value
    .filter(m => m.name.toLowerCase().includes(q) || m.role.toLowerCase().includes(q))
    .map(m => ({ type: 'member', icon: Users, color: 'text-violet-500', bg: 'bg-violet-100', id: m.id, title: m.name, subtitle: m.role }))

  return [...foundProjects, ...foundTasks, ...foundMembers]
})

const navigateTo = (item) => {
  globalSearchModalOpen.value = false
  if (item.type === 'project') {
    router.push(`/projects/${item.id}`)
  } else if (item.type === 'task') {
    // Mock open task detail
  } else if (item.type === 'member') {
    router.push('/team')
  }
}

// Keyboard shortcuts
const onKeydown = (e) => {
  if (e.key === 'Escape') {
    globalSearchModalOpen.value = false
  }
  // Ctrl + K to open search
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    globalSearchModalOpen.value = true
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="globalSearchModalOpen" class="fixed inset-0 z-[110] p-4 sm:p-6 pt-[10vh] animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity" @click="globalSearchModalOpen = false"></div>
      
      <!-- Command Palette -->
      <div class="relative bg-white/90 backdrop-blur-xl rounded-2xl shadow-2xl w-full max-w-2xl mx-auto flex flex-col max-h-[70vh] overflow-hidden border border-white/40 animate-in zoom-in-95 duration-200">
        
        <!-- Search Input -->
        <div class="flex items-center px-4 py-4 border-b border-slate-200/50 bg-white/50">
          <Search class="w-6 h-6 text-violet-500 shrink-0" />
          <input 
            ref="searchInputRef"
            v-model="query"
            type="text" 
            placeholder="Tìm kiếm dự án, nhiệm vụ, đồng nghiệp..." 
            class="w-full bg-transparent text-slate-900 placeholder:text-slate-400 text-lg sm:text-xl pl-4 pr-4 border-none focus:outline-none focus:ring-0"
          />
          <kbd class="hidden sm:inline-flex items-center gap-1 px-2 py-1 bg-slate-100 border border-slate-200 rounded text-[10px] font-medium text-slate-500 tracking-wider">ESC</kbd>
        </div>

        <!-- Results -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-2">
          <!-- Initial State -->
          <div v-if="!query" class="px-6 py-12 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-slate-100">
              <Search class="w-8 h-8 text-slate-300" />
            </div>
            <h3 class="text-slate-600 font-medium">Bắt đầu nhập để tìm kiếm</h3>
            <p class="text-sm text-slate-400 mt-1">Truy cập nhanh mọi dữ liệu trong ProjeVibe</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="results.length === 0" class="px-6 py-12 text-center">
            <h3 class="text-slate-600 font-medium">Không tìm thấy kết quả</h3>
            <p class="text-sm text-slate-400 mt-1">Vui lòng thử lại với từ khóa khác</p>
          </div>

          <!-- Result List -->
          <div v-else class="space-y-1">
            <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Kết quả</div>
            <button 
              v-for="item in results" 
              :key="item.id + item.type"
              @click="navigateTo(item)"
              class="w-full flex items-center justify-between p-3 rounded-xl hover:bg-violet-50 group transition-colors text-left"
            >
              <div class="flex items-center gap-4">
                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0', item.bg, item.color]">
                  <component :is="item.icon" class="w-5 h-5" />
                </div>
                <div>
                  <p class="font-semibold text-slate-900 group-hover:text-violet-700 transition-colors">{{ item.title }}</p>
                  <p class="text-xs text-slate-500">{{ item.type === 'project' ? 'Dự án' : item.type === 'task' ? 'Nhiệm vụ' : 'Thành viên' }} • <span class="capitalize">{{ item.subtitle }}</span></p>
                </div>
              </div>
              <ArrowRight class="w-4 h-4 text-violet-400 opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all" />
            </button>
          </div>
        </div>
        
        <!-- Footer Info -->
        <div class="hidden sm:flex items-center gap-4 px-4 py-3 bg-slate-50/80 border-t border-slate-200/50 text-xs text-slate-500">
          <span class="flex items-center gap-1.5"><kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-medium shadow-sm">↑</kbd> <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-medium shadow-sm">↓</kbd> để di chuyển</span>
          <span class="flex items-center gap-1.5"><kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-medium shadow-sm">Enter</kbd> để chọn</span>
        </div>
      </div>
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
</style>

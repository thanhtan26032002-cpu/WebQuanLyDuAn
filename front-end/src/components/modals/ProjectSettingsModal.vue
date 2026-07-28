<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, computed } from 'vue'
import { X, Settings, Check, Trash2, AlertTriangle } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const router = useRouter()
const { projects, projectSettingsModalOpen, editingProjectId, updateProject, deleteProject } = useProjectWorkspace()

const form = reactive({
  name: '',
  description: '',
  color: 'indigo',
  status: 'planning',
  dueDate: '',
  progress: 0,
})

const isDeleting = ref(false)
const errors = ref({})

const project = computed(() => projects.value.find(p => p.id === editingProjectId.value))

watch(projectSettingsModalOpen, (isOpen) => {
  if (isOpen && project.value) {
    form.name = project.value.name
    form.description = project.value.description || ''
    form.color = project.value.color
    form.status = project.value.status
    form.dueDate = project.value.dueDate ? project.value.dueDate.split('T')[0] : ''
    form.progress = project.value.progress
    isDeleting.value = false
    errors.value = {}
  } else if (!isOpen) {
    editingProjectId.value = null
  }
})

const colors = [
  { id: 'indigo', bg: 'bg-indigo-500', ring: 'ring-indigo-500' },
  { id: 'emerald', bg: 'bg-emerald-500', ring: 'ring-emerald-500' },
  { id: 'amber', bg: 'bg-amber-500', ring: 'ring-amber-500' },
  { id: 'rose', bg: 'bg-rose-500', ring: 'ring-rose-500' },
  { id: 'sky', bg: 'bg-sky-500', ring: 'ring-sky-500' },
  { id: 'violet', bg: 'bg-violet-500', ring: 'ring-violet-500' },
  { id: 'orange', bg: 'bg-orange-500', ring: 'ring-orange-500' }
]

function submit() {
  errors.value = {}
  if (!form.name.trim()) {
    errors.value.name = 'Vui lòng nhập tên dự án.'
    return
  }
  updateProject(editingProjectId.value, { ...form, name: form.name.trim() })
  projectSettingsModalOpen.value = false
}

function confirmDelete() {
  const pId = editingProjectId.value
  projectSettingsModalOpen.value = false
  deleteProject(pId)
  router.push('/projects')
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') projectSettingsModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="projectSettingsModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="projectSettingsModalOpen = false"></div>
      
      <!-- Modal Content -->
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-full overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <Settings class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Cài đặt Dự án</h2>
              <p class="text-sm text-slate-500">Chỉnh sửa thông tin cơ bản hoặc xóa dự án.</p>
            </div>
          </div>
          <button type="button" @click="projectSettingsModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-8 custom-scrollbar flex-1">
          <!-- Basic Info -->
          <form id="project-settings-form" @submit.prevent="submit" class="space-y-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Tên dự án *</label>
              <input v-model="form.name" placeholder="Ví dụ: Thiết kế lại website" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', errors.name ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.name" class="text-xs font-medium text-red-500 mt-1">{{ errors.name }}</p>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Mô tả</label>
              <textarea v-model="form.description" rows="3" placeholder="Mô tả ngắn về dự án..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none resize-none"></textarea>
            </div>

            <div class="space-y-3">
              <label class="block text-sm font-semibold text-slate-700">Màu sắc</label>
              <div class="flex flex-wrap gap-3">
                <label 
                  v-for="color in colors" 
                  :key="color.id"
                  class="relative cursor-pointer"
                >
                  <input v-model="form.color" type="radio" :value="color.id" class="sr-only" />
                  <div 
                    :class="[
                      'w-8 h-8 rounded-full flex items-center justify-center transition-all',
                      color.bg,
                      form.color === color.id ? `ring-4 ring-offset-2 ${color.ring}` : 'hover:scale-110 shadow-sm'
                    ]"
                  >
                    <Check v-if="form.color === color.id" class="w-4 h-4 text-white" />
                  </div>
                </label>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Trạng thái</label>
                <select v-model="form.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none appearance-none cursor-pointer">
                  <option value="planning">Lập kế hoạch</option>
                  <option value="active">Đang triển khai</option>
                  <option value="on_hold">Tạm dừng</option>
                  <option value="completed">Hoàn thành</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
                <input v-model="form.dueDate" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none cursor-pointer" />
              </div>
            </div>

            <div class="space-y-3">
              <label class="flex justify-between text-sm font-semibold text-slate-700">
                <span>Tiến độ</span>
                <span class="text-violet-600">{{ form.progress }}%</span>
              </label>
              <input v-model="form.progress" type="range" min="0" max="100" step="5" class="w-full accent-violet-600 h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" />
            </div>
          </form>

          <hr class="border-slate-100" />

          <!-- Danger Zone -->
          <div>
            <h3 class="text-sm font-bold text-rose-600 flex items-center gap-2 mb-3">
              <AlertTriangle class="w-4 h-4" /> Vùng nguy hiểm
            </h3>
            <div class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-between gap-4">
              <div>
                <strong class="block text-sm text-rose-900">Xóa dự án này</strong>
                <span class="text-xs text-rose-700">Hành động này sẽ xóa toàn bộ nhiệm vụ và tệp đính kèm. Không thể hoàn tác.</span>
              </div>
              <button 
                v-if="!isDeleting" 
                @click="isDeleting = true" 
                class="px-4 py-2 bg-white border border-rose-200 text-rose-600 text-sm font-medium rounded-lg hover:bg-rose-50 transition-colors shrink-0"
              >
                Xóa dự án
              </button>
              <div v-else class="flex gap-2 shrink-0">
                <button @click="isDeleting = false" class="px-3 py-2 bg-white text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50">Hủy</button>
                <button @click="confirmDelete" class="px-3 py-2 bg-rose-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-rose-700">Xác nhận xóa</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="projectSettingsModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Đóng
          </button>
          <button form="project-settings-form" type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
            <Check class="w-4 h-4" /> Lưu thay đổi
          </button>
        </footer>
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

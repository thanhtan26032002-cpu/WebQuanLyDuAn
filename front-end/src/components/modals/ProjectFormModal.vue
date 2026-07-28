<script setup>
import { reactive, ref, onMounted, onUnmounted } from 'vue'
import { X, FolderKanban, Plus, Check, Paperclip, UploadCloud } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { members, projectModalOpen, addProject, formatBytes } = useProjectWorkspace()

const selectedFiles = ref([])

const form = reactive({
  name: '',
  description: '',
  color: 'indigo',
  status: 'planning',
  dueDate: '',
  progress: 0,
  memberIds: [1],
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

const errors = ref({})

function toggleMember(memberId) {
  const index = form.memberIds.indexOf(memberId)
  if (index >= 0) form.memberIds.splice(index, 1)
  else form.memberIds.push(memberId)
}

async function submit() {
  errors.value = {}
  if (!form.name.trim()) {
    errors.value.name = 'Vui lòng nhập tên dự án.'
    return
  }
  
  const res = await addProject({ ...form, name: form.name.trim(), memberIds: [...form.memberIds], files: [...selectedFiles.value] })
  if (res && res.success === false && res.errors) {
    errors.value = res.errors
  } else if (res && res.success) {
    // modal is closed in useProjectWorkspace
    form.name = ''
    form.description = ''
    form.dueDate = ''
    form.progress = 0
    selectedFiles.value = []
  }
}

const onFileSelect = (e) => {
  if (e.target.files && e.target.files.length > 0) {
    selectedFiles.value.push(...Array.from(e.target.files))
  }
}

const removeSelectedFile = (idx) => {
  selectedFiles.value.splice(idx, 1)
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') projectModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="projectModalOpen = false"></div>
      
      <!-- Modal Content -->
      <form @submit.prevent="submit" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-full overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <FolderKanban class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Tạo dự án mới</h2>
              <p class="text-sm text-slate-500">Thiết lập mục tiêu, thành viên và tiến độ ban đầu.</p>
            </div>
          </div>
          <button type="button" @click="projectModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-5 custom-scrollbar flex-1">
          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Tên dự án *</label>
            <input v-model="form.name" autofocus placeholder="Ví dụ: Thiết kế lại website" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', errors.name ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
            <p v-if="errors.name" class="text-xs font-medium text-red-500">{{ errors.name }}</p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Mô tả</label>
            <textarea v-model="form.description" rows="3" placeholder="Mô tả ngắn về dự án..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none resize-none"></textarea>
            <p v-if="errors.description" class="text-xs font-medium text-red-500">{{ errors.description }}</p>
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
              <select v-model="form.status" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.status ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="planning">Lập kế hoạch</option>
                <option value="active">Đang triển khai</option>
                <option value="on_hold">Tạm dừng</option>
                <option value="completed">Hoàn thành</option>
              </select>
              <p v-if="errors.status" class="text-xs font-medium text-red-500">{{ errors.status }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
              <input v-model="form.dueDate" type="date" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none cursor-pointer', errors.due_date ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.due_date" class="text-xs font-medium text-red-500">{{ errors.due_date }}</p>
            </div>
          </div>

          <div class="space-y-3">
            <label class="flex justify-between text-sm font-semibold text-slate-700">
              <span>Tiến độ</span>
              <span class="text-violet-600">{{ form.progress }}%</span>
            </label>
            <input v-model="form.progress" type="range" min="0" max="100" step="5" class="w-full accent-violet-600 h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" />
          </div>

          <div class="space-y-3">
            <label class="block text-sm font-semibold text-slate-700">Thành viên</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="member in members" 
                :key="member.id" 
                type="button" 
                @click="toggleMember(member.id)"
                :class="[
                  'flex items-center gap-2 px-3 py-1.5 rounded-xl border transition-all text-sm font-medium',
                  form.memberIds.includes(member.id) 
                    ? 'border-violet-200 bg-violet-50 text-violet-700' 
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                ]"
              >
                <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold text-white', `bg-${member.color}-500`]">
                  {{ member.initials }}
                </span>
                {{ member.name }}
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <label class="block text-sm font-semibold text-slate-700">Tệp đính kèm (Tuỳ chọn)</label>
            <div class="border border-dashed border-slate-300 rounded-xl p-4 bg-slate-50 flex flex-col items-center justify-center text-center">
              <input type="file" id="new-project-upload" multiple class="hidden" @change="onFileSelect" />
              <label for="new-project-upload" class="flex items-center gap-2 text-xs font-semibold text-violet-600 hover:text-violet-700 bg-violet-100/60 hover:bg-violet-100 px-3 py-1.5 rounded-lg cursor-pointer transition-colors">
                <Paperclip class="w-3.5 h-3.5" /> Thêm file đính kèm
              </label>
            </div>
            <div v-if="selectedFiles.length > 0" class="space-y-1.5 max-h-28 overflow-y-auto custom-scrollbar pr-1">
              <div v-for="(f, idx) in selectedFiles" :key="idx" class="bg-white border border-slate-200 rounded-lg p-2 flex items-center justify-between text-xs shadow-sm">
                <span class="truncate font-medium text-slate-700 max-w-[280px]">{{ f.name }}</span>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-slate-400">{{ formatBytes(f.size) }}</span>
                  <button type="button" @click.stop="removeSelectedFile(idx)" class="text-slate-400 hover:text-rose-500 p-0.5 rounded hover:bg-rose-50">
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="projectModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Hủy
          </button>
          <button type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
            <Plus class="w-4 h-4" /> Tạo dự án
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
</style>

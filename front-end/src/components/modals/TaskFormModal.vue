<script setup>
import { reactive, ref, onMounted, onUnmounted } from 'vue'
import { X, CheckSquare, Plus } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { projects, members, taskModalOpen, addTask } = useProjectWorkspace()
const form = reactive({
  title: '',
  description: '',
  status: 'todo',
  priority: 'medium',
  projectId: '',
  assigneeId: '',
  dueDate: '',
  tags: '',
})

const errors = ref({})

async function submit() {
  errors.value = {}
  if (!form.title.trim()) {
    errors.value.title = 'Vui lòng nhập tiêu đề nhiệm vụ.'
    return
  }
  
  const res = await addTask({ ...form, title: form.title.trim() })
  if (res && res.success === false && res.errors) {
    errors.value = res.errors
  } else if (res && res.success) {
    // modal is closed in useProjectWorkspace
    form.title = ''
    form.description = ''
    form.dueDate = ''
    form.tags = ''
    form.projectId = ''
    form.assigneeId = ''
  }
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') taskModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="taskModalOpen = false"></div>
      
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
          <button type="button" @click="taskModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-5 custom-scrollbar flex-1">
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
                <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
              </select>
              <p v-if="errors.project_code" class="text-xs font-medium text-red-500">{{ errors.project_code }}</p>
            </div>
            
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Người phụ trách</label>
              <select v-model="form.assigneeId" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.assignee_code ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="">— Không có —</option>
                <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
              </select>
              <p v-if="errors.assignee_code" class="text-xs font-medium text-red-500">{{ errors.assignee_code }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
              <input v-model="form.dueDate" type="date" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none cursor-pointer', errors.due_date ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.due_date" class="text-xs font-medium text-red-500">{{ errors.due_date }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Nhãn</label>
              <input v-model="form.tags" placeholder="Frontend, UI/UX" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" />
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="taskModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
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
</style>

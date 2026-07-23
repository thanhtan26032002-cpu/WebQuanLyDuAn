<script setup>
import { ref, watch } from 'vue'
import { X, Users, Hash, AlignLeft, Trash2 } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { groups, editGroupModalOpen, activeEditGroupId, updateGroup, deleteGroup } = useProjectWorkspace()

const formData = ref({
  name: '',
  description: '',
  icon: '🚀',
  color: 'violet'
})

watch(() => activeEditGroupId.value, (newId) => {
  if (newId) {
    const group = groups.value.find(g => g.id === newId)
    if (group) {
      formData.value = { ...group }
    }
  }
})

const colors = [
  { id: 'violet', bg: 'bg-violet-500' },
  { id: 'indigo', bg: 'bg-indigo-500' },
  { id: 'blue', bg: 'bg-blue-500' },
  { id: 'sky', bg: 'bg-sky-500' },
  { id: 'emerald', bg: 'bg-emerald-500' },
  { id: 'amber', bg: 'bg-amber-500' },
  { id: 'orange', bg: 'bg-orange-500' },
  { id: 'rose', bg: 'bg-rose-500' },
  { id: 'pink', bg: 'bg-pink-500' },
  { id: 'slate', bg: 'bg-slate-500' },
]

const emojis = ['🚀', '🖥️', '🎨', '📱', '📈', '💡', '🔥', '✨', '⚡', '🛠️']

const close = () => {
  editGroupModalOpen.value = false
  activeEditGroupId.value = null
  formData.value = { name: '', description: '', icon: '🚀', color: 'violet' }
}

const submit = () => {
  if (!formData.value.name || !activeEditGroupId.value) return
  updateGroup(activeEditGroupId.value, { ...formData.value })
  close()
}

const handleDelete = () => {
  if (confirm('Bạn có chắc chắn muốn xóa nhóm này không? Toàn bộ thành viên sẽ bị đẩy ra ngoài nhóm.')) {
    deleteGroup(activeEditGroupId.value)
    close()
  }
}
</script>

<template>
  <div v-if="editGroupModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="close"></div>
    
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
      <header class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
          <Users class="w-5 h-5 text-violet-600" /> Cập nhật nhóm
        </h2>
        <button @click="close" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-200 transition-colors">
          <X class="w-5 h-5" />
        </button>
      </header>
      
      <div class="p-6 overflow-y-auto custom-scrollbar">
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Tên nhóm *</label>
            <div class="relative">
              <Hash class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="formData.name" required type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="VD: Nhóm Marketing" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Mô tả nhóm</label>
            <div class="relative">
              <AlignLeft class="absolute left-3 top-3 w-4 h-4 text-slate-400" />
              <textarea v-model="formData.description" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 resize-none" placeholder="Phụ trách các công việc..."></textarea>
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Biểu tượng</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="emoji in emojis" :key="emoji"
                type="button"
                @click="formData.icon = emoji"
                :class="['w-10 h-10 rounded-xl text-lg flex items-center justify-center transition-all bg-slate-50 border', formData.icon === emoji ? 'border-violet-400 bg-violet-50 shadow-sm' : 'border-slate-200 hover:bg-slate-100']"
              >
                {{ emoji }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Màu sắc chủ đạo</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="color in colors" :key="color.id"
                type="button"
                @click="formData.color = color.id"
                :class="['w-8 h-8 rounded-full flex items-center justify-center transition-all', color.bg, formData.color === color.id ? 'ring-4 ring-offset-2 ring-violet-200 scale-110' : 'opacity-80 hover:opacity-100']"
              ></button>
            </div>
          </div>
        </form>
      </div>

      <footer class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-between gap-3">
        <button type="button" @click="handleDelete" class="px-4 py-2 rounded-xl text-sm font-medium text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition-colors flex items-center gap-2">
          <Trash2 class="w-4 h-4" /> Xóa nhóm
        </button>
        <div class="flex gap-2">
          <button type="button" @click="close" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-200 transition-colors">
            Hủy bỏ
          </button>
          <button @click="submit" class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-lg transition-all">
            Cập nhật
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>

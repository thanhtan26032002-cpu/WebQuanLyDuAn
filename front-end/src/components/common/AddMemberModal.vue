<script setup>
import { ref } from 'vue'
import { X, UserPlus, Mail, Phone, Briefcase, Hash } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { addMemberModalOpen, groups, addMember } = useProjectWorkspace()

const formData = ref({
  name: '',
  email: '',
  phone: '',
  role: '',
  department: '',
  groupId: null,
  bio: '',
  color: 'blue'
})

const colors = [
  { id: 'blue', bg: 'bg-blue-500' },
  { id: 'purple', bg: 'bg-purple-500' },
  { id: 'pink', bg: 'bg-pink-500' },
  { id: 'orange', bg: 'bg-orange-500' },
  { id: 'green', bg: 'bg-green-500' },
  { id: 'amber', bg: 'bg-amber-500' },
  { id: 'rose', bg: 'bg-rose-500' },
  { id: 'sky', bg: 'bg-sky-500' },
]

const close = () => {
  addMemberModalOpen.value = false
  // reset
  formData.value = {
    name: '', email: '', phone: '', role: '', department: '', groupId: null, bio: '', color: 'blue'
  }
}

const submit = () => {
  if (!formData.value.name || !formData.value.email) return
  addMember(formData.value)
  close()
}
</script>

<template>
  <div v-if="addMemberModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="close"></div>
    
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
      <header class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
          <UserPlus class="w-5 h-5 text-violet-600" /> Thêm thành viên mới
        </h2>
        <button @click="close" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-200 transition-colors">
          <X class="w-5 h-5" />
        </button>
      </header>
      
      <div class="p-6 overflow-y-auto custom-scrollbar">
        <form @submit.prevent="submit" class="space-y-4">
          <!-- Avatar color select -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Màu đại diện</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="color in colors" :key="color.id"
                type="button"
                @click="formData.color = color.id"
                :class="['w-8 h-8 rounded-full flex items-center justify-center transition-all', color.bg, formData.color === color.id ? 'ring-4 ring-offset-2 ring-violet-200 scale-110' : 'opacity-80 hover:opacity-100']"
              ></button>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Họ tên *</label>
              <div class="relative">
                <input v-model="formData.name" required type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="Nguyễn Văn A" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Email *</label>
              <div class="relative">
                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="formData.email" required type="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="email@company.com" />
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Số điện thoại</label>
              <div class="relative">
                <Phone class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="formData.phone" type="tel" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="09xx xxx xxx" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Vị trí (Role)</label>
              <div class="relative">
                <Briefcase class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="formData.role" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="Frontend Developer" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Phòng ban</label>
              <input v-model="formData.department" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="Phát triển" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Thêm vào nhóm</label>
              <div class="relative">
                <Hash class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <select v-model="formData.groupId" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 appearance-none">
                  <option :value="null">Không thuộc nhóm nào</option>
                  <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
                </select>
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Tiểu sử ngắn</label>
            <textarea v-model="formData.bio" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 resize-none" placeholder="Vài dòng giới thiệu..."></textarea>
          </div>
        </form>
      </div>

      <footer class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
        <button type="button" @click="close" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-200 transition-colors">
          Hủy bỏ
        </button>
        <button @click="submit" class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-lg transition-all">
          Thêm thành viên
        </button>
      </footer>
    </div>
  </div>
</template>

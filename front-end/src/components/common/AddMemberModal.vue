<script setup>
import { computed, ref, watch } from 'vue'
import { X, UserPlus, Mail, Phone, Briefcase, Hash, LockKeyhole } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { addMemberModalOpen, addMemberTargetGroupId, closeAddMemberModal, groups, addMember, currentUser } = useProjectWorkspace()
const canAssignSystemRole = computed(() => currentUser.value?.role === 'admin')

const formData = ref({
  name: '',
  email: '',
  password: '',
  phone: '',
  jobTitle: '',
  systemRole: 'member',
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

const errors = ref({})
const phonePattern = /^\+?[0-9]{9,15}$/

watch(addMemberModalOpen, (isOpen) => {
  if (isOpen) {
    formData.value.groupId = addMemberTargetGroupId.value || null
  }
})

const close = () => {
  closeAddMemberModal()
  // reset
  formData.value = {
    name: '', email: '', password: '', phone: '', jobTitle: '', systemRole: 'member', department: '', groupId: null, bio: '', color: 'blue'
  }
  errors.value = {}
}

const submit = async () => {
  errors.value = {}
  const name = formData.value.name?.trim() || ''
  const email = formData.value.email?.trim() || ''
  const phone = formData.value.phone?.trim() || ''

  if (!name) errors.value.name = 'Vui lòng nhập họ tên.'
  if (!email) {
    errors.value.email = 'Vui lòng nhập email.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.value.email = 'Vui lòng nhập địa chỉ email hợp lệ.'
  }
  if (!phone) {
    errors.value.phone = 'Vui lòng nhập số điện thoại.'
  } else if (!phonePattern.test(phone)) {
    errors.value.phone = 'Số điện thoại phải gồm từ 9 đến 15 chữ số và chỉ có thể bắt đầu bằng dấu +.'
  }
  if (!formData.value.password || formData.value.password.length < 8) errors.value.password = 'Mật khẩu phải có ít nhất 8 ký tự.'
  if (!formData.value.jobTitle?.trim()) errors.value.job_title = 'Vui lòng nhập chức danh.'
  if (!formData.value.department?.trim()) errors.value.department = 'Vui lòng nhập phòng ban.'
  if (Object.keys(errors.value).length > 0) return
  
  const res = await addMember({ ...formData.value, name, email, phone })
  if (res && res.success === false && res.errors) {
    errors.value = res.errors
  } else if (res && res.success) {
    close()
  }
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

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Mật khẩu ban đầu *</label>
            <div class="relative">
              <LockKeyhole class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input v-model="formData.password" type="password" autocomplete="new-password" @input="errors.password = ''" :class="['w-full rounded-xl border bg-slate-50 py-2 pl-9 pr-4 text-sm outline-none', errors.password ? 'border-red-300' : 'border-slate-200 focus:border-violet-500']" placeholder="Tối thiểu 8 ký tự" />
            </div>
            <p v-if="errors.password" class="mt-1 text-xs font-medium text-red-500">{{ errors.password }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Họ tên *</label>
              <div class="relative">
                <input v-model="formData.name" type="text" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-1 transition-all', errors.name ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-violet-500']" placeholder="Nguyễn Văn A" />
              </div>
              <p v-if="errors.name" class="text-xs font-medium text-red-500 mt-1">{{ errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Email *</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail class="h-4 w-4 text-slate-400" />
                </div>
                <input v-model="formData.email" type="email" @input="errors.email = ''" :class="['w-full bg-slate-50 border rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 transition-all', errors.email ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-violet-500']" placeholder="nguyenvana@example.com" />
              </div>
              <p v-if="errors.email" class="text-xs font-medium text-red-500 mt-1">{{ errors.email }}</p>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Số điện thoại *</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Phone class="h-4 w-4 text-slate-400" />
                </div>
                <input v-model="formData.phone" type="tel" inputmode="tel" maxlength="16" @input="errors.phone = ''" :class="['w-full bg-slate-50 border rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-1 transition-all', errors.phone ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-violet-500']" placeholder="0901234567" />
              </div>
              <p v-if="errors.phone" class="text-xs font-medium text-red-500 mt-1">{{ errors.phone }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Chức danh *</label>
              <div class="relative">
                <Briefcase class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                <input v-model="formData.jobTitle" type="text" @input="errors.job_title = ''" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="Frontend Developer" />
              </div>
              <p v-if="errors.job_title" class="mt-1 text-xs font-medium text-red-500">{{ errors.job_title }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Phòng ban *</label>
              <input v-model="formData.department" type="text" @input="errors.department = ''" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" placeholder="Phát triển" />
              <p v-if="errors.department" class="mt-1 text-xs font-medium text-red-500">{{ errors.department }}</p>
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

          <div v-if="canAssignSystemRole">
            <label class="block text-sm font-semibold text-slate-700 mb-1">Vai trò hệ thống</label>
            <select v-model="formData.systemRole" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm outline-none focus:border-violet-500">
              <option value="member">Nhân viên</option>
              <option value="project_manager">Quản lý dự án</option>
              <option value="viewer">Chỉ xem</option>
              <option value="admin">Quản trị viên</option>
            </select>
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
          Tạo tài khoản thành viên
        </button>
      </footer>
    </div>
  </div>
</template>

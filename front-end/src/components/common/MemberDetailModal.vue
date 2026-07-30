<script setup>
import { computed, ref, watch } from 'vue'
import { X, Mail, Phone, Briefcase, Hash, CalendarDays, Edit2, Check, User } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { activeMemberId, memberDetailModalOpen, members, groups, updateMember, currentUser } = useProjectWorkspace()

const member = computed(() => members.value.find(m => m.id === activeMemberId.value))
const canEditMember = computed(() => {
  const role = currentUser.value?.role
  return currentUser.value?.code === member.value?.id || ['admin', 'project_manager', 'manager'].includes(role)
})
const isEditing = ref(false)
const editedMember = ref({})
const systemRoleLabels = {
  admin: 'Quản trị viên',
  project_manager: 'Quản lý dự án',
  member: 'Nhân viên',
}

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

watch(activeMemberId, (newVal) => {
  if (newVal && member.value) {
    isEditing.value = false
    editedMember.value = {
      ...JSON.parse(JSON.stringify(member.value)),
      groupId: groups.value.find(group => group.memberIds.includes(member.value.id))?.id || null,
    }
  }
})

const memberGroups = computed(() => {
  if (!member.value) return []
  return groups.value.filter(g => g.memberIds.includes(member.value.id))
})

const displayColor = computed(() => {
  if (isEditing.value && editedMember.value.color) {
    return editedMember.value.color
  }
  return member.value?.color || 'blue'
})

const memberThemeClasses = {
  blue: {
    header: 'from-blue-500 to-blue-600',
    avatar: 'from-blue-400 to-blue-600',
    bg: 'bg-blue-500',
    badge: 'bg-blue-50 text-blue-700 border-blue-100',
  },
  purple: {
    header: 'from-purple-500 to-purple-600',
    avatar: 'from-purple-400 to-purple-600',
    bg: 'bg-purple-500',
    badge: 'bg-purple-50 text-purple-700 border-purple-100',
  },
  pink: {
    header: 'from-pink-500 to-pink-600',
    avatar: 'from-pink-400 to-pink-600',
    bg: 'bg-pink-500',
    badge: 'bg-pink-50 text-pink-700 border-pink-100',
  },
  orange: {
    header: 'from-orange-500 to-orange-600',
    avatar: 'from-orange-400 to-orange-600',
    bg: 'bg-orange-500',
    badge: 'bg-orange-50 text-orange-700 border-orange-100',
  },
  green: {
    header: 'from-green-500 to-green-600',
    avatar: 'from-green-400 to-green-600',
    bg: 'bg-green-500',
    badge: 'bg-green-50 text-green-700 border-green-100',
  },
  amber: {
    header: 'from-amber-500 to-amber-600',
    avatar: 'from-amber-400 to-amber-600',
    bg: 'bg-amber-500',
    badge: 'bg-amber-50 text-amber-700 border-amber-100',
  },
  rose: {
    header: 'from-rose-500 to-rose-600',
    avatar: 'from-rose-400 to-rose-600',
    bg: 'bg-rose-500',
    badge: 'bg-rose-50 text-rose-700 border-rose-100',
  },
  sky: {
    header: 'from-sky-500 to-sky-600',
    avatar: 'from-sky-400 to-sky-600',
    bg: 'bg-sky-500',
    badge: 'bg-sky-50 text-sky-700 border-sky-100',
  },
  violet: {
    header: 'from-violet-500 to-violet-600',
    avatar: 'from-violet-400 to-violet-600',
    bg: 'bg-violet-500',
    badge: 'bg-violet-50 text-violet-700 border-violet-100',
  },
}

const displayInitials = computed(() => {
  if (isEditing.value && editedMember.value.name) {
    return editedMember.value.name.split(' ').filter(Boolean).map(n => n[0]).join('').toUpperCase().slice(0, 2) || '??'
  }
  return member.value?.initials || '??'
})

const errors = ref({})
const phonePattern = /^\+?[0-9]{9,15}$/

const startEditing = () => {
  if (!member.value || !canEditMember.value || member.value.profileLimited) return
  editedMember.value = {
    ...JSON.parse(JSON.stringify(member.value)),
    groupId: memberGroups.value[0]?.id || null,
  }
  errors.value = {}
  isEditing.value = true
}

const close = () => {
  memberDetailModalOpen.value = false
  activeMemberId.value = null
  isEditing.value = false
  errors.value = {}
}

const save = async () => {
  errors.value = {}
  if (!member.value) return
  const name = editedMember.value.name?.trim() || ''
  const email = editedMember.value.email?.trim() || ''
  const phone = editedMember.value.phone?.trim() || ''

  if (!name) errors.value.name = 'Vui lòng nhập họ tên thành viên.'
  if (!email) {
    errors.value.email = 'Vui lòng nhập địa chỉ email.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.value.email = 'Vui lòng nhập địa chỉ email hợp lệ.'
  }
  if (!phone) {
    errors.value.phone = 'Vui lòng nhập số điện thoại.'
  } else if (!phonePattern.test(phone)) {
    errors.value.phone = 'Số điện thoại phải gồm từ 9 đến 15 chữ số và chỉ có thể bắt đầu bằng dấu +.'
  }
  if (Object.keys(errors.value).length > 0) return

  const result = await updateMember(member.value.id, {
    name,
    email,
    phone,
    role: editedMember.value.role,
    systemRole: editedMember.value.systemRole,
    department: editedMember.value.department,
    bio: editedMember.value.bio,
    groupId: editedMember.value.groupId || null,
    color: editedMember.value.color || member.value.color || 'blue',
  })
  if (result?.success) {
    isEditing.value = false
  } else if (result?.errors) {
    errors.value = result.errors
  }
}
</script>

<template>
  <div v-if="memberDetailModalOpen && member" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="close"></div>
    
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header with Gradient -->
      <header :class="['relative h-32 flex items-end px-6 pb-6 pt-12 shrink-0 bg-gradient-to-br transition-all duration-300', memberThemeClasses[displayColor]?.header || 'from-blue-500 to-blue-600']">
        <!-- Close button -->
        <button @click="close" class="absolute top-4 right-4 text-white/70 hover:text-white bg-black/10 hover:bg-black/20 p-1.5 rounded-lg transition-colors">
          <X class="w-5 h-5" />
        </button>

        <div class="absolute -bottom-10 left-6">
          <div :class="['w-24 h-24 rounded-2xl shadow-lg border-4 border-white flex items-center justify-center text-4xl font-bold text-white transition-all duration-300 bg-gradient-to-br', memberThemeClasses[displayColor]?.avatar || 'from-blue-400 to-blue-600']">
            {{ displayInitials }}
          </div>
          <div :class="['absolute -bottom-2 -right-2 w-6 h-6 border-4 border-white rounded-full', member.online ? 'bg-emerald-500' : 'bg-slate-300']"></div>
        </div>

        <div class="absolute bottom-4 right-6 flex gap-2">
          <button 
            v-if="!isEditing && canEditMember && !member.profileLimited"
            @click="startEditing"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white font-medium text-sm rounded-lg transition-colors shadow-sm"
          >
            <Edit2 class="w-4 h-4" /> {{ currentUser?.role === 'admin' && member.id !== currentUser?.code ? 'Chỉnh sửa & phân quyền' : 'Chỉnh sửa' }}
          </button>
          <button 
            v-if="isEditing"
            @click="save"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-slate-900 font-bold text-sm rounded-lg hover:bg-slate-50 transition-colors shadow-sm"
          >
            <Check class="w-4 h-4" /> Lưu lại
          </button>
        </div>
      </header>
      
      <div class="px-6 pt-14 pb-6 overflow-y-auto custom-scrollbar">
        <template v-if="!isEditing">
          <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 leading-tight mb-1">{{ member.name }}</h2>
            <p class="text-violet-600 font-medium">{{ member.role }}</p>
            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ systemRoleLabels[member.systemRole] || 'Nhân viên' }}</p>
          </div>
          
          <div v-if="!member.profileLimited" class="mb-6">
            <p class="text-sm text-slate-600 leading-relaxed">{{ member.bio || 'Chưa có tiểu sử.' }}</p>
          </div>
          <div v-else class="mb-6 rounded-xl border border-violet-100 bg-violet-50 px-4 py-3 text-sm text-violet-700">
            Bạn đang xem hồ sơ cơ bản. Email, điện thoại và thông tin riêng tư chỉ hiển thị cho chính chủ hoặc người quản lý.
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div v-if="!member.profileLimited" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400 shrink-0">
                <Mail class="w-4 h-4" />
              </div>
              <div class="min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Email</p>
                <p class="text-sm font-medium text-slate-900 truncate">{{ member.email }}</p>
              </div>
            </div>
            <div v-if="!member.profileLimited" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400 shrink-0">
                <Phone class="w-4 h-4" />
              </div>
              <div class="min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Điện thoại</p>
                <p class="text-sm font-medium text-slate-900 truncate">{{ member.phone || 'Chưa cập nhật' }}</p>
              </div>
            </div>
            <div v-if="!member.profileLimited" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400 shrink-0">
                <Briefcase class="w-4 h-4" />
              </div>
              <div class="min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Phòng ban</p>
                <p class="text-sm font-medium text-slate-900 truncate">{{ member.department || 'Chưa cập nhật' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
              <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-slate-400 shrink-0">
                <CalendarDays class="w-4 h-4" />
              </div>
              <div class="min-w-0">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Ngày tham gia</p>
                <p class="text-sm font-medium text-slate-900 truncate">{{ member.joinDate }}</p>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
              <Hash class="w-4 h-4 text-slate-400" /> Nhóm tham gia
            </h3>
            <div v-if="memberGroups.length > 0" class="flex flex-wrap gap-2">
              <div v-for="group in memberGroups" :key="group.id" :class="['px-3 py-1.5 rounded-lg text-sm font-medium border flex items-center gap-1.5', memberThemeClasses[group.color]?.badge || 'bg-violet-50 text-violet-700 border-violet-100']">
                <span>{{ group.icon }}</span> {{ group.name }}
              </div>
            </div>
            <p v-else class="text-sm text-slate-500">Chưa tham gia nhóm nào.</p>
          </div>
        </template>
        
        <template v-else>
          <!-- Edit Form -->
          <form @submit.prevent="save" class="space-y-4">
            <!-- Avatar color select -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">Màu đại diện</label>
              <div class="flex flex-wrap gap-2">
                <button 
                  v-for="color in colors" :key="color.id"
                  type="button"
                  @click="editedMember.color = color.id"
                  :class="['w-8 h-8 rounded-full flex items-center justify-center transition-all', color.bg, editedMember.color === color.id ? 'ring-4 ring-offset-2 ring-violet-200 scale-110' : 'opacity-80 hover:opacity-100']"
                ></button>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Họ tên *</label>
                <input v-model="editedMember.name" type="text" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2 text-sm focus:outline-none transition-all', errors.name ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-1 focus:ring-violet-500']" />
                <p v-if="errors.name" class="text-xs font-medium text-red-500 mt-1">{{ errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Chức danh</label>
                <input v-model="editedMember.role" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" />
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email *</label>
                <input v-model="editedMember.email" type="email" @input="errors.email = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2 text-sm focus:outline-none transition-all', errors.email ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-1 focus:ring-violet-500']" />
                <p v-if="errors.email" class="text-xs font-medium text-red-500 mt-1">{{ errors.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Số điện thoại *</label>
                <input v-model="editedMember.phone" type="tel" inputmode="tel" maxlength="16" @input="errors.phone = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2 text-sm focus:outline-none transition-all', errors.phone ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-500' : 'border-slate-200 focus:border-violet-500 focus:ring-1 focus:ring-violet-500']" placeholder="0901234567" />
                <p v-if="errors.phone" class="text-xs font-medium text-red-500 mt-1">{{ errors.phone }}</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Phòng ban</label>
              <input v-model="editedMember.department" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500" />
            </div>
            <div v-if="currentUser?.role === 'admin' && member.id !== currentUser?.code" class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
              <label class="block text-sm font-semibold text-violet-900 mb-1">Vai trò hệ thống</label>
              <select v-model="editedMember.systemRole" class="w-full rounded-xl border border-violet-200 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                <option value="admin">Quản trị viên</option>
                <option value="project_manager">Quản lý dự án</option>
                <option value="member">Nhân viên</option>
              </select>
              <p class="mt-2 text-xs leading-5 text-violet-700">Quyền mới có hiệu lực ngay sau khi lưu. Người dùng không thể tự thay đổi vai trò của mình.</p>
              <p v-if="errors.system_role" class="mt-1 text-xs font-medium text-rose-600">{{ errors.system_role }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Thuộc nhóm</label>
              <select v-model="editedMember.groupId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 appearance-none">
                <option :value="null">Không thuộc nhóm nào</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                  {{ group.icon }} {{ group.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Tiểu sử ngắn</label>
              <textarea v-model="editedMember.bio" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 resize-none"></textarea>
            </div>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { User, Bell, Palette, Moon, Sun, CheckCircle2, KeyRound, ShieldCheck } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'
import { apiFetch, parseApiError } from '../services/api'

const activeTab = ref('profile')
const { darkMode, setTheme, notify, currentUser, updateUserProfile, BASE_URL } = useProjectWorkspace()

const userProfile = ref({
  name: currentUser.value?.name || '',
  email: currentUser.value?.email || '',
  jobTitle: currentUser.value?.job_title || '',
  phone: currentUser.value?.phone || '',
  department: currentUser.value?.department || '',
  bio: currentUser.value?.bio || ''
})

const systemRoleLabels = {
  admin: 'Quản trị viên',
  project_manager: 'Quản lý dự án',
  member: 'Nhân viên',
  viewer: 'Người xem',
}

const selectedAvatarFile = ref(null)
const avatarPreview = ref(currentUser.value?.avatar || null)

watch(currentUser, (newVal) => {
  if (newVal) {
    userProfile.value.name = newVal.name || ''
    userProfile.value.email = newVal.email || ''
    userProfile.value.jobTitle = newVal.job_title || ''
    userProfile.value.phone = newVal.phone || ''
    userProfile.value.department = newVal.department || ''
    userProfile.value.bio = newVal.bio || ''
    if (!selectedAvatarFile.value) {
      avatarPreview.value = newVal.avatar || null
    }
  }
}, { deep: true })

const isSavingProfile = ref(false)
const profileErrors = ref({})
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordErrors = ref({})
const isChangingPassword = ref(false)

const onAvatarSelected = (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  if (file.size > 2 * 1024 * 1024) {
    notify('Kích thước ảnh tối đa là 2MB!')
    return
  }
  
  selectedAvatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
}

const clearAvatar = () => {
  selectedAvatarFile.value = null
  avatarPreview.value = null
  // Cần cơ chế backend để xóa avatar thực sự, tạm thời chỉ clear UI
}

const saveProfile = async () => {
  isSavingProfile.value = true
  profileErrors.value = {}
  
  // Validate
  if (!userProfile.value.name.trim()) profileErrors.value.name = 'Họ và tên không được để trống'
  if (!userProfile.value.email.trim()) profileErrors.value.email = 'Email không được để trống'
  if (!userProfile.value.phone.trim()) profileErrors.value.phone = 'Số điện thoại không được để trống'
  if (!userProfile.value.jobTitle.trim()) profileErrors.value.job_title = 'Chức danh không được để trống'
  if (!userProfile.value.department.trim()) profileErrors.value.department = 'Phòng ban không được để trống'
  
  if (Object.keys(profileErrors.value).length > 0) {
    isSavingProfile.value = false
    notify('Vui lòng kiểm tra lại thông tin')
    return
  }
  
  const formData = new FormData()
  formData.append('name', userProfile.value.name)
  formData.append('email', userProfile.value.email)
  formData.append('phone', userProfile.value.phone)
  formData.append('department', userProfile.value.department)
  formData.append('job_title', userProfile.value.jobTitle)
  formData.append('bio', userProfile.value.bio)
  
  if (selectedAvatarFile.value) {
    formData.append('avatar', selectedAvatarFile.value)
  }

  const result = await updateUserProfile(currentUser.value.code, formData)
  
  isSavingProfile.value = false
  
  if (result.success) {
    notify('Đã lưu thông tin cá nhân')
  } else {
    profileErrors.value = result.errors
    notify(result.errors._general || 'Không thể lưu thông tin. Vui lòng kiểm tra lỗi.')
  }
}

const changePassword = async () => {
  passwordErrors.value = {}
  if (!passwordForm.value.current_password) passwordErrors.value.current_password = ['Vui lòng nhập mật khẩu hiện tại.']
  if (passwordForm.value.password.length < 8) passwordErrors.value.password = ['Mật khẩu mới phải có ít nhất 8 ký tự.']
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) passwordErrors.value.password_confirmation = ['Xác nhận mật khẩu mới không khớp.']
  if (Object.keys(passwordErrors.value).length > 0) return

  isChangingPassword.value = true
  try {
    const response = await apiFetch('/api/profile/password', {
      method: 'PUT',
      body: JSON.stringify(passwordForm.value),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) {
      passwordErrors.value = payload.errors || { _general: payload.message || 'Không thể đổi mật khẩu.' }
      return
    }

    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    notify(payload.message || 'Đã đổi mật khẩu thành công')
  } finally {
    isChangingPassword.value = false
  }
}

const tabs = [
  { id: 'profile', label: 'Hồ sơ', icon: User },
  { id: 'security', label: 'Bảo mật', icon: KeyRound },
  { id: 'notifications', label: 'Thông báo', icon: Bell },
  { id: 'appearance', label: 'Giao diện', icon: Palette }
]

const notifications = ref([
  { id: 'assignment', title: 'Phân công nhiệm vụ', desc: 'Khi bạn được giao một nhiệm vụ mới.', checked: true },
  { id: 'deadline', title: 'Nhắc hạn nhiệm vụ', desc: 'Nhắc bạn trước khi nhiệm vụ đến hạn.', checked: true },
  { id: 'comments', title: 'Bình luận mới', desc: 'Khi có bình luận mới trong nhiệm vụ bạn theo dõi.', checked: true },
  { id: 'mentions', title: 'Khi được đề cập', desc: 'Khi đồng đội nhắc đến tên của bạn.', checked: true },
  { id: 'blocked', title: 'Công việc bị chặn', desc: 'Khi một trở ngại ảnh hưởng đến nhiệm vụ.', checked: true }
])

const loadNotificationPreferences = async () => {
  const response = await apiFetch('/api/notification-preferences')
  if (!response.ok) return
  const preferences = await response.json()
  notifications.value.forEach(item => { item.checked = preferences[item.id] ?? true })
}

const toggleNotification = async (item) => {
  const preferences = Object.fromEntries(notifications.value.map(setting => [setting.id, Boolean(setting.checked)]))
  const response = await apiFetch('/api/notification-preferences', { method: 'PUT', body: JSON.stringify(preferences) })
  if (!response.ok) return notify(await parseApiError(response, 'Không thể lưu tùy chọn thông báo'))
  notify(item.checked ? `Đã bật: ${item.title}` : `Đã tắt: ${item.title}`)
}

onMounted(loadNotificationPreferences)

const colors = [
  { id: 'violet', bg: 'bg-[#8b5cf6]', ring: 'ring-[#8b5cf6]' },
  { id: 'blue', bg: 'bg-[#3b82f6]', ring: 'ring-[#3b82f6]' },
  { id: 'emerald', bg: 'bg-[#10b981]', ring: 'ring-[#10b981]' },
  { id: 'amber', bg: 'bg-[#f59e0b]', ring: 'ring-[#f59e0b]' },
  { id: 'rose', bg: 'bg-[#f43f5e]', ring: 'ring-[#f43f5e]' }
]
const activeColor = ref(localStorage.getItem('primaryColor') || 'violet')

const toggleColor = (colorId) => {
  activeColor.value = colorId
  localStorage.setItem('primaryColor', colorId)
  document.documentElement.setAttribute('data-theme-color', colorId)
  notify('Đã cập nhật màu chủ đạo')
}

// Khởi tạo màu theme nếu có
if (activeColor.value) {
  document.documentElement.setAttribute('data-theme-color', activeColor.value)
}
</script>

<template>
  <div class="space-y-6 pb-12 max-w-5xl mx-auto">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-bold text-slate-900 mb-1">Cài đặt</h1>
      <p class="text-slate-500 text-sm">Quản lý hồ sơ, thông báo và trải nghiệm của bạn.</p>
    </div>

    <div class="flex flex-col md:flex-row gap-8">
      <!-- Sidebar Tabs -->
      <div class="w-full md:w-64 shrink-0">
        <nav class="flex md:flex-col gap-2 overflow-x-auto pb-2 md:pb-0 scrollbar-hide">
          <button 
            v-for="tab in tabs" 
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all whitespace-nowrap',
              activeTab === tab.id 
                ? 'bg-violet-50 text-violet-700 shadow-sm' 
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5" :class="activeTab === tab.id ? 'text-violet-600' : 'text-slate-400'" />
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Main Content Panel -->
      <div class="flex-1 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 sm:p-8">
        
        <!-- Profile Tab -->
        <div v-if="activeTab === 'profile'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div>
            <h2 class="text-xl font-bold text-slate-900 mb-1">Thông tin cá nhân</h2>
            <p class="text-sm text-slate-500">Cập nhật ảnh đại diện và thông tin liên hệ của bạn.</p>
          </div>

          <div class="flex items-center gap-6">
            <div 
              class="w-24 h-24 rounded-2xl border-2 border-violet-200 flex items-center justify-center text-3xl font-bold text-violet-700 shadow-sm overflow-hidden"
              :class="avatarPreview ? '' : 'bg-gradient-to-br from-violet-100 to-indigo-100'"
            >
              <img v-if="avatarPreview" :src="avatarPreview.startsWith('blob:') || avatarPreview.startsWith('http') ? avatarPreview : `${BASE_URL}${avatarPreview.startsWith('/') ? '' : '/'}${avatarPreview}`" alt="Avatar" class="w-full h-full object-cover" />
              <span v-else>{{ userProfile.name ? userProfile.name.charAt(0).toUpperCase() : 'U' }}</span>
            </div>
            <div>
              <div class="flex gap-3 mb-2">
                <input type="file" ref="avatarInput" class="hidden" accept="image/jpeg, image/png, image/gif" @change="onAvatarSelected">
                <button @click="$refs.avatarInput.click()" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                  Thay ảnh
                </button>
                <button @click="clearAvatar" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition-colors">
                  Xóa
                </button>
              </div>
              <p class="text-xs text-slate-500">JPG, GIF hoặc PNG. Tối đa 2MB.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Họ và tên <span class="text-rose-500">*</span></label>
              <input type="text" v-model="userProfile.name" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" :class="{'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20': profileErrors.name}" />
              <p v-if="profileErrors.name" class="text-xs text-rose-500 mt-1">{{ profileErrors.name }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Chức vụ <span class="text-rose-500">*</span></label>
              <input type="text" v-model="userProfile.jobTitle" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" :class="{'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20': profileErrors.job_title}" />
              <p v-if="profileErrors.job_title" class="text-xs text-rose-500 mt-1">{{ profileErrors.job_title }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Email <span class="text-rose-500">*</span></label>
              <input type="email" v-model="userProfile.email" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" :class="{'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20': profileErrors.email}" />
              <p v-if="profileErrors.email" class="text-xs text-rose-500 mt-1">{{ profileErrors.email }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Số điện thoại</label>
              <input type="tel" v-model="userProfile.phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" :class="{'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20': profileErrors.phone}" />
              <p v-if="profileErrors.phone" class="text-xs text-rose-500 mt-1">{{ profileErrors.phone }}</p>
            </div>
            <div class="space-y-2 sm:col-span-2">
              <label class="block text-sm font-semibold text-slate-700">Phòng ban</label>
              <input type="text" v-model="userProfile.department" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" :class="{'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20': profileErrors.department}" />
              <p v-if="profileErrors.department" class="text-xs text-rose-500 mt-1">{{ profileErrors.department }}</p>
            </div>
            <div class="space-y-2 sm:col-span-2">
              <label class="block text-sm font-semibold text-slate-700">Giới thiệu ngắn</label>
              <textarea v-model="userProfile.bio" rows="3" maxlength="1000" class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 outline-none transition-all focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-500/10" placeholder="Kinh nghiệm, thế mạnh hoặc lĩnh vực đang phụ trách..."></textarea>
            </div>
          </div>

          <div class="rounded-xl border border-violet-100 bg-violet-50 px-4 py-3 text-sm text-violet-800">
            Vai trò hệ thống: <strong>{{ systemRoleLabels[currentUser?.role] || currentUser?.role }}</strong>. Vai trò này do quản trị viên phân quyền và không thể tự thay đổi.
          </div>

          <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
            <button class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
              Hủy
            </button>
            <button @click="saveProfile" :disabled="isSavingProfile" class="px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all disabled:opacity-50 disabled:cursor-not-allowed">
              <span v-if="isSavingProfile">Đang lưu...</span>
              <span v-else>Lưu thay đổi</span>
            </button>
          </div>
        </div>

        <!-- Notifications Tab -->
        <div v-else-if="activeTab === 'notifications'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div>
            <h2 class="text-xl font-bold text-slate-900 mb-1">Thông báo</h2>
            <p class="text-sm text-slate-500">Chọn cách bạn muốn nhận cập nhật từ RingNet.</p>
          </div>

          <div class="space-y-4">
            <label 
              v-for="item in notifications" 
              :key="item.id"
              class="flex items-start justify-between p-4 rounded-xl border border-slate-100 hover:border-violet-100 hover:bg-slate-50/50 transition-colors cursor-pointer"
            >
              <div>
                <strong class="block text-sm font-semibold text-slate-900 mb-1">{{ item.title }}</strong>
                <p class="text-sm text-slate-500">{{ item.desc }}</p>
              </div>
              <div class="relative inline-flex items-center cursor-pointer mt-1">
                <input type="checkbox" class="sr-only peer" v-model="item.checked" @change="toggleNotification(item)">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-500"></div>
              </div>
            </label>
          </div>
        </div>

        <!-- Appearance Tab -->
        <div v-else-if="activeTab === 'appearance'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div>
            <h2 class="text-xl font-bold text-slate-900 mb-1">Giao diện</h2>
            <p class="text-sm text-slate-500">Tùy chỉnh cách RingNet hiển thị với bạn.</p>
          </div>

          <div class="space-y-4">
            <h3 class="text-sm font-semibold text-slate-900">Chế độ hiển thị</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <button 
                @click="setTheme(false)"
                :class="[
                  'p-4 rounded-xl border-2 transition-all text-left',
                  !darkMode ? 'border-violet-500 bg-violet-50' : 'border-slate-100 hover:border-slate-200 bg-white'
                ]"
              >
                <div class="w-full h-24 bg-slate-100 rounded-lg mb-3 p-2 flex flex-col gap-2 border border-slate-200">
                  <div class="w-full h-4 bg-white rounded shadow-sm"></div>
                  <div class="flex gap-2 h-full">
                    <div class="w-1/3 bg-white rounded shadow-sm"></div>
                    <div class="w-2/3 bg-white rounded shadow-sm"></div>
                  </div>
                </div>
                <div class="flex items-center gap-2 font-medium" :class="!darkMode ? 'text-violet-700' : 'text-slate-700'">
                  <Sun class="w-5 h-5" /> Sáng
                  <CheckCircle2 v-if="!darkMode" class="w-5 h-5 ml-auto text-violet-500" />
                </div>
              </button>
              
              <button 
                @click="setTheme(true)"
                :class="[
                  'p-4 rounded-xl border-2 transition-all text-left',
                  darkMode ? 'border-violet-500 bg-violet-50' : 'border-slate-100 hover:border-slate-200 bg-white'
                ]"
              >
                <div class="w-full h-24 bg-slate-900 rounded-lg mb-3 p-2 flex flex-col gap-2 border border-slate-800">
                  <div class="w-full h-4 bg-slate-800 rounded shadow-sm"></div>
                  <div class="flex gap-2 h-full">
                    <div class="w-1/3 bg-slate-800 rounded shadow-sm"></div>
                    <div class="w-2/3 bg-slate-800 rounded shadow-sm"></div>
                  </div>
                </div>
                <div class="flex items-center gap-2 font-medium" :class="darkMode ? 'text-violet-700' : 'text-slate-700'">
                  <Moon class="w-5 h-5" /> Tối
                  <CheckCircle2 v-if="darkMode" class="w-5 h-5 ml-auto text-violet-500" />
                </div>
              </button>
            </div>
          </div>

          <div class="space-y-4 pt-6 border-t border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Màu chủ đạo</h3>
            <div class="flex flex-wrap gap-4">
              <button 
                v-for="color in colors" 
                :key="color.id"
                @click="toggleColor(color.id)"
                :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center transition-all',
                  color.bg,
                  activeColor === color.id ? `ring-4 ring-offset-2 ${color.ring}` : 'hover:scale-110 shadow-sm'
                ]"
              >
                <CheckCircle2 v-if="activeColor === color.id" class="w-5 h-5 text-white" />
              </button>
            </div>
          </div>
        </div>

        <!-- Security Tab -->
        <div v-else class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div>
            <div class="mb-3 inline-flex rounded-xl bg-violet-50 p-2.5 text-violet-600"><ShieldCheck class="h-6 w-6" /></div>
            <h2 class="text-xl font-bold text-slate-900 mb-1">Đổi mật khẩu</h2>
            <p class="text-sm text-slate-500">Xác nhận mật khẩu hiện tại trước khi thiết lập mật khẩu mới.</p>
          </div>

          <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
            Mật khẩu mới nên có ít nhất 8 ký tự và không dùng lại mật khẩu hiện tại. Không chia sẻ mật khẩu với người khác.
          </div>

          <form class="max-w-xl space-y-5" @submit.prevent="changePassword">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Mật khẩu hiện tại <span class="text-rose-500">*</span></label>
              <input v-model="passwordForm.current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-500/10" :class="{ 'border-rose-300': passwordErrors.current_password }" @input="passwordErrors.current_password = null" />
              <p v-if="passwordErrors.current_password" class="text-xs font-medium text-rose-600">{{ passwordErrors.current_password[0] }}</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Mật khẩu mới <span class="text-rose-500">*</span></label>
                <input v-model="passwordForm.password" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-500/10" :class="{ 'border-rose-300': passwordErrors.password }" @input="passwordErrors.password = null" />
                <p v-if="passwordErrors.password" class="text-xs font-medium text-rose-600">{{ passwordErrors.password[0] }}</p>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Xác nhận mật khẩu mới <span class="text-rose-500">*</span></label>
                <input v-model="passwordForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 outline-none transition focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-500/10" :class="{ 'border-rose-300': passwordErrors.password_confirmation }" @input="passwordErrors.password_confirmation = null" />
                <p v-if="passwordErrors.password_confirmation" class="text-xs font-medium text-rose-600">{{ passwordErrors.password_confirmation[0] }}</p>
              </div>
            </div>
            <p v-if="passwordErrors._general" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ passwordErrors._general }}</p>
            <div class="flex justify-end border-t border-slate-100 pt-5">
              <button :disabled="isChangingPassword" class="rounded-xl bg-gradient-to-r from-violet-500 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60">
                {{ isChangingPassword ? 'Đang đổi mật khẩu...' : 'Đổi mật khẩu' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

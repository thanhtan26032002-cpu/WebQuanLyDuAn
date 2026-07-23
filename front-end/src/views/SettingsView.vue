<script setup>
import { ref } from 'vue'
import { User, Bell, Palette, Moon, Sun, CheckCircle2 } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const activeTab = ref('profile')
const { darkMode, setTheme, notify } = useProjectWorkspace()

const tabs = [
  { id: 'profile', label: 'Hồ sơ', icon: User },
  { id: 'notifications', label: 'Thông báo', icon: Bell },
  { id: 'appearance', label: 'Giao diện', icon: Palette }
]

const notifications = [
  { id: 'email', title: 'Thông báo qua email', desc: 'Nhận bản tóm tắt hoạt động qua email.', checked: true },
  { id: 'push', title: 'Thông báo đẩy', desc: 'Hiển thị thông báo ngay trên thiết bị.', checked: true },
  { id: 'deadline', title: 'Nhắc hạn nhiệm vụ', desc: 'Nhắc bạn trước khi nhiệm vụ đến hạn.', checked: true },
  { id: 'mention', title: 'Khi được đề cập', desc: 'Khi đồng đội nhắc đến tên của bạn.', checked: false },
  { id: 'report', title: 'Báo cáo tiến độ', desc: 'Nhận báo cáo vào mỗi sáng thứ Hai.', checked: true }
]

const colors = [
  { id: 'violet', bg: 'bg-violet-500', ring: 'ring-violet-500' },
  { id: 'blue', bg: 'bg-blue-500', ring: 'ring-blue-500' },
  { id: 'emerald', bg: 'bg-emerald-500', ring: 'ring-emerald-500' },
  { id: 'amber', bg: 'bg-amber-500', ring: 'ring-amber-500' },
  { id: 'rose', bg: 'bg-rose-500', ring: 'ring-rose-500' }
]
const activeColor = ref('violet')

const toggleColor = (colorId) => {
  activeColor.value = colorId
  notify('Đã cập nhật màu chủ đạo')
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
            <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-violet-100 to-indigo-100 border-2 border-violet-200 flex items-center justify-center text-3xl font-bold text-violet-700 shadow-sm">
              US
            </div>
            <div>
              <div class="flex gap-3 mb-2">
                <button class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                  Thay ảnh
                </button>
                <button class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition-colors">
                  Xóa
                </button>
              </div>
              <p class="text-xs text-slate-500">JPG, GIF hoặc PNG. Tối đa 2MB.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Họ và tên</label>
              <input type="text" value="User" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" />
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Chức vụ</label>
              <input type="text" value="Quản lý dự án" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" />
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Email</label>
              <input type="email" value="user@ringnet.vn" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" />
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Số điện thoại</label>
              <input type="tel" value="(+84) 912 345 678" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none" />
            </div>
          </div>

          <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
            <button class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
              Hủy
            </button>
            <button @click="notify('Đã lưu thông tin cá nhân')" class="px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
              Lưu thay đổi
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
                <input type="checkbox" class="sr-only peer" :checked="item.checked" @change="item.checked = !item.checked">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-500"></div>
              </div>
            </label>
          </div>
        </div>

        <!-- Appearance Tab -->
        <div v-else class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
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
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { Bell, MessageSquare, AtSign, Settings, CheckCheck } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { 
  notifications, 
  notificationDropdownOpen, 
  markNotificationAsRead, 
  markAllNotificationsAsRead,
  timeAgo
} = useProjectWorkspace()

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)

const markAllRead = () => {
  markAllNotificationsAsRead()
}

const markAsRead = (id) => {
  markNotificationAsRead(id)
}

const getIcon = (type) => {
  switch (type) {
    case 'comment': return MessageSquare
    case 'mention': return AtSign
    default: return Bell
  }
}

const getColor = (type) => {
  switch (type) {
    case 'comment': return 'bg-blue-100 text-blue-600'
    case 'mention': return 'bg-violet-100 text-violet-600'
    default: return 'bg-emerald-100 text-emerald-600'
  }
}

// Click outside to close
const closeDropdown = (e) => {
  if (notificationDropdownOpen.value && !e.target.closest('#notification-container')) {
    notificationDropdownOpen.value = false
  }
}
onMounted(() => document.addEventListener('click', closeDropdown))
onUnmounted(() => document.removeEventListener('click', closeDropdown))
</script>

<template>
  <div id="notification-container" class="relative">
    <!-- Trigger Button -->
    <button 
      @click="notificationDropdownOpen = !notificationDropdownOpen"
      class="relative p-2.5 rounded-xl hover:bg-slate-100 text-slate-500 transition-colors"
      :class="{'bg-slate-100 text-violet-600': notificationDropdownOpen}"
    >
      <Bell class="w-5 h-5" />
      <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full border-2 border-white shadow-sm"></span>
    </button>

    <!-- Dropdown Panel -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0 translate-y-2"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 translate-y-2"
    >
      <div 
        v-if="notificationDropdownOpen"
        class="absolute right-0 mt-3 w-80 sm:w-96 bg-white/80 backdrop-blur-xl border border-white/40 shadow-[0_8px_30px_rgb(0,0,0,0.12)] rounded-2xl overflow-hidden z-50 origin-top-right"
      >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-100/50 flex items-center justify-between bg-white/50">
          <h3 class="font-bold text-slate-900 flex items-center gap-2">
            Thông báo
            <span v-if="unreadCount > 0" class="px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold">{{ unreadCount }} mới</span>
          </h3>
          <div class="flex items-center gap-2">
            <button @click="markAllRead" class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors" title="Đánh dấu đã đọc tất cả">
              <CheckCheck class="w-4 h-4" />
            </button>
            <button class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" title="Cài đặt">
              <Settings class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Notification List -->
        <div class="max-h-[60vh] overflow-y-auto custom-scrollbar">
          <div v-if="notifications.length === 0" class="p-8 text-center text-slate-500 text-sm">
            Không có thông báo nào.
          </div>
          
          <div 
            v-for="notif in notifications" 
            :key="notif.id"
            @click="markAsRead(notif.id)"
            class="p-4 border-b border-slate-50/50 hover:bg-white transition-colors cursor-pointer flex gap-4 relative group"
            :class="!notif.read ? 'bg-violet-50/30' : ''"
          >
            <!-- Unread Dot -->
            <div v-if="!notif.read" class="absolute left-1.5 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-violet-500"></div>
            
            <!-- Icon -->
            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm', getColor(notif.type)]">
              <component :is="getIcon(notif.type)" class="w-5 h-5" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-slate-900 mb-0.5">{{ notif.title }}</p>
              <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed mb-1.5">{{ notif.message }}</p>
              <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wide">{{ notif.createdAt ? timeAgo(notif.createdAt) : (notif.time || '') }}</span>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="p-3 bg-slate-50/50 border-t border-slate-100/50 text-center">
          <button class="text-sm font-medium text-violet-600 hover:text-violet-700 transition-colors">Xem tất cả thông báo</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
</style>

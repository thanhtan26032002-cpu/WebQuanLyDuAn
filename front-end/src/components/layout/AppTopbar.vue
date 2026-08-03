<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, Menu, X, Plus, LogOut } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'
import NotificationDropdown from './NotificationDropdown.vue'
import UserAvatar from '../common/UserAvatar.vue'

const route = useRoute()
const router = useRouter()
const { sidebarOpen, globalSearchModalOpen, openTaskModal, currentUser, logout } = useProjectWorkspace()
const searchFocused = ref(false)
const canCreateTasks = computed(() => Boolean(currentUser.value?.code))
const systemRoleLabels = { admin: 'Quản trị viên', project_manager: 'Quản lý dự án', member: 'Nhân viên' }

const openProfileSettings = () => {
  router.push({ name: 'settings', query: { tab: 'profile' } })
}
</script>

<template>
  <header class="h-16 sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-100 px-4 sm:px-6 flex items-center justify-between gap-4 shrink-0">
    <!-- Left: hamburger + search -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
      <!-- Mobile hamburger -->
      <button 
        class="lg:hidden text-slate-500 hover:text-slate-900 p-1 rounded-lg hover:bg-slate-100 transition-colors shrink-0"
        @click="sidebarOpen = !sidebarOpen"
      >
        <component :is="sidebarOpen ? X : Menu" class="w-5 h-5" />
      </button>
      
      <!-- Search -->
      <div 
        class="hidden md:flex items-center relative max-w-sm w-full transition-all duration-200"
        :class="searchFocused ? 'max-w-md' : 'max-w-sm'"
      >
        <Search class="w-4 h-4 absolute left-3 text-slate-400 pointer-events-none" />
        <input 
          type="text" 
          placeholder="Tìm kiếm nhanh (Ctrl + K)..." 
          class="w-full bg-slate-50 text-slate-900 placeholder:text-slate-400 rounded-full pl-9 pr-4 py-2 text-sm border border-transparent focus:border-violet-200 focus:bg-white focus:ring-4 focus:ring-violet-500/10 transition-all outline-none cursor-pointer"
          readonly
          @click="globalSearchModalOpen = true"
        />
      </div>
    </div>

    <!-- Right: actions -->
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <!-- Quick add task -->
      <button 
        @click="openTaskModal(route.name === 'project-detail' ? route.params.id : '')"
        v-if="canCreateTasks"
        class="hidden sm:flex items-center gap-1.5 text-xs font-semibold bg-violet-50 text-violet-700 border border-violet-100 px-3 py-2 rounded-lg hover:bg-violet-100 transition-colors"
      >
        <Plus class="w-3.5 h-3.5" /> Nhiệm vụ
      </button>

      <!-- Notifications & Profile -->
      <div class="flex items-center gap-4">
        <!-- Notification Dropdown -->
        <NotificationDropdown />
        
        <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>

        <!-- Avatar -->
        <button
          type="button"
          title="Mở hồ sơ cá nhân"
          aria-label="Mở hồ sơ cá nhân"
          class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
          @click="openProfileSettings"
        >
          <div class="hidden sm:block text-right">
            <p class="text-xs font-semibold text-slate-900 leading-none">{{ currentUser && currentUser.name || 'User' }}</p>
            <p class="text-[10px] text-slate-500 leading-none mt-1">{{ systemRoleLabels[currentUser?.role] || 'Nhân viên' }}</p>
          </div>
          <UserAvatar v-if="currentUser?.code" :member-id="currentUser.code" size="sm" :show-popover="false" />
        </button>
        <button type="button" title="Đăng xuất" aria-label="Đăng xuất" class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" @click="logout">
          <LogOut class="h-4 w-4" />
        </button>
      </div>
    </div>
  </header>
</template>

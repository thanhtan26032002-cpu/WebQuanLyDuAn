<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Menu, X, Plus } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'
import NotificationDropdown from './NotificationDropdown.vue'

const route = useRoute()
const { sidebarOpen, globalSearchModalOpen, taskModalOpen, openTaskModal, toastMessage } = useProjectWorkspace()
const searchFocused = ref(false)
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
        <button class="flex items-center gap-2.5 hover:opacity-80 transition-opacity">
          <div class="hidden sm:block text-right">
            <p class="text-xs font-semibold text-slate-900 leading-none">User</p>
            <p class="text-[10px] text-slate-500 leading-none mt-1">Quản lý dự án</p>
          </div>
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold shadow-sm">
            US
          </div>
        </button>
      </div>
    </div>
  </header>
</template>

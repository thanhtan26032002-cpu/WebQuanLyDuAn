<script setup>
import { useRoute, useRouter } from "vue-router";
import { computed } from "vue";
import {
  LayoutDashboard,
  UserRoundCheck,
  ChartNoAxesCombined,
  FolderKanban,
  CheckSquare,
  Users,
  Calendar as CalendarIcon,
  Trash2,
  Settings,
  Plus,
} from "@lucide/vue";
import { useProjectWorkspace } from "../../composables/useProjectWorkspace";

const route = useRoute();
const router = useRouter();
const { projectModalOpen, tasks, currentUser } = useProjectWorkspace();
const canManageWorkspace = computed(() => ['admin', 'project_manager', 'manager'].includes(currentUser.value?.role));

// Badge for Tasks that are not done
const pendingTasksCount = computed(
  () => tasks.value.filter((t) => t.status !== "done").length,
);

const navigation = computed(() => [
  { name: 'C\u00f4ng vi\u1ec7c c\u1ee7a t\u00f4i', href: '/my-work', icon: UserRoundCheck },
  { name: "Tổng quan", href: "/", icon: LayoutDashboard },
  { name: "Dự án", href: "/projects", icon: FolderKanban },
  {
    name: "Nhiệm vụ",
    href: "/tasks",
    icon: CheckSquare,
    badge: pendingTasksCount.value,
  },
  { name: "Nhóm", href: "/team", icon: Users },
  { name: "Lịch", href: "/calendar", icon: CalendarIcon },
  ...(currentUser.value && ['admin', 'project_manager', 'manager'].includes(currentUser.value.role)
    ? [{ name: 'B\u00e1o c\u00e1o', href: '/reports', icon: ChartNoAxesCombined }]
    : []),
]);

const isActive = (path) => {
  if (path === "/") return route.path === "/";
  return route.path.startsWith(path);
};

const goHomeAndReload = () => {
  if (window.location.pathname === "/") {
    window.location.reload();
  } else {
    window.location.href = "/";
  }
};
</script>

<template>
  <aside class="w-64 h-full flex flex-col bg-white border-r border-slate-100">
    <!-- Logo -->
    <div
      @click="goHomeAndReload"
      class="h-16 flex items-center px-5 border-b border-slate-100 shrink-0 cursor-pointer hover:bg-slate-50/80 transition-colors select-none group"
    >
      <div
        class="w-8 h-8 rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center mr-3 shadow-md shadow-violet-500/30 group-hover:scale-105 transition-transform duration-200"
      >
        <div class="w-3 h-3 bg-white rounded-sm rotate-12"></div>
      </div>
      <div>
        <span
          class="text-lg font-bold text-slate-900 leading-none group-hover:text-violet-600 transition-colors"
          >RingNet</span
        >
        <span
          class="block text-[10px] text-slate-400 font-medium tracking-wide leading-none mt-0.5"
          >Quản lý dự án</span
        >
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
      <router-link
        v-for="item in navigation"
        :key="item.name"
        :to="item.href"
        class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200"
        :class="
          isActive(item.href)
            ? 'bg-violet-50 text-violet-700'
            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
        "
      >
        <!-- Active indicator bar -->
        <div
          v-if="isActive(item.href)"
          class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-violet-500 rounded-r-full"
        ></div>

        <component
          :is="item.icon"
          class="w-5 h-5 shrink-0"
          :class="isActive(item.href) ? 'text-violet-600' : 'text-slate-400'"
        />
        <span class="flex-1">{{ item.name }}</span>

        <!-- Badge -->
        <span
          v-if="item.badge"
          :class="[
            'px-2 py-0.5 rounded-full text-xs font-bold',
            isActive(item.href)
              ? 'bg-violet-200 text-violet-700'
              : 'bg-slate-100 text-slate-500',
          ]"
        >
          {{ item.badge }}
        </span>
      </router-link>
    </nav>

    <!-- Add Project Button -->
    <div v-if="canManageWorkspace" class="px-3 pb-3">
      <button
        @click="projectModalOpen = true"
        class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-violet-500 to-indigo-600 text-white py-2.5 rounded-xl font-semibold text-sm hover:shadow-lg hover:shadow-violet-500/25 transition-all duration-300 active:scale-95"
      >
        <Plus class="w-4 h-4" /> Thêm dự án
      </button>
    </div>

    <!-- Settings -->
    <div class="px-3 pb-4 border-t border-slate-50 pt-3">
      <router-link
        to="/trash"
        class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all"
        :class="isActive('/trash') ? 'bg-violet-50 text-violet-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'"
      >
        <Trash2 class="h-5 w-5" /> Thùng rác
      </router-link>
      <router-link
        to="/settings"
        class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200"
        :class="
          isActive('/settings')
            ? 'bg-violet-50 text-violet-700'
            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
        "
      >
        <div
          v-if="isActive('/settings')"
          class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-violet-500 rounded-r-full"
        ></div>
        <Settings
          class="w-5 h-5"
          :class="isActive('/settings') ? 'text-violet-600' : 'text-slate-400'"
        />
        Cài đặt
      </router-link>
    </div>
  </aside>
</template>

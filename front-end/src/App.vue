<script setup>
import { useRoute } from "vue-router";
import AppSidebar from "./components/layout/AppSidebar.vue";
import AppTopbar from "./components/layout/AppTopbar.vue";
import ProjectFormModal from "./components/modals/ProjectFormModal.vue";
import TaskFormModal from "./components/modals/TaskFormModal.vue";
import ProjectSettingsModal from "./components/modals/ProjectSettingsModal.vue";
import FileUploadModal from "./components/modals/FileUploadModal.vue";
import ManageProjectMembersModal from "./components/modals/ManageProjectMembersModal.vue";
import GlobalSearchModal from "./components/modals/GlobalSearchModal.vue";
import ImportProjectModal from "./components/modals/ImportProjectModal.vue";
import ToastNotification from "./components/common/ToastNotification.vue";
import TaskDetailPanel from "./components/common/TaskDetailPanel.vue";
import AddMemberModal from "./components/common/AddMemberModal.vue";
import AddGroupModal from "./components/common/AddGroupModal.vue";
import EditGroupModal from "./components/common/EditGroupModal.vue";
import MemberDetailModal from "./components/common/MemberDetailModal.vue";
import { useProjectWorkspace } from "./composables/useProjectWorkspace";

const route = useRoute();
const { projectModalOpen, taskModalOpen, toastMessage, sidebarOpen, apiConnectionError } =
  useProjectWorkspace();
</script>

<template>
  <div
    class="h-screen bg-slate-50 flex overflow-hidden font-sans text-slate-900 selection:bg-violet-200 selection:text-violet-900"
  >
    <!-- Desktop Sidebar -->
    <div class="hidden lg:block shrink-0">
      <AppSidebar />
    </div>

    <!-- Mobile Sidebar Backdrop -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 lg:hidden transition-opacity"
      @click="sidebarOpen = false"
    ></div>

    <!-- Mobile Sidebar -->
    <div
      class="fixed inset-y-0 left-0 z-50 w-64 transform transition-transform duration-300 lg:hidden"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <AppSidebar />
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
      <!-- Topbar -->
      <AppTopbar />

      <div
        v-if="apiConnectionError"
        class="mx-4 mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 sm:mx-6 lg:mx-8"
        role="alert"
      >
        {{ apiConnectionError }}
      </div>

      <!-- Main Scrollable Content -->
      <main
        class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 relative"
      >
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
          <router-view v-slot="{ Component }">
            <component :is="Component" :key="route.path" />
          </router-view>
        </div>
      </main>

      <!-- Modals & Panels -->
      <ProjectFormModal v-if="projectModalOpen" />
      <ProjectSettingsModal />
      <TaskFormModal v-if="taskModalOpen" />
      <TaskDetailPanel />
      <FileUploadModal />
      <AddGroupModal />
      <EditGroupModal />
      <AddMemberModal />
      <MemberDetailModal />
      <ManageProjectMembersModal />
      <GlobalSearchModal />
      <ImportProjectModal />

      <!-- Toast Notification -->
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="transform translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="transform translate-y-0 opacity-100 sm:translate-x-0"
        leave-to-class="transform translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      >
        <div
          v-if="toastMessage"
          class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50"
        >
          <ToastNotification :message="toastMessage" />
        </div>
      </Transition>
    </div>
  </div>
</template>

<style>
/* Global Transitions */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.25s ease;
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ArchiveRestore, FolderKanban, ListChecks, RotateCcw, Trash2 } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const {
  deletedProjects,
  deletedTasks,
  loadTrash,
  restoreProject,
  restoreTask,
  formatDateTime,
} = useProjectWorkspace()

const restoringId = ref(null)
const totalItems = computed(() => deletedProjects.value.length + deletedTasks.value.length)

let trashRefreshTimer

onMounted(async () => {
  await loadTrash()
  trashRefreshTimer = window.setInterval(loadTrash, 60000)
})

onUnmounted(() => window.clearInterval(trashRefreshTimer))

const remainingLabel = (item) => {
  if (!item.canRestore) return 'Đã quá hạn khôi phục'
  const milliseconds = new Date(item.restoreUntil).getTime() - Date.now()
  const days = Math.max(0, Math.ceil(milliseconds / 86400000))
  return `Còn ${days} ngày để khôi phục`
}

const restoreDisabledLabel = (item) => {
  if (!item.canRestore) return 'Đã quá 30 ngày'
  if (!item.canRestoreByUser) return 'Bạn chỉ có quyền xem mục này'
  return ''
}

const restore = async (type, id) => {
  restoringId.value = `${type}-${id}`
  if (type === 'project') await restoreProject(id)
  else await restoreTask(id)
  restoringId.value = null
}
</script>

<template>
  <div class="space-y-8 pb-12 max-w-6xl mx-auto">
    <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <div class="w-11 h-11 rounded-2xl bg-violet-100 text-violet-600 flex items-center justify-center">
            <Trash2 class="w-5 h-5" />
          </div>
          <h1 class="text-3xl font-bold text-slate-900">Thùng rác</h1>
        </div>
        <p class="text-sm text-slate-500">Dữ liệu đã xóa vẫn được giữ trong cơ sở dữ liệu. Các mục quá 30 ngày sẽ tự ẩn khỏi đây và không thể khôi phục.</p>
      </div>
      <span class="w-fit px-3 py-1.5 rounded-full bg-slate-100 text-sm font-semibold text-slate-600">{{ totalItems }} mục</span>
    </header>

    <div v-if="!totalItems" class="bg-white border border-slate-100 rounded-2xl py-16 text-center shadow-sm">
      <ArchiveRestore class="w-12 h-12 mx-auto mb-4 text-slate-300" />
      <h2 class="text-lg font-bold text-slate-800">Thùng rác đang trống</h2>
      <p class="text-sm text-slate-500 mt-1">Dự án và nhiệm vụ đã xóa sẽ xuất hiện tại đây.</p>
    </div>

    <section v-if="deletedProjects.length" class="space-y-3">
      <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><FolderKanban class="w-5 h-5 text-violet-500" /> Dự án</h2>
      <div class="grid gap-3">
        <article v-for="project in deletedProjects" :key="project.id" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center gap-4">
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-slate-900 truncate">{{ project.name }}</h3>
            <p class="text-sm text-slate-500 mt-1">Đã xóa {{ formatDateTime(project.deletedAt) }} · {{ project.tasks_count || 0 }} nhiệm vụ đang hoạt động</p>
          </div>
          <div class="md:text-right">
            <p :class="['text-xs font-semibold mb-2', project.canRestore ? 'text-emerald-600' : 'text-rose-600']">{{ remainingLabel(project) }}</p>
            <button @click="restore('project', project.id)" :title="restoreDisabledLabel(project)" :disabled="!project.canRestore || !project.canRestoreByUser || restoringId === `project-${project.id}`" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 text-white hover:bg-violet-700 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed">
              <RotateCcw class="w-4 h-4" /> {{ restoringId === `project-${project.id}` ? 'Đang khôi phục...' : 'Khôi phục' }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <section v-if="deletedTasks.length" class="space-y-3">
      <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900"><ListChecks class="w-5 h-5 text-emerald-500" /> Nhiệm vụ</h2>
      <div class="grid gap-3">
        <article v-for="task in deletedTasks" :key="task.id" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center gap-4">
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-slate-900 truncate">{{ task.title }}</h3>
            <p class="text-sm text-slate-500 mt-1">{{ task.project?.name || 'Không thuộc dự án' }} · Đã xóa {{ formatDateTime(task.deletedAt) }}</p>
          </div>
          <div class="md:text-right">
            <p :class="['text-xs font-semibold mb-2', task.canRestore ? 'text-emerald-600' : 'text-rose-600']">{{ remainingLabel(task) }}</p>
            <button @click="restore('task', task.id)" :title="restoreDisabledLabel(task)" :disabled="!task.canRestore || !task.canRestoreByUser || restoringId === `task-${task.id}`" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 text-white hover:bg-violet-700 disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed">
              <RotateCcw class="w-4 h-4" /> {{ restoringId === `task-${task.id}` ? 'Đang khôi phục...' : 'Khôi phục' }}
            </button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, Ban, CalendarClock, CircleUserRound, RefreshCw } from '@lucide/vue'
import { apiFetch, parseApiError } from '../services/api'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const { activeTaskId, formatDate } = useProjectWorkspace()
const loading = ref(true)
const error = ref('')
const data = ref({ overdue: [], today: [], upcoming: [], blocked: [], unassigned: [] })

const sections = computed(() => [
  { id: 'overdue', title: 'Quá hạn', icon: AlertTriangle, tone: 'rose', items: data.value.overdue },
  { id: 'today', title: 'Hôm nay', icon: CalendarClock, tone: 'violet', items: data.value.today },
  { id: 'blocked', title: 'Đang bị chặn', icon: Ban, tone: 'amber', items: data.value.blocked },
  { id: 'upcoming', title: '7 ngày tới', icon: CalendarClock, tone: 'blue', items: data.value.upcoming },
  { id: 'unassigned', title: 'Chưa phân công', icon: CircleUserRound, tone: 'slate', items: data.value.unassigned },
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await apiFetch('/api/my-work')
    if (!response.ok) throw new Error(await parseApiError(response))
    data.value = await response.json()
  } catch (loadError) {
    error.value = loadError.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-7 p-5 sm:p-7">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Không gian cá nhân</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Công việc của tôi</h1>
        <p class="mt-1 text-sm text-slate-500">Ưu tiên những việc cần chú ý ngay hôm nay.</p>
      </div>
      <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-violet-200 hover:text-violet-700" @click="load">
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> Làm mới
      </button>
    </header>

    <div v-if="error" role="alert" class="flex items-center justify-between rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      <span>{{ error }}</span><button class="font-bold underline" @click="load">Thử lại</button>
    </div>

    <div v-if="loading" class="grid gap-5 lg:grid-cols-2">
      <div v-for="index in 4" :key="index" class="h-52 animate-pulse rounded-2xl bg-white shadow-sm"></div>
    </div>

    <div v-else class="grid items-start gap-5 lg:grid-cols-2">
      <section v-for="section in sections" :key="section.id" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div class="flex items-center gap-2.5"><component :is="section.icon" class="h-5 w-5 text-violet-600" /><h2 class="font-bold">{{ section.title }}</h2></div>
          <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ section.items.length }}</span>
        </header>
        <div v-if="section.items.length" class="divide-y divide-slate-100">
          <button v-for="task in section.items" :key="task.code" type="button" class="flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50" @click="activeTaskId = task.code">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="task.priority === 'high' ? 'bg-rose-500' : task.priority === 'medium' ? 'bg-amber-400' : 'bg-emerald-500'"></span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold text-slate-800">{{ task.title }}</span>
              <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                <span>{{ task.project?.name || 'Nhiệm vụ độc lập' }}</span><span v-if="task.due_date">{{ formatDate(task.due_date) }}</span>
              </span>
            </span>
            <span v-if="task.is_blocked" class="rounded-md bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">BỊ CHẶN</span>
          </button>
        </div>
        <div v-else class="px-5 py-9 text-center text-sm text-slate-400">Không có công việc trong nhóm này.</div>
      </section>
    </div>
  </div>
</template>

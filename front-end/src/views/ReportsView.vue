<script setup>
import { computed, onMounted, ref } from 'vue'
import { Activity, AlertTriangle, Clock3, RefreshCw, UsersRound } from '@lucide/vue'
import { apiFetch, parseApiError } from '../services/api'

const report = ref(null)
const loading = ref(true)
const error = ref('')
const maxCompleted = computed(() => Math.max(1, ...(report.value?.completed_by_week || []).map(item => item.count)))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await apiFetch('/api/reports')
    if (!response.ok) throw new Error(await parseApiError(response, 'Không thể tải báo cáo.'))
    report.value = await response.json()
  } catch (loadError) {
    error.value = loadError.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 p-5 sm:p-7">
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div><p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">Điều hành</p><h1 class="mt-1 text-3xl font-bold">Báo cáo</h1><p class="mt-1 text-sm text-slate-500">Các chỉ số giúp phát hiện chậm trễ và quá tải.</p></div>
      <button class="flex items-center gap-2 rounded-xl border bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:text-violet-700" @click="load"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> Làm mới</button>
    </header>

    <div v-if="error" role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">{{ error }}</div>
    <div v-if="loading" class="grid gap-5 sm:grid-cols-3"><div v-for="i in 6" :key="i" class="h-36 animate-pulse rounded-2xl bg-white"></div></div>

    <template v-else-if="report">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm"><AlertTriangle class="h-5 w-5 text-rose-500" /><p class="mt-4 text-3xl font-bold">{{ report.overdue.total }}</p><p class="text-sm text-slate-500">Nhiệm vụ quá hạn</p></article>
        <article class="rounded-2xl border border-orange-100 bg-white p-5 shadow-sm"><AlertTriangle class="h-5 w-5 text-orange-500" /><p class="mt-4 text-3xl font-bold">{{ report.overdue_projects?.total || 0 }}</p><p class="text-sm text-slate-500">Dự án quá hạn</p></article>
        <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm"><Clock3 class="h-5 w-5 text-amber-600" /><p class="mt-4 text-3xl font-bold">{{ report.late_completion?.tasks?.total || 0 }}</p><p class="text-sm text-slate-500">Nhiệm vụ hoàn thành trễ</p><p class="mt-1 text-xs text-slate-400">{{ report.late_completion?.tasks?.rate || 0 }}% việc có hạn đã hoàn thành</p></article>
        <article class="rounded-2xl border border-fuchsia-100 bg-white p-5 shadow-sm"><Clock3 class="h-5 w-5 text-fuchsia-600" /><p class="mt-4 text-3xl font-bold">{{ report.late_completion?.projects?.total || 0 }}</p><p class="text-sm text-slate-500">Dự án hoàn thành trễ</p><p class="mt-1 text-xs text-slate-400">{{ report.late_completion?.projects?.rate || 0 }}% dự án có hạn đã hoàn thành</p></article>
        <article class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm"><Clock3 class="h-5 w-5 text-violet-500" /><p class="mt-4 text-3xl font-bold">{{ report.average_cycle_hours }}h</p><p class="text-sm text-slate-500">Chu kỳ hoàn thành trung bình</p></article>
        <article class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm"><UsersRound class="h-5 w-5 text-blue-500" /><p class="mt-4 text-3xl font-bold">{{ report.workload.length }}</p><p class="text-sm text-slate-500">Thành viên có việc đang mở</p></article>
      </section>

      <section class="grid items-start gap-5 xl:grid-cols-2">
        <article class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm">
          <h2 class="font-bold text-slate-900">Dự án đang quá hạn</h2>
          <p class="mt-1 text-xs text-slate-500">Sắp xếp theo số ngày quá hạn giảm dần.</p>
          <div class="mt-4 space-y-2">
            <div v-for="project in report.overdue_projects?.items || []" :key="project.code" class="flex items-center justify-between gap-4 rounded-xl bg-rose-50/70 p-3">
              <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ project.name }}</p><p class="text-xs text-slate-500">{{ project.manager?.name || 'Chưa phân công quản lý' }} · {{ project.progress || 0 }}%</p></div>
              <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700">{{ project.overdue_days }} ngày</span>
            </div>
            <p v-if="!report.overdue_projects?.items?.length" class="py-8 text-center text-sm text-slate-400">Không có dự án quá hạn.</p>
          </div>
        </article>

        <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
          <h2 class="font-bold text-slate-900">Nhiệm vụ hoàn thành trễ</h2>
          <p class="mt-1 text-xs text-slate-500">Trung bình trễ {{ report.late_completion?.tasks?.average_late_days || 0 }} ngày.</p>
          <div class="mt-4 space-y-2">
            <div v-for="task in report.late_completion?.tasks?.items || []" :key="task.code" class="flex items-center justify-between gap-4 rounded-xl bg-amber-50/70 p-3">
              <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ task.title }}</p><p class="truncate text-xs text-slate-500">{{ task.project?.name || 'Nhiệm vụ độc lập' }} · {{ task.assignee?.name || 'Chưa phân công' }}</p></div>
              <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Trễ {{ task.late_days }} ngày</span>
            </div>
            <p v-if="!report.late_completion?.tasks?.items?.length" class="py-8 text-center text-sm text-slate-400">Chưa có nhiệm vụ hoàn thành trễ.</p>
          </div>
        </article>
      </section>

      <section class="grid items-start gap-5 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h2 class="flex items-center gap-2 font-bold"><Activity class="h-5 w-5 text-violet-500" /> Hoàn thành theo tuần</h2>
          <div class="mt-6 flex h-52 items-end gap-3">
            <div v-for="item in report.completed_by_week" :key="item.label" class="flex h-full flex-1 flex-col justify-end text-center">
              <span class="mb-1 text-xs font-bold text-slate-600">{{ item.count }}</span>
              <div class="min-h-1 rounded-t-lg bg-gradient-to-t from-violet-600 to-indigo-400" :style="{ height: `${Math.max(3, item.count / maxCompleted * 100)}%` }"></div>
              <span class="mt-2 text-[10px] font-medium text-slate-400">{{ item.label }}</span>
            </div>
          </div>
        </article>

        <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h2 class="font-bold">Khối lượng theo thành viên</h2>
          <div class="mt-5 space-y-5">
            <div v-for="item in report.workload" :key="item.member?.code">
              <div class="mb-2 flex justify-between text-sm"><span class="font-semibold">{{ item.member?.name || 'Chưa xác định' }}</span><span :class="item.load_percent > 100 ? 'font-bold text-rose-600' : 'text-slate-500'">{{ item.estimated_hours }}/{{ item.capacity_hours }}h</span></div>
              <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="item.load_percent > 100 ? 'bg-rose-500' : item.load_percent > 80 ? 'bg-amber-400' : 'bg-emerald-500'" :style="{ width: `${Math.min(100, item.load_percent)}%` }"></div></div>
            </div>
            <p v-if="!report.workload.length" class="py-8 text-center text-sm text-slate-400">Chưa có dữ liệu workload.</p>
          </div>
        </article>
      </section>

      <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <h2 class="font-bold">Ước tính so với thực tế</h2>
        <div class="mt-4 overflow-x-auto">
          <table class="w-full min-w-[620px] text-left text-sm"><thead class="text-xs uppercase text-slate-400"><tr><th class="pb-3">Nhiệm vụ</th><th class="pb-3">Ước tính</th><th class="pb-3">Thực tế</th><th class="pb-3">Chênh lệch</th></tr></thead><tbody class="divide-y"><tr v-for="item in report.estimate_vs_actual.slice(0, 15)" :key="item.task_code"><td class="py-3 font-medium">{{ item.title }}</td><td>{{ item.estimated_hours }}h</td><td>{{ item.actual_hours }}h</td><td :class="item.actual_hours > item.estimated_hours ? 'font-bold text-rose-600' : 'text-emerald-600'">{{ Math.round((item.actual_hours - item.estimated_hours) * 10) / 10 }}h</td></tr></tbody></table>
        </div>
      </section>
    </template>
  </div>
</template>

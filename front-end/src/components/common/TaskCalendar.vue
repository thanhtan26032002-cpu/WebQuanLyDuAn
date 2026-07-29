<script setup>
import { computed, ref } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight, CheckCircle2, Clock3 } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const props = defineProps({ tasks: { type: Array, default: () => [] }, showProject: { type: Boolean, default: true } })
const emit = defineEmits(['select'])
const { findProject, findMember } = useProjectWorkspace()
const mode = ref('month')
const viewDate = ref(new Date())

const cloneDate = date => new Date(date.getFullYear(), date.getMonth(), date.getDate())
const addDays = (date, amount) => { const next = cloneDate(date); next.setDate(next.getDate() + amount); return next }
const startOfWeek = date => addDays(date, -((date.getDay() + 6) % 7))
const dateKey = date => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
const parseDate = value => {
  if (!value) return null
  const [year, month, day] = String(value).split('T')[0].split('-').map(Number)
  return year && month && day ? new Date(year, month - 1, day) : null
}
const dayDiff = (from, to) => Math.round((cloneDate(to) - cloneDate(from)) / 86400000)

function taskRange(task) {
  let start = parseDate(task.startDate || task.dueDate)
  let end = parseDate(task.dueDate || task.startDate)
  if (!start || !end) return null
  if (start > end) [start, end] = [end, start]
  return { task, start, end }
}

function buildWeeks(calendarStart, count, currentMonth = null) {
  const today = dateKey(new Date())
  const ranges = props.tasks.map(taskRange).filter(Boolean)
  return Array.from({ length: count }, (_, weekIndex) => {
    const weekStart = addDays(calendarStart, weekIndex * 7)
    const weekEnd = addDays(weekStart, 6)
    const lanes = []
    const segments = ranges
      .filter(item => item.start <= weekEnd && item.end >= weekStart)
      .sort((a, b) => a.start - b.start || b.end - a.end)
      .map(item => {
        const clippedStart = item.start < weekStart ? weekStart : item.start
        const clippedEnd = item.end > weekEnd ? weekEnd : item.end
        const colStart = dayDiff(weekStart, clippedStart) + 1
        const colEnd = dayDiff(weekStart, clippedEnd) + 1
        let lane = lanes.findIndex(lastEnd => lastEnd < colStart)
        if (lane < 0) { lane = lanes.length; lanes.push(colEnd) } else lanes[lane] = colEnd
        return { ...item.task, colStart, span: colEnd - colStart + 1, lane, continuesBefore: item.start < weekStart, continuesAfter: item.end > weekEnd }
      })
    const days = Array.from({ length: 7 }, (_, index) => {
      const date = addDays(weekStart, index)
      return { key: dateKey(date), number: date.getDate(), month: date.getMonth(), current: currentMonth === null || date.getMonth() === currentMonth, today: dateKey(date) === today }
    })
    return { key: dateKey(weekStart), days, segments, laneCount: Math.max(lanes.length, mode.value === 'week' ? 5 : 2) }
  })
}

const visibleWeeks = computed(() => {
  if (mode.value === 'week') return buildWeeks(startOfWeek(viewDate.value), 1)
  const first = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1)
  return buildWeeks(startOfWeek(first), 6, viewDate.value.getMonth())
})

const calendarTitle = computed(() => {
  if (mode.value === 'month') return new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(viewDate.value)
  const start = startOfWeek(viewDate.value)
  const end = addDays(start, 6)
  return `${new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(start)} – ${new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(end)}`
})

const taskClass = task => ({ high: 'bg-rose-500 text-white border-rose-600', medium: 'bg-amber-400 text-amber-950 border-amber-500', low: 'bg-sky-500 text-white border-sky-600' }[task.priority] || 'bg-violet-500 text-white border-violet-600')
const navigate = offset => { viewDate.value = mode.value === 'month' ? new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + offset, 1) : addDays(viewDate.value, offset * 7) }
const resetToday = () => { viewDate.value = new Date() }
</script>

<template>
  <div class="max-h-[75vh] overflow-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="sticky top-0 z-40 flex min-w-[820px] items-center justify-between gap-4 border-b border-slate-100 bg-white p-4">
      <div class="flex items-center gap-3">
        <div class="flex rounded-xl border border-slate-200 bg-slate-50 p-1">
          <button @click="navigate(-1)" class="rounded-lg p-1.5 text-slate-500 hover:bg-white hover:text-slate-900 hover:shadow-sm" title="Trước"><ChevronLeft class="h-4 w-4" /></button>
          <button @click="navigate(1)" class="rounded-lg p-1.5 text-slate-500 hover:bg-white hover:text-slate-900 hover:shadow-sm" title="Sau"><ChevronRight class="h-4 w-4" /></button>
        </div>
        <button @click="resetToday" class="flex items-center gap-1.5 rounded-xl bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100"><CalendarDays class="h-3.5 w-3.5" />Hôm nay</button>
        <h3 class="text-lg font-bold capitalize text-slate-900">{{ calendarTitle }}</h3>
      </div>
      <div class="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-1">
        <button @click="mode = 'week'" :class="['rounded-lg px-3 py-1.5 text-xs font-bold transition-all', mode === 'week' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500']">Tuần</button>
        <button @click="mode = 'month'" :class="['rounded-lg px-3 py-1.5 text-xs font-bold transition-all', mode === 'month' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500']">Tháng</button>
      </div>
    </div>
    <div class="sticky top-[65px] z-30 grid min-w-[820px] grid-cols-7 border-b border-slate-200 bg-slate-50/95 backdrop-blur-sm">
      <div v-for="label in ['T2','T3','T4','T5','T6','T7','CN']" :key="label" class="border-r border-slate-200 py-2.5 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500 last:border-r-0">{{ label }}</div>
    </div>
    <div class="min-w-[820px] divide-y divide-slate-200">
      <div
        v-for="week in visibleWeeks"
        :key="week.key"
        :class="['relative grid grid-cols-7 overflow-hidden bg-white', mode === 'week' ? 'min-h-[470px]' : 'min-h-[142px]']"
        :style="{ gridTemplateRows: mode === 'week' ? `68px repeat(${week.laneCount}, 44px) minmax(140px, 1fr)` : `40px repeat(${week.laneCount}, 34px) minmax(12px, 1fr)` }"
      >
        <div
          v-for="(day, index) in week.days"
          :key="day.key"
          :class="['relative border-r border-slate-200 px-2 pt-2 last:border-r-0', !day.current && mode === 'month' ? 'bg-slate-50/70' : 'bg-white']"
          :style="{ gridColumn: index + 1, gridRow: '1 / -1' }"
        >
          <div :class="['flex items-center gap-2', mode === 'week' && 'flex-col items-start']">
            <span v-if="mode === 'week'" class="text-[10px] font-bold uppercase text-slate-400">{{ ['T2','T3','T4','T5','T6','T7','CN'][index] }}</span>
            <span :class="['flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold', day.today ? 'bg-violet-600 text-white shadow-sm' : day.current ? 'text-slate-700' : 'text-slate-300']">{{ day.number }}</span>
          </div>
        </div>

        <button
          v-for="segment in week.segments"
          :key="`${week.key}-${segment.id}`"
          @click="emit('select', segment)"
          :class="['z-10 mx-1 flex min-w-0 items-center gap-1.5 self-center overflow-hidden border px-2 text-left text-[11px] font-bold shadow-sm transition-all hover:-translate-y-px hover:brightness-95 hover:shadow-md', taskClass(segment), segment.continuesBefore ? 'rounded-l-sm border-l-4' : 'rounded-l-lg', segment.continuesAfter ? 'rounded-r-sm border-r-4' : 'rounded-r-lg', mode === 'week' ? 'h-10' : 'h-7']"
          :style="{ gridColumn: `${segment.colStart} / span ${segment.span}`, gridRow: segment.lane + 2 }"
          :title="`${segment.title} · ${findMember(segment.assigneeId).name} · ${segment.progress || 0}% · ${segment.startDate || segment.dueDate} → ${segment.dueDate || segment.startDate}`"
        >
          <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/75 text-[8px] font-black text-slate-700">{{ findMember(segment.assigneeId).initials }}</span>
          <span class="min-w-0 flex-1 truncate">{{ segment.title }}</span>
          <span v-if="showProject && mode === 'week'" class="max-w-[28%] truncate text-[9px] font-medium opacity-75">{{ findProject(segment.projectId)?.name }}</span>
          <span v-if="segment.estimatedHours && (mode === 'week' || segment.span > 1)" class="flex shrink-0 items-center gap-0.5 text-[9px] font-bold opacity-80"><Clock3 class="h-3 w-3" />{{ segment.estimatedHours }}h</span>
          <CheckCircle2 v-if="segment.status === 'done'" class="h-3.5 w-3.5 shrink-0" />
          <span v-else-if="segment.progress" class="shrink-0 rounded bg-white/30 px-1 py-0.5 text-[8px] font-black">{{ segment.progress }}%</span>
        </button>
      </div>
    </div>
  </div>
</template>

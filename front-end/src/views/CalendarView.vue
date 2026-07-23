<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon, FolderKanban, CheckSquare } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const router = useRouter()
const viewDate = ref(new Date())
const { projects, tasks, activeTaskId } = useProjectWorkspace()

const calendarTitle = computed(() => {
  const month = viewDate.value.toLocaleString('vi-VN', { month: 'long' })
  const year = viewDate.value.getFullYear()
  return `Tháng ${month.replace('tháng ', '')} ${year}`
})

const events = computed(() => [
  ...projects.value.map((project) => ({ id: project.id, date: project.dueDate, text: project.name, type: 'project', color: project.color })),
  ...tasks.value.map((task) => ({ id: task.id, date: task.dueDate, text: task.title, type: 'task', priority: task.priority })),
])

const calendarDays = computed(() => {
  const year = viewDate.value.getFullYear()
  const month = viewDate.value.getMonth()
  
  let firstDay = new Date(year, month, 1).getDay() - 1
  if (firstDay === -1) firstDay = 6
  
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const previousMonthDays = new Date(year, month, 0).getDate()
  
  const today = new Date()
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

  return Array.from({ length: 42 }, (_, index) => {
    const relativeDay = index - firstDay + 1
    const isCurrent = relativeDay > 0 && relativeDay <= daysInMonth
    
    let date
    if (isCurrent) {
      date = new Date(year, month, relativeDay)
    } else if (relativeDay <= 0) {
      date = new Date(year, month - 1, previousMonthDays + relativeDay)
    } else {
      date = new Date(year, month + 1, relativeDay - daysInMonth)
    }
    
    const isoDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    const number = date.getDate()
    
    return { 
      number, 
      isCurrent, 
      isToday: isoDate === todayStr, 
      events: events.value.filter((event) => event.date === isoDate) 
    }
  })
})

function changeMonth(offset) {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + offset, 1)
}

function resetToToday() {
  viewDate.value = new Date()
}

function handleEventClick(event) {
  if (event.type === 'task') {
    activeTaskId.value = event.id
  } else {
    router.push(`/projects/${event.id}`)
  }
}
</script>

<template>
  <div class="space-y-6 pb-12 h-full flex flex-col">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 mb-1">Lịch</h1>
        <p class="text-slate-500 text-sm">Hạn chót nhiệm vụ và dự án</p>
      </div>
      <button 
        @click="resetToToday"
        class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-medium hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm shrink-0 flex items-center gap-2"
      >
        <CalendarIcon class="w-5 h-5" /> Hôm nay
      </button>
    </div>

    <!-- Calendar Container -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm flex flex-col flex-1 min-h-[600px]">
      
      <!-- Calendar Header -->
      <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100">
        <div class="flex items-center gap-4">
          <div class="flex items-center bg-slate-50 rounded-lg p-1 border border-slate-100">
            <button @click="changeMonth(-1)" class="p-1.5 hover:bg-white hover:shadow-sm rounded-md transition-all text-slate-500 hover:text-slate-900">
              <ChevronLeft class="w-5 h-5" />
            </button>
            <button @click="changeMonth(1)" class="p-1.5 hover:bg-white hover:shadow-sm rounded-md transition-all text-slate-500 hover:text-slate-900">
              <ChevronRight class="w-5 h-5" />
            </button>
          </div>
          <h2 class="text-xl font-bold text-slate-900 capitalize">{{ calendarTitle }}</h2>
        </div>
        
        <div class="flex items-center gap-4 text-sm font-medium">
          <div class="flex items-center gap-2 text-slate-600">
            <div class="w-3 h-3 rounded-full bg-violet-500"></div>
            Hạn dự án
          </div>
          <div class="flex items-center gap-2 text-slate-600">
            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
            Hạn nhiệm vụ
          </div>
        </div>
      </div>

      <!-- Days of Week -->
      <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/50">
        <div v-for="day in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="day" class="py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">
          {{ day }}
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="grid grid-cols-7 flex-1 bg-slate-100 gap-[1px]">
        <div 
          v-for="(day, index) in calendarDays" 
          :key="index" 
          class="bg-white p-2 min-h-[120px] flex flex-col transition-colors hover:bg-slate-50/50"
        >
          <!-- Date Number -->
          <div class="flex justify-between items-start mb-2">
            <span 
              :class="[
                'w-7 h-7 flex items-center justify-center rounded-full text-sm font-semibold',
                !day.isCurrent ? 'text-slate-300' : 'text-slate-700',
                day.isToday ? 'bg-gradient-to-r from-violet-500 to-indigo-600 text-white shadow-md shadow-violet-500/20' : ''
              ]"
            >
              {{ day.number }}
            </span>
          </div>

          <!-- Events -->
          <div class="flex-1 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
            <button 
              v-for="event in day.events.slice(0, 3)" 
              :key="`${event.type}-${event.id}`"
              @click="handleEventClick(event)"
              :class="[
                'w-full text-left px-2 py-1.5 rounded text-xs font-medium truncate flex items-center gap-1.5 transition-colors border shadow-sm',
                event.type === 'project' 
                  ? 'bg-violet-50 text-violet-700 border-violet-100 hover:bg-violet-100' 
                  : (event.priority === 'high' ? 'bg-rose-50 text-rose-700 border-rose-100 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100 hover:bg-emerald-100')
              ]"
            >
              <FolderKanban v-if="event.type === 'project'" class="w-3 h-3 shrink-0" />
              <CheckSquare v-else class="w-3 h-3 shrink-0" />
              <span class="truncate leading-none">{{ event.text }}</span>
            </button>
            
            <div v-if="day.events.length > 3" class="text-[10px] font-bold text-slate-400 px-1 py-0.5 text-center bg-slate-50 rounded">
              +{{ day.events.length - 3 }} nữa
            </div>
          </div>
        </div>
      </div>
      
    </div>
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
  border-radius: 20px;
}
</style>

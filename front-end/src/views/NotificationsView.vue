<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, AtSign, Bell, CheckCheck, ChevronRight, MessageSquare, RefreshCw } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'
import { apiFetch, parseApiError } from '../services/api'
import { notificationDestination, notificationExactTime, notificationTimeAgo } from '../utils/notifications'

const router = useRouter()
const { notifications, markNotificationAsRead, markAllNotificationsAsRead, activeTaskId, notify } = useProjectWorkspace()
const items = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const filter = ref('all')
const meta = ref({ currentPage: 0, lastPage: 1, total: 0 })

const visibleItems = computed(() => filter.value === 'unread' ? items.value.filter(item => !item.read) : items.value)
const unreadCount = computed(() => items.value.filter(item => !item.read).length)

const getIcon = (type) => {
  if (type === 'comment') return MessageSquare
  if (type === 'mention') return AtSign
  if (['warning', 'danger'].includes(type)) return AlertTriangle
  return Bell
}

const getColor = (type) => {
  if (type === 'comment') return 'bg-blue-100 text-blue-600'
  if (type === 'mention') return 'bg-violet-100 text-violet-600'
  if (type === 'warning') return 'bg-amber-100 text-amber-700'
  if (type === 'danger') return 'bg-rose-100 text-rose-700'
  return 'bg-emerald-100 text-emerald-600'
}

const loadPage = async (page = 1) => {
  if (page === 1) loading.value = true
  else loadingMore.value = true
  try {
    const response = await apiFetch(`/api/notifications?paginate=1&per_page=30&page=${page}`)
    if (!response.ok) {
      notify(await parseApiError(response, 'Không thể tải danh sách thông báo.'))
      return
    }
    const payload = await response.json()
    items.value = page === 1
      ? payload.data
      : [...items.value, ...payload.data.filter(item => !items.value.some(existing => existing.id === item.id))]
    meta.value = {
      currentPage: payload.current_page || page,
      lastPage: payload.last_page || 1,
      total: payload.total || items.value.length,
    }
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const openNotification = async (notification) => {
  await markNotificationAsRead(notification.id)
  notification.read = true
  const destination = notificationDestination(notification)
  if (!destination) {
    notify('Thông báo cũ này không có liên kết đến dự án hoặc nhiệm vụ.')
    return
  }
  if ((notification.targetType || notification.target_type) === 'Task') {
    activeTaskId.value = notification.targetCode || notification.target_code
  }
  await router.push(destination)
}

const markAllRead = async () => {
  await markAllNotificationsAsRead()
  items.value.forEach(item => { item.read = true })
  notifications.value.forEach(item => { item.read = true })
}

onMounted(() => loadPage(1))
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 pb-12">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="flex items-center gap-3 text-3xl font-bold text-slate-900">
          <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-600"><Bell class="h-6 w-6" /></span>
          Thông báo
        </h1>
        <p class="mt-2 text-sm text-slate-500">{{ meta.total }} thông báo · {{ unreadCount }} chưa đọc</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-500 hover:border-violet-200 hover:text-violet-600" title="Làm mới" @click="loadPage(1)">
          <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
        </button>
        <button type="button" class="flex items-center gap-2 rounded-xl bg-violet-50 px-4 py-2.5 text-sm font-bold text-violet-700 hover:bg-violet-100 disabled:opacity-50" :disabled="unreadCount === 0" @click="markAllRead">
          <CheckCheck class="h-4 w-4" /> Đánh dấu đã đọc
        </button>
      </div>
    </header>

    <div class="flex gap-2 rounded-2xl border border-slate-100 bg-white p-2 shadow-sm">
      <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold" :class="filter === 'all' ? 'bg-violet-100 text-violet-700' : 'text-slate-500 hover:bg-slate-50'" @click="filter = 'all'">Tất cả</button>
      <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold" :class="filter === 'unread' ? 'bg-violet-100 text-violet-700' : 'text-slate-500 hover:bg-slate-50'" @click="filter = 'unread'">Chưa đọc ({{ unreadCount }})</button>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="item in 5" :key="item" class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
      </div>
      <div v-else-if="visibleItems.length === 0" class="flex flex-col items-center px-6 py-16 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><Bell class="h-7 w-7" /></span>
        <h2 class="mt-4 font-bold text-slate-800">Không có thông báo phù hợp</h2>
        <p class="mt-1 text-sm text-slate-500">Các cập nhật mới liên quan đến công việc sẽ xuất hiện tại đây.</p>
      </div>
      <template v-else>
        <button
          v-for="notification in visibleItems"
          :key="notification.id"
          type="button"
          class="group relative flex w-full gap-4 border-b border-slate-100 p-5 text-left transition last:border-b-0 hover:bg-slate-50"
          :class="!notification.read ? 'bg-violet-50/40' : ''"
          @click="openNotification(notification)"
        >
          <span v-if="!notification.read" class="absolute left-1.5 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-violet-500"></span>
          <span :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl', getColor(notification.type)]">
            <component :is="getIcon(notification.type)" class="h-5 w-5" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-start justify-between gap-4">
              <span class="font-bold text-slate-900">{{ notification.title }}</span>
              <span class="shrink-0 text-xs font-medium text-slate-400" :title="notificationExactTime(notification.createdAt)">{{ notificationTimeAgo(notification.createdAt) }}</span>
            </span>
            <span class="mt-1 block text-sm leading-relaxed text-slate-600">{{ notification.message }}</span>
            <span v-if="notification.targetCode || notification.target_code" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-violet-600">Xem chi tiết <ChevronRight class="h-3.5 w-3.5" /></span>
            <span v-else class="mt-2 block text-xs font-medium text-slate-400">Thông báo hệ thống không có liên kết</span>
          </span>
        </button>
      </template>
    </section>

    <button
      v-if="meta.currentPage < meta.lastPage"
      type="button"
      class="w-full rounded-xl border border-slate-200 bg-white py-3 text-sm font-bold text-slate-600 hover:border-violet-200 hover:text-violet-700 disabled:opacity-50"
      :disabled="loadingMore"
      @click="loadPage(meta.currentPage + 1)"
    >
      {{ loadingMore ? 'Đang tải...' : `Xem thêm (${items.length}/${meta.total})` }}
    </button>
  </div>
</template>

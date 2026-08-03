<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import {
  Activity,
  ArrowLeft,
  CalendarDays,
  CheckSquare,
  ChevronRight,
  FolderKanban,
  MessageSquareText,
  RefreshCw,
  Search,
} from "@lucide/vue";
import UserAvatar from "../components/common/UserAvatar.vue";
import { useProjectWorkspace } from "../composables/useProjectWorkspace";
import { apiFetch } from "../services/api";

const router = useRouter();
const {
  currentUser,
  projects,
  tasks,
  activeTaskId,
  notify,
} = useProjectWorkspace();

const activities = ref([]);
const meta = ref({ currentPage: 0, lastPage: 1, total: 0 });
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const keyword = ref("");
const selectedType = ref("");
let searchTimer;
let refreshTimer;
let requestSequence = 0;

const typeOptions = [
  { value: "", label: "Tất cả hoạt động" },
  { value: "Project", label: "Dự án" },
  { value: "Task", label: "Nhiệm vụ" },
  { value: "TaskComment", label: "Trao đổi và tệp" },
];

const scopeDescription = computed(() =>
  currentUser.value?.role === "admin"
    ? "Toàn bộ hoạt động được ghi nhận trong công ty."
    : "Hoạt động của bạn và các dự án bạn được phép truy cập.",
);

const mapActivity = (activityItem) => {
  const actorName = activityItem.user?.name || "Người dùng hệ thống";
  return {
    ...activityItem,
    id: activityItem.code,
    actor: {
      id: activityItem.user?.code || activityItem.user_code || "",
      name: actorName,
      initials:
        actorName
          .split(" ")
          .filter(Boolean)
          .map((part) => part[0])
          .join("")
          .toUpperCase()
          .slice(0, 2) || "HT",
    },
    target: activityItem.target_label || activityItem.target_code || "",
    createdAt: activityItem.created_at,
  };
};

const loadHistory = async ({ reset = true } = {}) => {
  const sequence = ++requestSequence;
  const nextPage = reset ? 1 : meta.value.currentPage + 1;
  if (reset) {
    loading.value = true;
    errorMessage.value = "";
  } else {
    loadingMore.value = true;
  }

  try {
    const params = new URLSearchParams({
      paginated: "1",
      per_page: "30",
      page: String(nextPage),
    });
    if (selectedType.value) params.set("type", selectedType.value);
    if (keyword.value.trim()) params.set("search", keyword.value.trim());

    const response = await apiFetch("/api/activities?" + params.toString());
    if (!response.ok) throw new Error("activity-history-request-failed");
    const payload = await response.json();
    if (sequence !== requestSequence) return;

    const mappedItems = (payload.data || []).map(mapActivity);
    activities.value = reset
      ? mappedItems
      : [...activities.value, ...mappedItems];
    meta.value = {
      currentPage: Number(payload.current_page || nextPage),
      lastPage: Number(payload.last_page || 1),
      total: Number(payload.total || 0),
    };
  } catch {
    if (sequence !== requestSequence) return;
    errorMessage.value = "Không thể tải lịch sử hoạt động. Vui lòng thử lại.";
  } finally {
    if (sequence === requestSequence) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
};

const refreshHistory = () => {
  window.clearTimeout(refreshTimer);
  refreshTimer = window.setTimeout(() => loadHistory({ reset: true }), 250);
};

watch([keyword, selectedType], () => {
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => loadHistory({ reset: true }), 350);
});

onMounted(() => {
  loadHistory({ reset: true });
  window.addEventListener("ringnet:activity-changed", refreshHistory);
});

onUnmounted(() => {
  window.removeEventListener("ringnet:activity-changed", refreshHistory);
  window.clearTimeout(searchTimer);
  window.clearTimeout(refreshTimer);
});

const targetTypeMeta = (type) => {
  if (type === "Project") {
    return {
      label: "Dự án",
      icon: FolderKanban,
      badge: "bg-indigo-50 text-indigo-700 ring-indigo-100",
    };
  }
  if (type === "TaskComment") {
    return {
      label: "Trao đổi và tệp",
      icon: MessageSquareText,
      badge: "bg-sky-50 text-sky-700 ring-sky-100",
    };
  }
  return {
    label: "Nhiệm vụ",
    icon: CheckSquare,
    badge: "bg-violet-50 text-violet-700 ring-violet-100",
  };
};

const targetFor = (activityItem) => {
  if (activityItem.target_type === "Project") {
    return projects.value.find((project) => project.id === activityItem.target_code);
  }
  if (["Task", "TaskComment"].includes(activityItem.target_type)) {
    return tasks.value.find((task) => task.id === activityItem.target_code);
  }
  return null;
};

const openTarget = (activityItem) => {
  const target = targetFor(activityItem);
  if (!target) {
    notify("Đối tượng này không còn hoạt động hoặc bạn không có quyền truy cập.");
    return;
  }
  if (activityItem.target_type === "Project") {
    router.push("/projects/" + target.id);
    return;
  }
  activeTaskId.value = target.id;
};

const localDateKey = (dateValue) => {
  const date = new Date(dateValue);
  if (Number.isNaN(date.getTime())) return "unknown";
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return [year, month, day].join("-");
};

const dateHeading = (dateKey) => {
  if (dateKey === "unknown") return "Không xác định thời gian";
  const today = new Date();
  const todayKey = localDateKey(today);
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (dateKey === todayKey) return "Hôm nay";
  if (dateKey === localDateKey(yesterday)) return "Hôm qua";

  return new Intl.DateTimeFormat("vi-VN", {
    weekday: "long",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(new Date(dateKey + "T00:00:00"));
};

const groupedActivities = computed(() => {
  const groups = [];
  activities.value.forEach((activityItem) => {
    const key = localDateKey(activityItem.createdAt);
    let group = groups.find((item) => item.key === key);
    if (!group) {
      group = { key, label: dateHeading(key), items: [] };
      groups.push(group);
    }
    group.items.push(activityItem);
  });
  return groups;
});

const formatExactTime = (dateValue) => {
  const date = new Date(dateValue);
  if (Number.isNaN(date.getTime())) return "Không xác định";
  return new Intl.DateTimeFormat("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <button
          type="button"
          class="mb-3 flex min-h-10 items-center gap-2 rounded-lg px-2 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-violet-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
          @click="router.push('/')"
        >
          <ArrowLeft class="h-4 w-4" />
          Tổng quan
        </button>
        <h1 class="flex items-center gap-3 text-3xl font-bold text-slate-900">
          <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
            <Activity class="h-6 w-6" />
          </span>
          Lịch sử hoạt động
        </h1>
        <p class="mt-2 text-sm text-slate-600">{{ scopeDescription }}</p>
      </div>
      <button
        type="button"
        :disabled="loading"
        class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-violet-200 hover:text-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-100 disabled:opacity-60"
        @click="loadHistory({ reset: true })"
      >
        <RefreshCw :class="['h-4 w-4', loading ? 'animate-spin' : '']" />
        Làm mới
      </button>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Bộ lọc lịch sử">
      <div class="flex flex-col gap-3 md:flex-row">
        <label class="relative flex-1">
          <span class="sr-only">Tìm kiếm lịch sử hoạt động</span>
          <Search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <input
            v-model="keyword"
            type="search"
            class="min-h-11 w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-100"
            placeholder="Tìm theo người thao tác, hành động, dự án hoặc nhiệm vụ..."
          />
        </label>
        <label>
          <span class="sr-only">Lọc loại hoạt động</span>
          <select
            v-model="selectedType"
            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-violet-300 focus:ring-4 focus:ring-violet-100 md:w-52"
          >
            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
      </div>
      <div v-if="!loading && !errorMessage" class="mt-3 flex items-center gap-2 text-xs text-slate-500">
        <CalendarDays class="h-4 w-4" />
        {{ meta.total }} hoạt động được tìm thấy
      </div>
    </section>

    <section
      v-if="errorMessage"
      class="rounded-2xl border border-rose-200 bg-white px-6 py-10 text-center"
      role="alert"
    >
      <Activity class="mx-auto h-8 w-8 text-rose-500" />
      <h2 class="mt-3 font-bold text-slate-900">Chưa tải được lịch sử</h2>
      <p class="mt-1 text-sm text-slate-600">{{ errorMessage }}</p>
      <button
        type="button"
        class="mt-5 min-h-10 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white hover:bg-violet-700"
        @click="loadHistory({ reset: true })"
      >
        Thử lại
      </button>
    </section>

    <section v-else-if="loading" class="space-y-4" aria-label="Đang tải lịch sử">
      <div v-for="index in 6" :key="index" class="h-28 animate-pulse rounded-2xl bg-white"></div>
    </section>

    <section v-else-if="groupedActivities.length" class="space-y-8">
      <section v-for="group in groupedActivities" :key="group.key" :aria-labelledby="'activity-date-' + group.key">
        <div class="mb-3 flex items-center gap-3">
          <h2 :id="'activity-date-' + group.key" class="text-sm font-bold capitalize text-slate-700">
            {{ group.label }}
          </h2>
          <span class="h-px flex-1 bg-slate-200"></span>
          <span class="text-xs font-semibold text-slate-500">{{ group.items.length }} hoạt động</span>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <article
            v-for="activityItem in group.items"
            :key="activityItem.id"
            class="flex gap-4 border-b border-slate-100 p-5 last:border-b-0"
          >
            <UserAvatar
              v-if="activityItem.actor.id"
              :member-id="activityItem.actor.id"
              size="md"
              :show-popover="false"
              class="shrink-0"
            />
            <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-500 text-xs font-bold text-white">
              {{ activityItem.actor.initials }}
            </span>

            <div class="min-w-0 flex-1">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <p class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-sm leading-relaxed text-slate-700">
                  <strong class="font-bold text-slate-900">{{ activityItem.actor.name }}</strong>
                  <span>{{ activityItem.action }}</span>
                  <strong class="font-semibold text-slate-900">{{ activityItem.target }}</strong>
                </p>
                <time class="shrink-0 text-xs font-medium text-slate-500" :datetime="activityItem.createdAt">
                  {{ formatExactTime(activityItem.createdAt) }}
                </time>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-2">
                <span
                  :class="[
                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1',
                    targetTypeMeta(activityItem.target_type).badge,
                  ]"
                >
                  <component :is="targetTypeMeta(activityItem.target_type).icon" class="h-3.5 w-3.5" />
                  {{ targetTypeMeta(activityItem.target_type).label }}
                </span>
                <span class="text-xs font-medium text-slate-500">{{ activityItem.target_code }}</span>
              </div>

              <p v-if="activityItem.detail" class="mt-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-600">
                {{ activityItem.detail }}
              </p>
            </div>

            <button
              type="button"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-violet-50 hover:text-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-100"
              :title="'Mở ' + targetTypeMeta(activityItem.target_type).label.toLowerCase()"
              :aria-label="'Mở ' + activityItem.target"
              @click="openTarget(activityItem)"
            >
              <ChevronRight class="h-5 w-5" />
            </button>
          </article>
        </div>
      </section>

      <button
        v-if="meta.currentPage < meta.lastPage"
        type="button"
        :disabled="loadingMore"
        class="mx-auto flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-6 text-sm font-semibold text-slate-700 transition hover:border-violet-200 hover:text-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-100 disabled:opacity-60"
        @click="loadHistory({ reset: false })"
      >
        <RefreshCw :class="['h-4 w-4', loadingMore ? 'animate-spin' : '']" />
        {{ loadingMore ? "Đang tải..." : "Xem thêm (" + activities.length + "/" + meta.total + ")" }}
      </button>
    </section>

    <section v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <Activity class="mx-auto h-9 w-9 text-slate-400" />
      <h2 class="mt-3 font-bold text-slate-800">Không tìm thấy hoạt động</h2>
      <p class="mt-1 text-sm text-slate-500">Hãy thử thay đổi từ khóa hoặc loại hoạt động.</p>
    </section>
  </div>
</template>

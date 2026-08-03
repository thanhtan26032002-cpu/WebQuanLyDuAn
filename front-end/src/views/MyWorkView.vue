<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  AlertTriangle,
  Ban,
  CalendarClock,
  CalendarOff,
  CalendarRange,
  CheckCircle2,
  CircleCheckBig,
  Clock,
  FolderKanban,
  ListTodo,
  Play,
  RefreshCw,
  RotateCcw,
  Search,
  TrendingUp,
  Users,
  ArrowRight,
} from "@lucide/vue";
import { apiFetch, parseApiError } from "../services/api";
import { useProjectWorkspace } from "../composables/useProjectWorkspace";

const {
  activeTaskId,
  currentUser,
  formatDate,
  moveTask,
  priorityMap,
  taskStatusMap,
} = useProjectWorkspace();
const router = useRouter();

const emptyData = () => ({
  owner: null,
  summary: {
    total_assigned: 0,
    active: 0,
    in_progress: 0,
    overdue: 0,
    due_today: 0,
    upcoming: 0,
    blocked: 0,
    completed: 0,
  },
  sections: {
    overdue: [],
    today: [],
    upcoming: [],
    later: [],
    no_deadline: [],
    recently_completed: [],
  },
  projects: {
    summary: { total: 0, managed: 0, participating: 0, active: 0, overdue: 0 },
    items: [],
  },
  meta: null,
});

const loading = ref(true);
const error = ref("");
const data = ref(emptyData());
const workspaceView = ref("tasks");
const activeTab = ref("active");
const projectFilter = ref("all");
const searchQuery = ref("");
const changingTaskId = ref(null);

const ownerName = computed(
  () => data.value.owner?.name || currentUser.value?.name || "bạn",
);

const summaryCards = computed(() => [
  {
    id: "active",
    label: "Đang cần xử lý",
    value: data.value.summary.active,
    helper: `${data.value.summary.in_progress} đang thực hiện`,
    icon: ListTodo,
    iconClass: "bg-violet-100 text-violet-700",
  },
  {
    id: "overdue",
    label: "Đã quá hạn",
    value: data.value.summary.overdue,
    helper: data.value.summary.overdue ? "Cần ưu tiên ngay" : "Không có việc trễ hạn",
    icon: AlertTriangle,
    iconClass: "bg-rose-100 text-rose-700",
  },
  {
    id: "due",
    label: "Đến hạn hôm nay",
    value: data.value.summary.due_today,
    helper: `${data.value.summary.upcoming} việc trong 7 ngày tới`,
    icon: CalendarClock,
    iconClass: "bg-amber-100 text-amber-700",
  },
  {
    id: "completed",
    label: "Đã hoàn thành",
    value: data.value.summary.completed,
    helper: `${data.value.summary.blocked} việc đang bị chặn`,
    icon: CircleCheckBig,
    iconClass: "bg-emerald-100 text-emerald-700",
  },
]);
const projectSummaryCards = computed(() => [
  { id: "all", label: "Tổng dự án", value: data.value.projects?.summary?.total || 0, helper: "Bạn tham gia hoặc phụ trách", icon: FolderKanban, iconClass: "bg-indigo-100 text-indigo-700" },
  { id: "managed", label: "Đang phụ trách", value: data.value.projects?.summary?.managed || 0, helper: "Bạn chịu trách nhiệm chính", icon: Users, iconClass: "bg-violet-100 text-violet-700" },
  { id: "active", label: "Chưa hoàn thành", value: data.value.projects?.summary?.active || 0, helper: "Còn đang được triển khai", icon: Clock, iconClass: "bg-emerald-100 text-emerald-700" },
  { id: "overdue", label: "Đã quá hạn", value: data.value.projects?.summary?.overdue || 0, helper: "Cần ưu tiên xử lý", icon: AlertTriangle, iconClass: "bg-rose-100 text-rose-700" },
]);

const activeSectionDefinitions = computed(() => [
  {
    id: "overdue",
    title: "Quá hạn",
    description: "Cần được ưu tiên xử lý trước các công việc khác.",
    icon: AlertTriangle,
    iconClass: "bg-rose-100 text-rose-700",
    countClass: "bg-rose-50 text-rose-700",
    items: data.value.sections.overdue,
  },
  {
    id: "today",
    title: "Đến hạn hôm nay",
    description: "Hoàn thành trong ngày để bảo đảm đúng cam kết.",
    icon: CalendarClock,
    iconClass: "bg-amber-100 text-amber-700",
    countClass: "bg-amber-50 text-amber-700",
    items: data.value.sections.today,
  },
  {
    id: "upcoming",
    title: "7 ngày tới",
    description: "Chủ động lên kế hoạch cho những công việc sắp đến hạn.",
    icon: CalendarRange,
    iconClass: "bg-blue-100 text-blue-700",
    countClass: "bg-blue-50 text-blue-700",
    items: data.value.sections.upcoming,
  },
  {
    id: "later",
    title: "Kế hoạch dài hạn",
    description: "Các nhiệm vụ có thời hạn sau 7 ngày tới.",
    icon: Clock,
    iconClass: "bg-indigo-100 text-indigo-700",
    countClass: "bg-indigo-50 text-indigo-700",
    items: data.value.sections.later,
  },
  {
    id: "no_deadline",
    title: "Chưa đặt thời hạn",
    description: "Nên trao đổi với quản lý để xác định thời hạn phù hợp.",
    icon: CalendarOff,
    iconClass: "bg-slate-100 text-slate-600",
    countClass: "bg-slate-100 text-slate-600",
    items: data.value.sections.no_deadline,
  },
]);

const normalizedSearch = computed(() => searchQuery.value.trim().toLocaleLowerCase("vi"));
const matchesSearch = (task) => {
  if (!normalizedSearch.value) return true;
  return [task.title, task.code, task.project?.name]
    .filter(Boolean)
    .some((value) => String(value).toLocaleLowerCase("vi").includes(normalizedSearch.value));
};

const visibleSections = computed(() =>
  activeSectionDefinitions.value
    .map((section) => ({
      ...section,
      items: section.items.filter(matchesSearch),
    }))
    .filter((section) => section.items.length > 0),
);

const completedTasks = computed(() =>
  data.value.sections.recently_completed.filter(matchesSearch),
);
const overdueTasks = computed(() => data.value.sections.overdue.filter(matchesSearch));
const participationRoleLabels = {
  manager: "Phụ trách",
  creator: "Khởi tạo",
  member: "Tham gia",
};
const visibleProjects = computed(() =>
  (data.value.projects?.items || []).filter((project) => {
    if (projectFilter.value === "managed" && project.participation_role !== "manager") return false;
    if (projectFilter.value === "active" && project.status === "completed") return false;
    if (projectFilter.value === "overdue" && project.deadline_state !== "overdue") return false;
    if (!normalizedSearch.value) return true;
    return [project.name, project.code, project.manager?.name]
      .filter(Boolean)
      .some((value) => String(value).toLocaleLowerCase("vi").includes(normalizedSearch.value));
  }),
);

const visibleTaskCount = computed(() =>
  activeTab.value === "overdue"
    ? overdueTasks.value.length
    : activeTab.value === "completed"
    ? completedTasks.value.length
    : visibleSections.value.reduce((total, section) => total + section.items.length, 0),
);

const formatLastUpdated = computed(() => {
  if (!data.value.meta?.generated_at) return "";
  return new Date(data.value.meta.generated_at).toLocaleTimeString("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
  });
});

function statusAction(task) {
  if (task.status === "done") {
    return { label: "Mở lại", status: "todo", icon: RotateCcw };
  }
  if (task.is_blocked) {
    return { label: "Đang bị chặn", status: null, icon: Ban };
  }
  if (task.status === "in_progress") {
    return { label: "Hoàn thành", status: "done", icon: CheckCircle2 };
  }
  return { label: "Bắt đầu", status: "in_progress", icon: Play };
}

function dueText(task) {
  if (task.status === "done") return task.completed_at ? `Hoàn thành ${formatDate(task.completed_at)}` : "Đã hoàn thành";
  if (!task.due_date) return "Chưa có hạn chót";
  return `Hạn ${formatDate(task.due_date)}`;
}

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const response = await apiFetch("/api/my-work");
    if (!response.ok) throw new Error(await parseApiError(response));
    data.value = await response.json();
  } catch (loadError) {
    error.value = loadError.message;
  } finally {
    loading.value = false;
  }
}

async function changeStatus(task, status) {
  if (!status || changingTaskId.value) return;
  changingTaskId.value = task.code;
  const changed = await moveTask(task.code, status);
  if (changed) await load();
  changingTaskId.value = null;
}

function openTask(task) {
  activeTaskId.value = task.code;
}

function openProject(project) {
  router.push({ name: "project-detail", params: { id: project.code } });
}

function selectSummary(card) {
  if (card.id === "completed") activeTab.value = "completed";
  if (card.id === "active") activeTab.value = "active";
  if (card.id === "overdue") activeTab.value = "overdue";
  if (card.id === "due") activeTab.value = "active";
}

function switchWorkspace(view) {
  workspaceView.value = view;
  searchQuery.value = "";
  if (view === "tasks" && !["active", "completed", "overdue"].includes(activeTab.value)) activeTab.value = "active";
}

function selectProjectSummary(card) {
  projectFilter.value = card.id;
}

onMounted(load);
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-7 pb-10">
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-600">
          Không gian làm việc cá nhân
        </p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Công việc của tôi</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
          Xin chào {{ ownerName }}. Đây là toàn bộ nhiệm vụ được giao trực tiếp cho bạn,
          cùng các dự án bạn đang tham gia, khởi tạo hoặc được giao phụ trách.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="formatLastUpdated" class="hidden text-xs text-slate-400 sm:inline">
          Cập nhật lúc {{ formatLastUpdated }}
        </span>
        <button
          type="button"
          :disabled="loading"
          class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-violet-200 hover:text-violet-700 disabled:cursor-wait disabled:opacity-60"
          @click="load"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Làm mới
        </button>
      </div>
    </header>

    <div class="flex justify-center">
      <div class="inline-flex rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
        <button
          type="button"
          :class="['flex min-w-36 items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold transition', workspaceView === 'tasks' ? 'bg-gradient-to-r from-violet-500 to-indigo-600 text-white shadow-md shadow-violet-200' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800']"
          @click="switchWorkspace('tasks')"
        >
          <ListTodo class="h-4 w-4" /> Nhiệm vụ
        </button>
        <button
          type="button"
          :class="['flex min-w-36 items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold transition', workspaceView === 'projects' ? 'bg-gradient-to-r from-violet-500 to-indigo-600 text-white shadow-md shadow-violet-200' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800']"
          @click="switchWorkspace('projects')"
        >
          <FolderKanban class="h-4 w-4" /> Dự án
        </button>
      </div>
    </div>

    <div
      v-if="error"
      role="alert"
      class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
    >
      <span>{{ error }}</span>
      <button class="font-bold underline" @click="load">Thử lại</button>
    </div>

    <div v-if="workspaceView === 'tasks'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="card in summaryCards"
        :key="card.id"
        type="button"
        class="rounded-2xl border border-slate-100 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-violet-100 hover:shadow-md"
        @click="selectSummary(card)"
      >
        <div class="flex items-start justify-between gap-4">
          <span :class="['flex h-11 w-11 items-center justify-center rounded-xl', card.iconClass]">
            <component :is="card.icon" class="h-5 w-5" />
          </span>
          <span v-if="loading" class="h-8 w-12 animate-pulse rounded-lg bg-slate-100"></span>
          <strong v-else class="text-3xl font-bold text-slate-900">{{ card.value }}</strong>
        </div>
        <p class="mt-4 text-sm font-bold text-slate-800">{{ card.label }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ card.helper }}</p>
      </button>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="card in projectSummaryCards"
        :key="card.id"
        type="button"
        :class="['rounded-2xl border bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md', projectFilter === card.id ? 'border-indigo-200 ring-2 ring-indigo-50' : 'border-slate-100 hover:border-indigo-100']"
        @click="selectProjectSummary(card)"
      >
        <div class="flex items-start justify-between gap-4">
          <span :class="['flex h-11 w-11 items-center justify-center rounded-xl', card.iconClass]"><component :is="card.icon" class="h-5 w-5" /></span>
          <span v-if="loading" class="h-8 w-12 animate-pulse rounded-lg bg-slate-100"></span>
          <strong v-else class="text-3xl font-bold text-slate-900">{{ card.value }}</strong>
        </div>
        <p class="mt-4 text-sm font-bold text-slate-800">{{ card.label }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ card.helper }}</p>
      </button>
    </div>

    <section v-if="workspaceView === 'tasks'" class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap rounded-xl bg-slate-100 p-1">
          <button
            type="button"
            :class="[
              'rounded-lg px-4 py-2 text-sm font-semibold transition',
              activeTab === 'active' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="activeTab = 'active'"
          >
            Cần xử lý
            <span class="ml-1.5 rounded-full bg-violet-50 px-2 py-0.5 text-[11px] text-violet-700">
              {{ data.summary.active }}
            </span>
          </button>
          <button
            type="button"
            :class="[
              'rounded-lg px-4 py-2 text-sm font-semibold transition',
              activeTab === 'completed' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="activeTab = 'completed'"
          >
            Đã hoàn thành
            <span class="ml-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-700">
              {{ data.summary.completed }}
            </span>
          </button>
          <button
            type="button"
            :class="[
              'rounded-lg px-4 py-2 text-sm font-semibold transition',
              activeTab === 'overdue' ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-slate-800',
            ]"
            @click="activeTab = 'overdue'"
          >
            Quá hạn
            <span class="ml-1.5 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] text-rose-700">
              {{ data.summary.overdue }}
            </span>
          </button>
        </div>

        <label class="relative block w-full sm:max-w-sm">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Tìm theo tên, mã hoặc dự án..."
            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-violet-300 focus:bg-white focus:ring-4 focus:ring-violet-50"
          />
        </label>
      </div>

      <div v-if="loading" class="space-y-4 p-5">
        <div v-for="index in 4" :key="index" class="h-24 animate-pulse rounded-2xl bg-slate-100"></div>
      </div>

      <div v-else-if="activeTab === 'active'" class="divide-y divide-slate-100">
        <section v-for="section in visibleSections" :key="section.id" class="p-5 sm:p-6">
          <header class="mb-4 flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
              <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', section.iconClass]">
                <component :is="section.icon" class="h-5 w-5" />
              </span>
              <div>
                <h2 class="font-bold text-slate-900">{{ section.title }}</h2>
                <p class="mt-0.5 text-xs text-slate-400">{{ section.description }}</p>
              </div>
            </div>
            <span :class="['rounded-full px-2.5 py-1 text-xs font-bold', section.countClass]">
              {{ section.items.length }}
            </span>
          </header>

          <div class="space-y-2.5">
            <article
              v-for="task in section.items"
              :key="task.code"
              class="group flex flex-col gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition hover:border-violet-100 hover:bg-white hover:shadow-sm sm:flex-row sm:items-center"
            >
              <button type="button" class="min-w-0 flex-1 text-left" @click="openTask(task)">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    :class="[
                      'h-2.5 w-2.5 rounded-full',
                      task.priority === 'high' ? 'bg-rose-500' : task.priority === 'medium' ? 'bg-amber-400' : 'bg-sky-400',
                    ]"
                  ></span>
                  <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ task.code }}</span>
                  <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-100">
                    {{ priorityMap[task.priority]?.label || 'Thường' }}
                  </span>
                  <span v-if="task.is_blocked" class="flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                    <Ban class="h-3 w-3" /> Bị chặn
                  </span>
                </div>
                <p class="mt-1.5 truncate text-sm font-bold text-slate-800 group-hover:text-violet-700">{{ task.title }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                  <span class="flex items-center gap-1.5">
                    <FolderKanban class="h-3.5 w-3.5 text-slate-400" />
                    {{ task.project?.name || "Nhiệm vụ độc lập" }}
                  </span>
                  <span class="flex items-center gap-1.5">
                    <CalendarClock class="h-3.5 w-3.5 text-slate-400" />
                    {{ dueText(task) }}
                  </span>
                  <span>{{ task.progress || 0 }}% hoàn thành</span>
                </div>
              </button>

              <div class="flex items-center justify-between gap-3 sm:justify-end">
                <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', task.status === 'in_progress' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600']">
                  {{ taskStatusMap[task.status]?.label || task.status }}
                </span>
                <button
                  type="button"
                  :disabled="!statusAction(task).status || changingTaskId === task.code"
                  class="flex min-w-28 items-center justify-center gap-1.5 rounded-xl border border-violet-200 bg-white px-3 py-2 text-xs font-bold text-violet-700 transition hover:bg-violet-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                  @click="changeStatus(task, statusAction(task).status)"
                >
                  <RefreshCw v-if="changingTaskId === task.code" class="h-3.5 w-3.5 animate-spin" />
                  <component :is="statusAction(task).icon" v-else class="h-3.5 w-3.5" />
                  {{ statusAction(task).label }}
                </button>
              </div>
            </article>
          </div>
        </section>

        <div v-if="visibleTaskCount === 0" class="px-5 py-16 text-center">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
            <CheckCircle2 class="h-7 w-7" />
          </span>
          <h2 class="mt-4 font-bold text-slate-800">{{ searchQuery ? "Không tìm thấy nhiệm vụ phù hợp" : "Bạn đã xử lý hết công việc đang mở" }}</h2>
          <p class="mt-1 text-sm text-slate-400">{{ searchQuery ? "Hãy thử một từ khóa khác." : "Các nhiệm vụ mới được giao sẽ xuất hiện tại đây." }}</p>
        </div>
      </div>

      <div v-else-if="activeTab === 'completed'" class="p-5 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-4">
          <div>
            <h2 class="flex items-center gap-2 font-bold text-slate-900">
              <TrendingUp class="h-5 w-5 text-emerald-600" /> Công việc hoàn thành gần đây
            </h2>
            <p class="mt-1 text-xs text-slate-400">Hiển thị tối đa {{ data.meta?.recently_completed_limit || 20 }} nhiệm vụ gần nhất để trang luôn tải nhanh.</p>
          </div>
          <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ completedTasks.length }}</span>
        </div>

        <div v-if="completedTasks.length" class="space-y-2.5">
          <article v-for="task in completedTasks" :key="task.code" class="group flex flex-col gap-3 rounded-2xl border border-slate-100 p-4 transition hover:border-emerald-100 hover:shadow-sm sm:flex-row sm:items-center">
            <button type="button" class="min-w-0 flex-1 text-left" @click="openTask(task)">
              <div class="flex items-center gap-2">
                <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ task.code }}</span>
              </div>
              <p class="mt-1.5 truncate text-sm font-bold text-slate-700 group-hover:text-emerald-700">{{ task.title }}</p>
              <p class="mt-1 text-xs text-slate-400">{{ task.project?.name || "Nhiệm vụ độc lập" }} · {{ dueText(task) }}</p>
            </button>
            <button
              type="button"
              :disabled="changingTaskId === task.code"
              class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-violet-200 hover:text-violet-700 disabled:opacity-50"
              @click="changeStatus(task, 'todo')"
            >
              <RefreshCw v-if="changingTaskId === task.code" class="h-3.5 w-3.5 animate-spin" />
              <RotateCcw v-else class="h-3.5 w-3.5" /> Mở lại
            </button>
          </article>
        </div>

        <div v-else class="py-14 text-center text-sm text-slate-400">
          {{ searchQuery ? "Không tìm thấy nhiệm vụ đã hoàn thành phù hợp." : "Chưa có nhiệm vụ nào được hoàn thành." }}
        </div>
      </div>

      <div v-else class="p-5 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-4">
          <div>
            <h2 class="flex items-center gap-2 font-bold text-slate-900"><AlertTriangle class="h-5 w-5 text-rose-600" /> Nhiệm vụ quá hạn</h2>
            <p class="mt-1 text-xs text-slate-400">Tập trung xử lý các cam kết đã vượt quá hạn chót.</p>
          </div>
          <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">{{ overdueTasks.length }}</span>
        </div>
        <div v-if="overdueTasks.length" class="space-y-2.5">
          <article v-for="task in overdueTasks" :key="task.code" class="group flex flex-col gap-3 rounded-2xl border border-rose-100 bg-rose-50/40 p-4 transition hover:bg-white hover:shadow-sm sm:flex-row sm:items-center">
            <button type="button" class="min-w-0 flex-1 text-left" @click="openTask(task)">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ task.code }}</span>
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">Quá hạn {{ task.overdue_days }} ngày</span>
              </div>
              <p class="mt-1.5 truncate text-sm font-bold text-slate-800 group-hover:text-rose-700">{{ task.title }}</p>
              <p class="mt-1 text-xs text-slate-500">{{ task.project?.name || "Nhiệm vụ độc lập" }} · Hạn {{ formatDate(task.due_date) }} · {{ task.progress || 0 }}%</p>
            </button>
            <button type="button" :disabled="!statusAction(task).status || changingTaskId === task.code" class="flex min-w-28 items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-50 disabled:opacity-50" @click="changeStatus(task, statusAction(task).status)">
              <RefreshCw v-if="changingTaskId === task.code" class="h-3.5 w-3.5 animate-spin" />
              <component :is="statusAction(task).icon" v-else class="h-3.5 w-3.5" /> {{ statusAction(task).label }}
            </button>
          </article>
        </div>
        <div v-else class="py-14 text-center">
          <CheckCircle2 class="mx-auto h-10 w-10 text-emerald-400" />
          <p class="mt-3 font-bold text-slate-700">{{ searchQuery ? "Không tìm thấy nhiệm vụ quá hạn phù hợp" : "Bạn không có nhiệm vụ quá hạn" }}</p>
          <p class="mt-1 text-sm text-slate-400">Tiến độ cá nhân hiện đang đúng cam kết.</p>
        </div>
      </div>
    </section>

    <section v-else class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
      <div class="flex flex-col gap-4 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-bold text-slate-900">Dự án của tôi</h2>
          <p class="mt-1 text-xs text-slate-400">Đang lọc: {{ projectSummaryCards.find(card => card.id === projectFilter)?.label }}</p>
        </div>
        <label class="relative block w-full sm:max-w-sm">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input v-model="searchQuery" type="search" placeholder="Tìm dự án theo tên, mã hoặc quản lý..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-50" />
        </label>
      </div>
      <div v-if="loading" class="grid gap-4 p-5 lg:grid-cols-2"><div v-for="index in 4" :key="index" class="h-72 animate-pulse rounded-2xl bg-slate-100"></div></div>
      <div v-else class="p-5 sm:p-6">
        <div v-if="visibleProjects.length" class="grid gap-4 lg:grid-cols-2">
          <article v-for="project in visibleProjects" :key="project.code" class="group rounded-2xl border border-slate-100 bg-slate-50/60 p-5 transition hover:border-indigo-100 hover:bg-white hover:shadow-sm">
            <button type="button" class="w-full text-left" @click="openProject(project)">
              <div class="flex items-start justify-between gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700"><FolderKanban class="h-5 w-5" /></span>
                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-indigo-700 ring-1 ring-indigo-100">{{ participationRoleLabels[project.participation_role] || "Tham gia" }}</span>
              </div>
              <p class="mt-4 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ project.code }}</p>
              <h2 class="mt-1 truncate font-bold text-slate-900 group-hover:text-indigo-700">{{ project.name }}</h2>
              <p class="mt-1 line-clamp-2 min-h-10 text-sm text-slate-500">{{ project.description || "Chưa có mô tả dự án." }}</p>
              <div class="mt-4"><div class="mb-1.5 flex items-center justify-between text-xs"><span class="font-medium text-slate-500">Tiến độ</span><strong class="text-slate-800">{{ project.progress || 0 }}%</strong></div><div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-600" :style="{ width: `${project.progress || 0}%` }"></div></div></div>
              <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><Users class="h-3.5 w-3.5" /> {{ project.member_count }} thành viên</span>
                <span class="flex items-center gap-1.5"><ListTodo class="h-3.5 w-3.5" /> {{ project.open_task_count }} nhiệm vụ đang mở</span>
                <span class="flex items-center gap-1.5"><CalendarClock class="h-3.5 w-3.5" /> {{ project.due_date ? `Hạn ${formatDate(project.due_date)}` : "Chưa có hạn" }}</span>
              </div>
              <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <span v-if="project.deadline_state === 'overdue'" class="text-xs font-bold text-rose-600">Quá hạn {{ project.overdue_days }} ngày</span>
                <span v-else class="text-xs font-medium text-slate-400">Bạn có {{ project.assigned_task_count }} nhiệm vụ trong dự án</span>
                <span class="flex items-center gap-1 text-xs font-bold text-indigo-600">Xem dự án <ArrowRight class="h-3.5 w-3.5" /></span>
              </div>
            </button>
          </article>
        </div>
        <div v-else class="py-14 text-center"><FolderKanban class="mx-auto h-10 w-10 text-slate-300" /><p class="mt-3 font-bold text-slate-700">{{ searchQuery ? "Không tìm thấy dự án phù hợp" : "Không có dự án trong bộ lọc này" }}</p><p class="mt-1 text-sm text-slate-400">Hãy chọn một bộ lọc khác hoặc xóa từ khóa tìm kiếm.</p></div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";
import draggable from "vuedraggable";
import {
  FolderKanban,
  CheckCircle2,
  ArrowUpRight,
  Plus,
  MoreHorizontal,
  Activity,
  UploadCloud,
  CheckSquare,
  ChevronRight,
  BellRing,
  AlertTriangle,
  RefreshCw,
  ListTodo,
  History,
  X,
} from "@lucide/vue";
import ProjectCard from "../components/common/ProjectCard.vue";
import TaskCard from "../components/common/TaskCard.vue";
import UserAvatar from "../components/common/UserAvatar.vue";
import { useProjectWorkspace } from "../composables/useProjectWorkspace";
import { apiFetch } from "../services/api";

const router = useRouter();
const {
  projects,
  tasks,
  currentUser,
  projectModalOpen,
  taskModalOpen,
  formatDate,
  activeTaskId,
  importProjectModalOpen,
  notify,
  moveTask,
} = useProjectWorkspace();

const getLocalDateKey = (dateValue = new Date()) => {
  if (!dateValue) return "";

  if (typeof dateValue === "string") {
    return dateValue.split("T")[0];
  }

  const year = dateValue.getFullYear();
  const month = String(dateValue.getMonth() + 1).padStart(2, "0");
  const day = String(dateValue.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const todayKey = getLocalDateKey();
const currentUserCode = computed(() => currentUser.value?.code || "");
const canManageProjects = computed(() =>
  ["admin", "project_manager", "manager"].includes(currentUser.value?.role),
);
const canCreateTasks = computed(() => Boolean(currentUserCode.value));
const currentUserName = computed(
  () => currentUser.value?.name?.trim() || "bạn",
);

const myProjects = computed(() => {
  if (!currentUserCode.value) return [];

  return projects.value.filter(
    (project) =>
      project.managerId === currentUserCode.value ||
      project.created_by === currentUserCode.value ||
      project.memberIds?.includes(currentUserCode.value),
  );
});

const myTasks = computed(() => {
  if (!currentUserCode.value) return [];
  return tasks.value.filter(
    (task) => task.assigneeId === currentUserCode.value,
  );
});

// Tổng quan là bảng điều hành chung; dữ liệu cá nhân chỉ dùng cho popup nhắc hạn.
const companyProjects = ref([]);
const companyTasks = ref([]);
const overviewActivities = ref([]);
const overviewLoading = ref(true);
const overviewError = ref("");
const hasLoadedOverview = ref(false);
const projectActionsMenu = ref(null);
const overviewProjects = computed(() => companyProjects.value);
const overviewTasks = computed(() => companyTasks.value);

const mapCompanyProject = (project) => ({
  ...project,
  id: project.code,
  dueDate: project.due_date,
  startDate: project.start_date,
  managerId: project.manager_code,
  created_by: project.created_by,
  memberIds: (project.members || []).map((member) => member.code),
  deadlineState: project.deadline_state || "none",
  overdueDays: Number(project.overdue_days || 0),
  lateDays: Number(project.late_days || 0),
  canView: Boolean(project.can_view),
  canCreateTask: Boolean(project.can_create_task),
});

const mapCompanyTask = (task) => ({
  ...task,
  id: task.code,
  projectId: task.project_code,
  projectName: task.project?.name || "",
  assigneeId: task.assignee_code,
  dueDate: task.due_date,
  startDate: task.start_date,
  tags: Array.isArray(task.tags)
    ? task.tags
    : String(task.tags || "").split(",").map((tag) => tag.trim()).filter(Boolean),
  deadlineState: task.deadline_state || "none",
  overdueDays: Number(task.overdue_days || 0),
  lateDays: Number(task.late_days || 0),
  isBlocked: Boolean(task.is_blocked),
  blockedReason: task.blocked_reason || "",
  delayReason: task.delay_reason || "",
  recoveryPlan: task.recovery_plan || "",
  canView: Boolean(task.can_view),
  canContribute: Boolean(task.can_contribute),
});

const mapCompanyActivity = (activity) => {
  const actorName = activity.user?.name || "Người dùng hệ thống";
  return {
    ...activity,
    id: activity.code,
    target: activity.target_label || "",
    createdAt: activity.created_at,
    actor: {
      id: activity.user?.code || "",
      name: actorName,
      initials: actorName.split(" ").filter(Boolean).map((part) => part[0]).join("").toUpperCase().slice(0, 2) || "HT",
      color: activity.user?.color || "slate",
    },
  };
};

const loadCompanyOverview = async ({ silent = false } = {}) => {
  if (!silent || !hasLoadedOverview.value) overviewLoading.value = true;
  overviewError.value = "";

  try {
    const response = await apiFetch("/api/company-overview");
    if (!response.ok) throw new Error("overview-request-failed");
    const payload = await response.json();
    companyProjects.value = (payload.projects || []).map(mapCompanyProject);
    companyTasks.value = (payload.tasks || []).map(mapCompanyTask);
    overviewActivities.value = (payload.activities || []).map(mapCompanyActivity);
    hasLoadedOverview.value = true;
  } catch {
    if (silent && hasLoadedOverview.value) {
      notify("Không thể làm mới Tổng quan. Vui lòng thử lại.");
    } else {
      overviewError.value = "Không thể tải dữ liệu Tổng quan toàn công ty.";
    }
  } finally {
    overviewLoading.value = false;
  }
};

let overviewRefreshTimer;
const closeProjectActions = (event) => {
  if (
    projectActionsMenu.value?.open &&
    (!event || !projectActionsMenu.value.contains(event.target))
  ) {
    projectActionsMenu.value.removeAttribute("open");
  }
};
const handleDashboardKeydown = (event) => {
  if (event.key === "Escape") closeProjectActions();
};
const refreshCompanyOverview = () => {
  window.clearTimeout(overviewRefreshTimer);
  overviewRefreshTimer = window.setTimeout(
    () => loadCompanyOverview({ silent: true }),
    300,
  );
};
onMounted(() => {
  loadCompanyOverview();
  window.addEventListener("ringnet:activity-changed", refreshCompanyOverview);
  document.addEventListener("click", closeProjectActions);
  document.addEventListener("keydown", handleDashboardKeydown);
});
onUnmounted(() => {
  window.removeEventListener("ringnet:activity-changed", refreshCompanyOverview);
  document.removeEventListener("click", closeProjectActions);
  document.removeEventListener("keydown", handleDashboardKeydown);
  window.clearTimeout(overviewRefreshTimer);
  window.clearTimeout(taskDragReleaseTimer);
});

const DAY_IN_MILLISECONDS = 24 * 60 * 60 * 1000;
const toUtcDate = (dateValue) => {
  const [year, month, day] = getLocalDateKey(dateValue)
    .split("-")
    .map(Number);
  return Date.UTC(year, month - 1, day);
};
const daysUntil = (dateValue) =>
  Math.round((toUtcDate(dateValue) - toUtcDate(todayKey)) / DAY_IN_MILLISECONDS);

const buildAttentionItem = (record, type) => {
  const isProject = type === "project";
  const isComplete = isProject
    ? record.status === "completed"
    : record.status === "done";
  if (isComplete) return null;

  const days = record.dueDate ? daysUntil(record.dueDate) : null;
  let level = "";
  let label = "";
  let rank = 99;

  if (days !== null && days < 0) {
    level = "overdue";
    label = `Quá hạn ${Math.abs(days)} ngày`;
    rank = days;
  } else if (days === 0) {
    level = "today";
    label = "Đến hạn hôm nay";
    rank = 10;
  } else if (isProject && record.health === "off_track") {
    level = "risk";
    label = "Chậm tiến độ";
    rank = 20;
  } else if (isProject && record.status === "on_hold") {
    level = "risk";
    label = "Đang tạm dừng";
    rank = 21;
  } else if (isProject && record.health === "at_risk") {
    level = "risk";
    label = "Có rủi ro";
    rank = 22;
  } else if (days !== null && days <= 3) {
    level = "soon";
    label = days === 1 ? "Đến hạn ngày mai" : `Còn ${days} ngày`;
    rank = 30 + days;
  } else if (days !== null && days <= 7) {
    level = "upcoming";
    label = `Còn ${days} ngày`;
    rank = 40 + days;
  }

  if (!level) return null;

  return {
    key: `${type}-${record.id}`,
    type,
    typeLabel: isProject ? "Dự án" : "Nhiệm vụ",
    title: isProject ? record.name : record.title,
    context: isProject
      ? record.manager?.name || "Chưa phân công quản lý"
      : record.projectName || "Không thuộc dự án",
    days,
    level,
    label,
    rank,
    record,
  };
};

const attentionItems = computed(() =>
  [
    ...overviewProjects.value.map((project) =>
      buildAttentionItem(project, "project"),
    ),
    ...overviewTasks.value.map((task) => buildAttentionItem(task, "task")),
  ]
    .filter(Boolean)
    .sort((first, second) => first.rank - second.rank),
);

const attentionSummary = computed(() => ({
  overdue: attentionItems.value.filter((item) => item.level === "overdue").length,
  today: attentionItems.value.filter((item) => item.level === "today").length,
  monitoring: attentionItems.value.filter((item) =>
    ["risk", "soon", "upcoming"].includes(item.level),
  ).length,
}));

const attentionTone = (level) => {
  if (level === "overdue") {
    return {
      badge: "bg-rose-100 text-rose-700",
      icon: "bg-rose-50 text-rose-600",
      border: "border-rose-100 hover:border-rose-200",
    };
  }
  if (["today", "risk", "soon"].includes(level)) {
    return {
      badge: "bg-amber-100 text-amber-800",
      icon: "bg-amber-50 text-amber-700",
      border: "border-amber-100 hover:border-amber-200",
    };
  }
  return {
    badge: "bg-blue-100 text-blue-700",
    icon: "bg-blue-50 text-blue-600",
    border: "border-blue-100 hover:border-blue-200",
  };
};

const dueReminderItems = computed(() => {
  const projectItems = myProjects.value
    .filter(
      (project) => project.dueDate && project.status !== "completed",
    )
    .map((project) => ({
      key: `project-${project.id}`,
      type: "project",
      typeLabel: "Dự án",
      title: project.name,
      dueDate: project.dueDate,
      days: daysUntil(project.dueDate),
    }));
  const taskItems = myTasks.value
    .filter((task) => task.dueDate && task.status !== "done")
    .map((task) => ({
      key: `task-${task.id}`,
      type: "task",
      typeLabel: "Nhiệm vụ",
      title: task.title,
      dueDate: task.dueDate,
      days: daysUntil(task.dueDate),
    }));

  return [...projectItems, ...taskItems]
    .filter((item) => item.days <= 7)
    .sort((first, second) => first.days - second.days);
});

const dueLabel = (item) => {
  if (item.days < 0) return `Quá hạn ${Math.abs(item.days)} ngày`;
  if (item.days === 0) return "Đến hạn hôm nay";
  if (item.days === 1) return "Đến hạn ngày mai";
  return `Còn ${item.days} ngày`;
};

const reminderStorageKey = computed(
  () => `ringnet_due_reminder_dismissed_${currentUserCode.value || "guest"}`,
);
const dueReminderOpen = ref(
  localStorage.getItem(reminderStorageKey.value) !== todayKey,
);
const closeDueReminder = () => {
  dueReminderOpen.value = false;
  localStorage.setItem(reminderStorageKey.value, todayKey);
};
const goToMyWork = () => {
  closeDueReminder();
  router.push("/my-work");
};

const openImportProject = () => {
  importProjectModalOpen.value = true;
  projectActionsMenu.value?.removeAttribute("open");
};

const columns = ref([
  { id: "todo", title: "Cần làm", color: "bg-slate-400" },
  { id: "in_progress", title: "Đang làm", color: "bg-amber-500" },
  { id: "done", title: "Hoàn thành", color: "bg-emerald-500" },
]);

const priorityRank = { high: 0, medium: 1, low: 2 };
const sortTasksForOverview = (first, second) => {
  const firstOverdue = first.deadlineState === "overdue" ? 0 : 1;
  const secondOverdue = second.deadlineState === "overdue" ? 0 : 1;
  if (firstOverdue !== secondOverdue) return firstOverdue - secondOverdue;

  const firstDays = first.dueDate ? daysUntil(first.dueDate) : 9999;
  const secondDays = second.dueDate ? daysUntil(second.dueDate) : 9999;
  if (firstDays !== secondDays) return firstDays - secondDays;

  return (priorityRank[first.priority] ?? 9) - (priorityRank[second.priority] ?? 9);
};
const getTasksByStatus = (status) =>
  overviewTasks.value.filter((task) => task.status === status);
const getVisibleTasksByStatus = (status) =>
  [...getTasksByStatus(status)]
    .sort(sortTasksForOverview);
const recentProjects = computed(() => overviewProjects.value.slice(0, 3));
const recentActivities = computed(() => overviewActivities.value.slice(0, 5));

const taskViewportFrame = Symbol("taskViewportFrame");
const taskViewportResizeHandler = Symbol("taskViewportResizeHandler");
const updateThreeCardViewport = (element) => {
  window.cancelAnimationFrame(element[taskViewportFrame]);
  element[taskViewportFrame] = window.requestAnimationFrame(() => {
    const cards = Array.from(
      element.querySelectorAll("[data-dashboard-task-card]"),
    );
    if (cards.length <= 3) {
      element.style.maxHeight = "";
      return;
    }

    const firstCard = cards[0];
    const thirdCard = cards[2];
    const viewportHeight =
      thirdCard.offsetTop + thirdCard.offsetHeight - firstCard.offsetTop;
    element.style.maxHeight = `${viewportHeight}px`;
  });
};
const vThreeCardScroll = {
  mounted(element) {
    const resizeHandler = () => updateThreeCardViewport(element);
    element[taskViewportResizeHandler] = resizeHandler;
    window.addEventListener("resize", resizeHandler);
    updateThreeCardViewport(element);
  },
  updated(element) {
    updateThreeCardViewport(element);
  },
  unmounted(element) {
    window.cancelAnimationFrame(element[taskViewportFrame]);
    window.removeEventListener(
      "resize",
      element[taskViewportResizeHandler],
    );
  },
};

const taskDragInProgress = ref(false);
let taskDragReleaseTimer;
const canContributeTask = (task) => Boolean(task?.canContribute);
const canDragTask = (event) =>
  canContributeTask(event.draggedContext?.element);
const onTaskDragStart = () => {
  window.clearTimeout(taskDragReleaseTimer);
  taskDragInProgress.value = true;
};
const onTaskDragEnd = () => {
  taskDragReleaseTimer = window.setTimeout(() => {
    taskDragInProgress.value = false;
  }, 0);
};
const onTaskChange = async (event, newStatus) => {
  if (!event.added) return;

  const task = event.added.element;
  if (!canContributeTask(task)) {
    notify("Bạn chỉ có quyền xem nhiệm vụ này.");
    await loadCompanyOverview({ silent: true });
    return;
  }

  const previousStatus = task.status;
  if (previousStatus === newStatus) return;

  task.status = newStatus;
  if (newStatus === "done") task.progress = 100;

  const updated = await moveTask(task.id, newStatus);
  if (!updated) {
    task.status = previousStatus;
    await loadCompanyOverview({ silent: true });
    return;
  }

  refreshCompanyOverview();
};

const openTask = (task) => {
  if (taskDragInProgress.value) return;
  if (task.canView) {
    activeTaskId.value = task.id;
    return;
  }
  notify("Bạn đang xem thông tin tổng quan; chi tiết nhiệm vụ chỉ dành cho người tham gia.");
};

const openProject = (project) => {
  if (project.canView) {
    router.push(`/projects/${project.id}`);
    return;
  }
  notify("Bạn đang xem thông tin tổng quan; chi tiết dự án chỉ dành cho thành viên dự án.");
};

const openAttentionItem = (item) => {
  if (item.type === "project") openProject(item.record);
  else openTask(item.record);
};

const activityTarget = (activity) => {
  if (activity.target_type === "Project") {
    return overviewProjects.value.find((project) => project.id === activity.target_code);
  }
  if (["Task", "TaskComment"].includes(activity.target_type)) {
    return overviewTasks.value.find((task) => task.id === activity.target_code);
  }
  return null;
};

const openActivity = (activity) => {
  const target = activityTarget(activity);
  if (!target) return;
  if (activity.target_type === "Project") openProject(target);
  else openTask(target);
};

const timeAgo = (dateStr) => {
  const diff = (new Date() - new Date(dateStr)) / 1000;
  if (diff < 60) return "Vừa xong";
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  return formatDate(dateStr);
};

</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
    >
      <div>
        <h1 class="text-2xl font-bold text-slate-900 mb-1">
          Xin chào, {{ currentUserName }} 👋
        </h1>
        <p v-if="!overviewLoading && !overviewError" class="text-slate-600 text-sm">
          Toàn công ty hiện có
          <strong class="font-semibold text-indigo-700">
            {{ overviewProjects.length }} dự án
          </strong>
          và
          <strong class="font-semibold text-rose-700">
            {{ overviewTasks.length }} nhiệm vụ
          </strong>.
        </p>
        <div v-else-if="overviewLoading" class="mt-2 flex items-center gap-2" aria-hidden="true">
          <span class="h-4 w-32 animate-pulse rounded bg-slate-200"></span>
          <span class="h-4 w-20 animate-pulse rounded bg-slate-200"></span>
        </div>
        <p v-else class="text-sm text-slate-600">Bảng điều hành chung của công ty.</p>
      </div>
      <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
        <button
          v-if="canCreateTasks"
          type="button"
          class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-200 sm:flex-none"
          @click="taskModalOpen = true"
        >
          <Plus class="h-5 w-5" /> Tạo nhiệm vụ
        </button>
        <button
          v-if="canManageProjects"
          type="button"
          @click="projectModalOpen = true"
          class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-violet-200 bg-white px-5 py-2.5 text-sm font-semibold text-violet-700 transition hover:border-violet-300 hover:bg-violet-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-100 sm:flex-none"
        >
          <FolderKanban class="h-5 w-5" /> Tạo dự án
        </button>
        <details
          v-if="canManageProjects"
          ref="projectActionsMenu"
          class="relative"
        >
          <summary
            class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200 [&::-webkit-details-marker]:hidden"
            aria-label="Thêm hành động dự án"
            title="Thêm hành động dự án"
          >
            <MoreHorizontal class="h-5 w-5" />
          </summary>
          <div
            class="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10"
          >
            <button
              type="button"
              class="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
              @click="openImportProject"
            >
              <UploadCloud class="h-4 w-4 text-slate-500" />
              Nhập dự án từ tệp
            </button>
          </div>
        </details>
      </div>
    </div>

    <div
      v-if="overviewLoading"
      class="space-y-6"
      aria-live="polite"
      aria-label="Đang tải Tổng quan"
    >
      <div class="h-20 animate-pulse rounded-2xl bg-white"></div>
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <div class="h-10 w-72 animate-pulse rounded-xl bg-white"></div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-for="index in 3" :key="index" class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="h-6 w-28 animate-pulse rounded-lg bg-slate-200"></div>
              <div v-for="card in 2" :key="card" class="h-32 animate-pulse rounded-xl bg-white"></div>
            </div>
          </div>
        </div>
        <div class="h-96 animate-pulse rounded-2xl bg-white"></div>
      </div>
    </div>

    <section
      v-else-if="overviewError"
      class="rounded-2xl border border-rose-200 bg-white px-6 py-10 text-center shadow-sm"
      role="alert"
    >
      <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 text-rose-600">
        <AlertTriangle class="h-6 w-6" />
      </span>
      <h2 class="mt-4 text-lg font-bold text-slate-900">Chưa tải được Tổng quan</h2>
      <p class="mt-1 text-sm text-slate-600">{{ overviewError }}</p>
      <button
        type="button"
        class="mx-auto mt-5 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white transition hover:bg-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-200"
        @click="loadCompanyOverview()"
      >
        <RefreshCw class="h-4 w-4" />
        Thử lại
      </button>
    </section>

    <template v-else>
      <section
        :class="[
          'flex flex-col gap-4 rounded-2xl border px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
          attentionItems.length
            ? attentionSummary.overdue
              ? 'border-rose-200 bg-rose-50/70'
              : 'border-amber-200 bg-amber-50/70'
            : 'border-emerald-200 bg-emerald-50/70',
        ]"
        aria-labelledby="attention-summary-title"
      >
        <div class="flex items-start gap-3">
          <span
            :class="[
              'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm',
              attentionItems.length
                ? attentionSummary.overdue
                  ? 'text-rose-600'
                  : 'text-amber-700'
                : 'text-emerald-600',
            ]"
          >
            <AlertTriangle v-if="attentionItems.length" class="h-5 w-5" />
            <CheckCircle2 v-else class="h-5 w-5" />
          </span>
          <div>
            <h2 id="attention-summary-title" class="font-bold text-slate-900">
              {{
                attentionItems.length
                  ? "Công ty có " + attentionItems.length + " mục cần chú ý"
                  : "Tiến độ chung đang ổn định"
              }}
            </h2>
            <p class="mt-0.5 text-sm text-slate-600">
              {{
                attentionItems.length
                  ? "Ưu tiên các mục quá hạn và đến hạn gần nhất."
                  : "Không có dự án hoặc nhiệm vụ nào cần cảnh báo trong 7 ngày tới."
              }}
            </p>
          </div>
        </div>

        <div v-if="attentionItems.length" class="flex flex-wrap items-center gap-2 sm:justify-end">
          <span v-if="attentionSummary.overdue" class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-rose-700 ring-1 ring-rose-200">
            {{ attentionSummary.overdue }} quá hạn
          </span>
          <span v-if="attentionSummary.today" class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-amber-800 ring-1 ring-amber-200">
            {{ attentionSummary.today }} hôm nay
          </span>
          <span v-if="attentionSummary.monitoring" class="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-blue-700 ring-1 ring-blue-200">
            {{ attentionSummary.monitoring }} cần theo dõi
          </span>
          <a href="#company-attention" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs font-bold text-slate-700 transition hover:bg-white/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-300">
            Xem ngay <ChevronRight class="h-4 w-4" />
          </a>
        </div>
      </section>

      <div class="grid grid-cols-1 items-start gap-8 2xl:grid-cols-3">
        <section class="order-2 2xl:order-1 2xl:col-span-2" aria-labelledby="company-task-board-title">
          <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 id="company-task-board-title" class="text-lg font-bold text-slate-900">
                Bảng nhiệm vụ toàn công ty
              </h2>
              <p class="text-sm text-slate-600">
                Ưu tiên nhiệm vụ quá hạn, gần đến hạn; kéo thả thẻ bạn có quyền xử lý để đổi trạng thái.
              </p>
            </div>
            <button
              type="button"
              class="flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
              @click="router.push('/tasks')"
            >
              Xem tất cả <ArrowUpRight class="h-4 w-4" />
            </button>
          </div>

          <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="col in columns"
              :key="col.id"
              class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4"
            >
              <header class="mb-4 flex items-center justify-between gap-2 px-1">
                <div class="flex items-center gap-2">
                  <span :class="['h-2.5 w-2.5 rounded-full', col.color]"></span>
                  <h3 class="font-bold text-slate-800">{{ col.title }}</h3>
                </div>
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                  {{ getTasksByStatus(col.id).length }}
                </span>
              </header>

              <div
                v-three-card-scroll
                :class="[
                  'min-h-32',
                  getTasksByStatus(col.id).length > 3
                    ? 'overflow-y-auto overscroll-contain pr-2 custom-scrollbar [scrollbar-gutter:stable]'
                    : '',
                ]"
              >
                <draggable
                  :list="getVisibleTasksByStatus(col.id)"
                  group="company-overview-tasks"
                  item-key="id"
                  ghost-class="opacity-40"
                  chosen-class="ring-2"
                  drag-class="rotate-1"
                  :move="canDragTask"
                  class="min-h-32 space-y-3"
                  @start="onTaskDragStart"
                  @end="onTaskDragEnd"
                  @change="onTaskChange($event, col.id)"
                >
                  <template #item="{ element: task }">
                    <div
                      data-dashboard-task-card
                      role="button"
                      tabindex="0"
                      :aria-label="'Mở nhiệm vụ ' + task.title"
                      :title="canContributeTask(task) ? 'Kéo thả để đổi trạng thái' : 'Bạn chỉ có quyền xem nhiệm vụ này'"
                      :class="[
                        'rounded-xl text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-200',
                        canContributeTask(task) ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer',
                      ]"
                      @click="openTask(task)"
                      @keydown.enter.prevent="openTask(task)"
                      @keydown.space.prevent="openTask(task)"
                    >
                      <TaskCard :task="task" :read-only="!canContributeTask(task)" />
                    </div>
                  </template>

                  <template #footer>
                    <div
                      v-if="getVisibleTasksByStatus(col.id).length === 0"
                      class="flex min-h-32 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white/70 px-4 py-6 text-center"
                    >
                      <ListTodo class="h-6 w-6 text-slate-400" />
                      <p class="mt-2 text-sm font-semibold text-slate-700">
                        Không có nhiệm vụ {{ col.title.toLowerCase() }}
                      </p>
                      <p class="mt-1 text-xs text-slate-500">Kéo nhiệm vụ có quyền xử lý vào đây.</p>
                    </div>
                  </template>
                </draggable>
              </div>

            </article>
          </div>
        </section>
        <aside
          id="company-attention"
          class="order-1 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm 2xl:order-2"
          aria-labelledby="company-attention-title"
        >
          <header class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
              <h2 id="company-attention-title" class="flex items-center gap-2 font-bold text-slate-900">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-amber-700 shadow-sm">
                  <AlertTriangle class="h-4 w-4" />
                </span>
                Công việc cần xử lý
              </h2>
              <span
                :class="[
                  'flex h-8 min-w-8 items-center justify-center rounded-full px-2 text-sm font-bold',
                  attentionItems.length
                    ? 'bg-rose-500 text-white'
                    : 'bg-emerald-100 text-emerald-700',
                ]"
              >
                {{ attentionItems.length }}
              </span>
            </div>
            <p class="ml-10 mt-1 text-xs text-slate-600">
              Quá hạn, hôm nay, rủi ro và hạn trong 7 ngày.
            </p>
          </header>

          <div v-if="attentionItems.length" class="max-h-[560px] space-y-2 overflow-y-auto p-4 custom-scrollbar">
            <button
              v-for="item in attentionItems"
              :key="item.key"
              type="button"
              :class="[
                'group flex min-h-20 w-full items-start gap-3 rounded-xl border bg-white p-3.5 text-left transition hover:shadow-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-100',
                attentionTone(item.level).border,
              ]"
              @click="openAttentionItem(item)"
            >
              <span
                :class="[
                  'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                  attentionTone(item.level).icon,
                ]"
              >
                <FolderKanban v-if="item.type === 'project'" class="h-4.5 w-4.5" />
                <CheckSquare v-else class="h-4.5 w-4.5" />
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    {{ item.typeLabel }}
                  </span>
                  <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', attentionTone(item.level).badge]">
                    {{ item.label }}
                  </span>
                </span>
                <span class="mt-1 block line-clamp-2 text-sm font-semibold leading-snug text-slate-900 group-hover:text-violet-700">
                  {{ item.title }}
                </span>
                <span class="mt-1 block truncate text-xs text-slate-500">{{ item.context }}</span>
              </span>
              <ChevronRight class="mt-2 h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-violet-600" />
            </button>
          </div>

          <div v-else class="px-5 py-10 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
              <CheckCircle2 class="h-6 w-6" />
            </span>
            <p class="mt-3 text-sm font-semibold text-slate-900">Chưa có mục cần xử lý</p>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">
              Công ty không có công việc quá hạn hoặc sắp đến hạn trong 7 ngày.
            </p>
          </div>
        </aside>
      </div>

      <div class="grid grid-cols-1 items-start gap-8 2xl:grid-cols-3">
        <section class="2xl:col-span-2" aria-labelledby="recent-projects-title">
          <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 id="recent-projects-title" class="text-lg font-bold text-slate-900">
                Dự án gần đây
              </h2>
              <p class="text-sm text-slate-600">Ba dự án được cập nhật gần nhất.</p>
            </div>
            <button
              type="button"
              class="flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
              @click="router.push('/projects')"
            >
              Tất cả dự án <ArrowUpRight class="h-4 w-4" />
            </button>
          </div>

          <div v-if="recentProjects.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <ProjectCard
              v-for="project in recentProjects"
              :key="project.id"
              :project="project"
              compact
              read-only
              :clickable="project.canView"
            />
          </div>
          <div
            v-else
            class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center"
          >
            <FolderKanban class="mx-auto h-8 w-8 text-slate-400" />
            <p class="mt-3 font-semibold text-slate-800">Chưa có dự án nào</p>
            <p class="mt-1 text-sm text-slate-500">Dự án mới sẽ xuất hiện tại đây.</p>
            <button
              v-if="canManageProjects"
              type="button"
              class="mt-4 min-h-10 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-200"
              @click="projectModalOpen = true"
            >
              Tạo dự án
            </button>
          </div>
        </section>
        <section
          class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
          aria-labelledby="recent-activity-title"
        >
          <header class="flex items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <div>
              <h2 id="recent-activity-title" class="flex items-center gap-2 font-bold text-slate-900">
                <Activity class="h-5 w-5 text-violet-600" />
                Hoạt động gần đây
              </h2>
              <p class="mt-1 text-xs text-slate-600">Năm thay đổi mới nhất trong công ty.</p>
            </div>
            <button
              type="button"
              class="flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg border border-violet-100 bg-white px-3 text-xs font-bold text-violet-700 transition hover:border-violet-200 hover:bg-violet-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200"
              @click="router.push('/activities')"
            >
              <History class="h-4 w-4" />
              Lịch sử
            </button>
          </header>

          <div v-if="recentActivities.length" class="divide-y divide-slate-100">
            <button
              v-for="activityItem in recentActivities"
              :key="activityItem.id"
              type="button"
              :disabled="!activityTarget(activityItem)"
              class="group flex min-h-20 w-full items-start gap-3 px-5 py-4 text-left transition enabled:hover:bg-slate-50 enabled:focus-visible:outline-none enabled:focus-visible:ring-4 enabled:focus-visible:ring-inset enabled:focus-visible:ring-violet-100 disabled:cursor-default"
              @click="openActivity(activityItem)"
            >
              <UserAvatar
                v-if="activityItem.actor?.id"
                :member-id="activityItem.actor.id"
                size="sm"
                :show-popover="false"
              />
              <span
                v-else
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-500 text-[10px] font-bold text-white"
              >
                {{ activityItem.actor?.initials || "HT" }}
              </span>
              <span class="min-w-0 flex-1">
                <span class="block text-sm leading-snug text-slate-600">
                  <strong class="font-semibold text-slate-900">
                    {{ activityItem.actor?.name || "Người dùng hệ thống" }}
                  </strong>
                  {{ activityItem.action }}
                  <strong class="font-semibold text-slate-800">{{ activityItem.target }}</strong>
                </span>
                <span v-if="activityItem.detail" class="mt-1 block line-clamp-2 text-xs text-slate-500">
                  {{ activityItem.detail }}
                </span>
                <span class="mt-1.5 block text-[11px] font-medium text-slate-500">
                  {{ timeAgo(activityItem.createdAt) }}
                </span>
              </span>
              <ChevronRight
                v-if="activityTarget(activityItem)"
                class="mt-2 h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-violet-600"
              />
            </button>
          </div>

          <div v-else class="px-5 py-10 text-center">
            <Activity class="mx-auto h-7 w-7 text-slate-400" />
            <p class="mt-3 text-sm font-semibold text-slate-800">Chưa có hoạt động nào</p>
            <p class="mt-1 text-xs text-slate-500">Các thay đổi mới sẽ xuất hiện tại đây.</p>
          </div>
        </section>
      </div>
    </template>
  </div>

  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="dueReminderOpen && dueReminderItems.length > 0"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm"
        role="presentation"
        @click.self="closeDueReminder"
      >
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby="due-reminder-title"
          class="w-full max-w-xl overflow-hidden rounded-3xl border border-white/60 bg-white shadow-2xl shadow-slate-950/20"
        >
          <header
            class="relative overflow-hidden bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 px-6 py-6 text-white"
          >
            <div class="absolute -right-10 -top-12 h-36 w-36 rounded-full bg-white/10"></div>
            <button
              type="button"
              class="absolute right-4 top-4 rounded-full p-2 text-white/75 transition hover:bg-white/15 hover:text-white"
              aria-label="Đóng thông báo"
              @click="closeDueReminder"
            >
              <X class="h-5 w-5" />
            </button>
            <div class="relative flex items-start gap-4 pr-8">
              <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25"
              >
                <BellRing class="h-6 w-6" />
              </span>
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-100">
                  Nhắc hạn công việc
                </p>
                <h2 id="due-reminder-title" class="mt-1 text-xl font-bold">
                  Bạn có {{ dueReminderItems.length }} công việc cần chú ý
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-indigo-100">
                  Hãy kiểm tra và hoàn thành sớm để bảo đảm tiến độ chung.
                </p>
              </div>
            </div>
          </header>

          <div class="max-h-[48vh] space-y-2 overflow-y-auto bg-slate-50/70 p-5 custom-scrollbar">
            <article
              v-for="item in dueReminderItems"
              :key="item.key"
              class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
            >
              <span
                :class="[
                  'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                  item.type === 'project'
                    ? 'bg-indigo-50 text-indigo-600'
                    : 'bg-rose-50 text-rose-600',
                ]"
              >
                <FolderKanban v-if="item.type === 'project'" class="h-5 w-5" />
                <CheckSquare v-else class="h-5 w-5" />
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                    {{ item.typeLabel }}
                  </span>
                  <span
                    :class="[
                      'rounded-full px-2 py-0.5 text-[10px] font-bold',
                      item.days < 0
                        ? 'bg-rose-50 text-rose-700'
                        : item.days === 0
                          ? 'bg-amber-50 text-amber-700'
                          : 'bg-blue-50 text-blue-700',
                    ]"
                  >
                    {{ dueLabel(item) }}
                  </span>
                </div>
                <p class="mt-1 truncate text-sm font-semibold text-slate-800">
                  {{ item.title }}
                </p>
                <p class="mt-0.5 text-xs text-slate-400">
                  Hạn chót: {{ formatDate(item.dueDate) }}
                </p>
              </div>
            </article>
          </div>

          <footer class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-white px-5 py-4 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
              @click="closeDueReminder"
            >
              Hủy
            </button>
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-200 transition hover:-translate-y-0.5 hover:shadow-lg"
              @click="goToMyWork"
            >
              Đến công việc của tôi
              <ArrowUpRight class="h-4 w-4" />
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e2e8f0;
  border-radius: 10px;
}
</style>

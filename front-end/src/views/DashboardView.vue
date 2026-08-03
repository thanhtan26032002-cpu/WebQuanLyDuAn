<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  FolderKanban,
  CheckCircle2,
  ArrowUpRight,
  Plus,
  MoreHorizontal,
  Activity,
  CalendarDays,
  UploadCloud,
  CheckSquare,
  ChevronRight,
  BellRing,
  X,
} from "@lucide/vue";
import draggable from "vuedraggable";
import ProjectCard from "../components/common/ProjectCard.vue";
import TaskCard from "../components/common/TaskCard.vue";
import { useProjectWorkspace } from "../composables/useProjectWorkspace";
import { apiFetch } from "../services/api";

const router = useRouter();
const {
  projects,
  tasks,
  currentUser,
  projectStatusMap,
  priorityMap,
  projectModalOpen,
  taskModalOpen,
  findProject,
  formatDate,
  activeTaskId,
  importProjectModalOpen,
  notify,
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
  canView: Boolean(task.can_view),
});

const mapCompanyActivity = (activity) => {
  const actorName = activity.user?.name || "Người dùng hệ thống";
  return {
    ...activity,
    id: activity.code,
    target: activity.target_label || "",
    createdAt: activity.created_at,
    actor: {
      name: actorName,
      initials: actorName.split(" ").filter(Boolean).map((part) => part[0]).join("").toUpperCase().slice(0, 2) || "HT",
      color: activity.user?.color || "slate",
    },
  };
};

const loadCompanyOverview = async () => {
  try {
    const response = await apiFetch("/api/company-overview");
    if (!response.ok) throw new Error("overview-request-failed");
    const payload = await response.json();
    companyProjects.value = (payload.projects || []).map(mapCompanyProject);
    companyTasks.value = (payload.tasks || []).map(mapCompanyTask);
    overviewActivities.value = (payload.activities || []).map(mapCompanyActivity);
  } catch {
    notify("Không thể tải Tổng quan toàn công ty. Vui lòng thử lại.");
  }
};

let overviewRefreshTimer;
const refreshCompanyOverview = () => {
  window.clearTimeout(overviewRefreshTimer);
  overviewRefreshTimer = window.setTimeout(loadCompanyOverview, 300);
};
onMounted(() => {
  loadCompanyOverview();
  window.addEventListener("ringnet:activity-changed", refreshCompanyOverview);
});
onUnmounted(() => {
  window.removeEventListener("ringnet:activity-changed", refreshCompanyOverview);
  window.clearTimeout(overviewRefreshTimer);
});

const todayTasks = computed(() =>
  overviewTasks.value.filter(
    (task) =>
      Boolean(task.dueDate) &&
      getLocalDateKey(task.dueDate) === todayKey &&
      task.status !== "done",
  ),
);
const todayProjects = computed(() =>
  overviewProjects.value.filter(
    (project) =>
      Boolean(project.dueDate) &&
      getLocalDateKey(project.dueDate) === todayKey &&
      project.status !== "completed",
  ),
);
const todayDueCount = computed(
  () => todayTasks.value.length + todayProjects.value.length,
);

const DAY_IN_MILLISECONDS = 24 * 60 * 60 * 1000;
const toUtcDate = (dateValue) => {
  const [year, month, day] = getLocalDateKey(dateValue)
    .split("-")
    .map(Number);
  return Date.UTC(year, month - 1, day);
};
const daysUntil = (dateValue) =>
  Math.round((toUtcDate(dateValue) - toUtcDate(todayKey)) / DAY_IN_MILLISECONDS);

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

const columns = ref([
  { id: "todo", title: "Cần làm", color: "bg-slate-400" },
  { id: "in_progress", title: "Đang làm", color: "bg-amber-500" },
  { id: "done", title: "Hoàn thành", color: "bg-emerald-500" },
]);

const getTasksByStatus = (status) => {
  return overviewTasks.value.filter((t) => t.status === status);
};

const openTask = (task) => {
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

const timeAgo = (dateStr) => {
  const diff = (new Date() - new Date(dateStr)) / 1000;
  if (diff < 60) return "Vừa xong";
  if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
  return formatDate(dateStr);
};

const showRecentActivities = ref(false);
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
        <p class="text-slate-500 text-sm">
          Toàn công ty hiện có
          <strong class="font-semibold text-indigo-700">
            {{ overviewProjects.length }} dự án
          </strong>
          và
          <strong class="font-semibold text-rose-700">
            {{ overviewTasks.length }} nhiệm vụ
          </strong>.
        </p>
      </div>
      <div v-if="canManageProjects" class="flex items-center gap-3 shrink-0">
        <button
          @click="importProjectModalOpen = true"
          class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-medium hover:bg-slate-50 transition-all flex items-center gap-2"
        >
          <UploadCloud class="w-5 h-5" /> Tải dự án lên
        </button>
        <button
          @click="projectModalOpen = true"
          class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-medium hover:shadow-premium transition-all shadow-md shadow-violet-500/25 flex items-center gap-2"
        >
          <Plus class="w-5 h-5" /> Tạo dự án mới
        </button>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column: Kanban -->
      <div class="lg:col-span-2 space-y-8">
        <div>
          <div class="flex items-end justify-between mb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">
                Bảng nhiệm vụ toàn công ty
              </h2>
              <p class="text-sm text-slate-500">
                Theo dõi chung; cập nhật chi tiết tại tab Nhiệm vụ
              </p>
            </div>
            <button
              @click="router.push('/tasks')"
              class="text-violet-600 font-medium text-sm hover:text-violet-700 flex items-center"
            >
              Xem tất cả <ArrowUpRight class="w-4 h-4 ml-1" />
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="col in columns"
              :key="col.id"
              class="bg-slate-50 rounded-2xl p-4 border-2 border-slate-200 flex flex-col h-[500px]"
            >
              <div class="flex items-center justify-between mb-4 px-1">
                <div class="flex items-center gap-2">
                  <div :class="['w-2.5 h-2.5 rounded-full', col.color]"></div>
                  <h3 class="font-bold text-slate-700">{{ col.title }}</h3>
                  <span
                    class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-full"
                  >
                    {{ getTasksByStatus(col.id).length }}
                  </span>
                </div>
              </div>

              <div class="flex-1 overflow-y-auto custom-scrollbar">
                <draggable
                  class="min-h-full space-y-3 pb-4 pr-1"
                  :list="getTasksByStatus(col.id)"
                  group="tasks"
                  item-key="id"
                  :disabled="true"
                  ghost-class="opacity-50"
                >
                  <template #item="{ element }">
                    <div @click="openTask(element)">
                      <TaskCard :task="element" read-only />
                    </div>
                  </template>
                </draggable>
              </div>

              <button
                v-if="canCreateTasks"
                @click="taskModalOpen = true"
                class="w-full mt-2 py-2 flex items-center justify-center gap-2 text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 rounded-xl transition-colors font-medium text-sm"
              >
                <Plus class="w-4 h-4" /> Thêm thẻ
              </button>
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-end justify-between mb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">
                Dự án toàn công ty gần đây
              </h2>
              <p class="text-sm text-slate-500">
                Các dự án gần đây đang hoạt động
              </p>
            </div>
            <button
              @click="router.push('/projects')"
              class="text-violet-600 font-medium text-sm hover:text-violet-700 flex items-center"
            >
              Tất cả dự án <ArrowUpRight class="w-4 h-4 ml-1" />
            </button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <ProjectCard
              v-for="project in overviewProjects.slice(0, 2)"
              :key="project.id"
              :project="project"
              read-only
              :clickable="project.canView"
            />
          </div>
        </div>
      </div>

      <!-- Right Column: Widgets -->
      <div class="space-y-8">
        <!-- Today's Due Items Widget -->
        <div
          class="bg-white rounded-2xl border border-amber-100 shadow-sm overflow-hidden"
        >
          <div
            class="px-5 py-4 border-b border-amber-100 bg-gradient-to-r from-amber-50 to-rose-50 flex items-center justify-between gap-3"
          >
            <div>
              <h2 class="font-bold text-slate-900 flex items-center gap-2">
                <span
                  class="w-8 h-8 rounded-lg bg-white/80 shadow-sm flex items-center justify-center"
                >
                  <CalendarDays class="w-4.5 h-4.5 text-rose-500" />
                </span>
                Đến hạn hôm nay toàn công ty
              </h2>
              <p class="text-xs text-slate-500 mt-1 ml-10">
                Ưu tiên xử lý trước khi kết thúc ngày
              </p>
            </div>
            <span
              class="min-w-8 h-8 px-2 bg-rose-500 text-white text-sm font-bold rounded-full flex items-center justify-center shadow-sm shadow-rose-200"
              >{{ todayDueCount }}</span
            >
          </div>

          <div class="p-5">
            <div
              v-if="todayDueCount > 0"
              class="space-y-5 max-h-[480px] overflow-y-auto custom-scrollbar pr-1"
            >
              <section v-if="todayProjects.length > 0">
                <div class="flex items-center justify-between mb-2.5">
                  <h3
                    class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-1.5"
                  >
                    <FolderKanban class="w-4 h-4" />
                    Dự án
                  </h3>
                  <span
                    class="bg-indigo-50 text-indigo-700 text-[11px] font-bold px-2 py-0.5 rounded-full"
                  >
                    {{ todayProjects.length }}
                  </span>
                </div>

                <div class="space-y-2">
                  <button
                    v-for="project in todayProjects"
                    :key="`project-${project.id}`"
                    type="button"
                    class="group w-full text-left p-3.5 bg-indigo-50/60 rounded-xl border border-indigo-100 hover:bg-indigo-50 hover:border-indigo-200 hover:shadow-sm transition-all"
                    @click="openProject(project)"
                  >
                    <span class="flex items-start gap-3">
                      <span
                        class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"
                      >
                        <FolderKanban class="w-4.5 h-4.5" />
                      </span>
                      <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-2 mb-1">
                          <span
                            class="px-2 py-0.5 bg-indigo-600 text-white rounded text-[10px] font-bold uppercase tracking-wide"
                          >
                            Dự án
                          </span>
                          <span class="text-[11px] font-medium text-indigo-600">
                            {{ projectStatusMap[project.status]?.label }}
                          </span>
                        </span>
                        <span
                          class="block font-semibold text-slate-900 text-sm leading-snug group-hover:text-indigo-700 transition-colors"
                        >
                          {{ project.name }}
                        </span>
                        <span class="block text-xs text-slate-500 mt-1">
                          Tiến độ hiện tại: {{ project.progress || 0 }}%
                        </span>
                      </span>
                      <ChevronRight
                        class="w-4 h-4 text-indigo-300 group-hover:text-indigo-600 mt-2 transition-colors shrink-0"
                      />
                    </span>
                  </button>
                </div>
              </section>

              <section v-if="todayTasks.length > 0">
                <div class="flex items-center justify-between mb-2.5">
                  <h3
                    class="text-[11px] font-bold uppercase tracking-wider text-rose-700 flex items-center gap-1.5"
                  >
                    <CheckSquare class="w-4 h-4" />
                    Nhiệm vụ
                  </h3>
                  <span
                    class="bg-rose-50 text-rose-700 text-[11px] font-bold px-2 py-0.5 rounded-full"
                  >
                    {{ todayTasks.length }}
                  </span>
                </div>

                <div class="space-y-2">
                  <button
                    v-for="task in todayTasks"
                    :key="`task-${task.id}`"
                    type="button"
                    class="group w-full text-left p-3.5 bg-rose-50/60 rounded-xl border border-rose-100 hover:bg-rose-50 hover:border-rose-200 hover:shadow-sm transition-all"
                    @click="openTask(task)"
                  >
                    <span class="flex items-start gap-3">
                      <span
                        class="w-9 h-9 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center shrink-0"
                      >
                        <CheckSquare class="w-4.5 h-4.5" />
                      </span>
                      <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-2 mb-1">
                          <span
                            class="px-2 py-0.5 bg-rose-500 text-white rounded text-[10px] font-bold uppercase tracking-wide"
                          >
                            Nhiệm vụ
                          </span>
                          <span class="text-[11px] font-medium text-rose-600">
                            Ưu tiên
                            {{
                              priorityMap[
                                task.priority
                              ]?.label?.toLowerCase() || "trung bình"
                            }}
                          </span>
                        </span>
                        <span
                          class="block font-semibold text-slate-900 text-sm leading-snug group-hover:text-rose-700 transition-colors"
                        >
                          {{ task.title }}
                        </span>
                        <span
                          class="block text-xs text-slate-500 mt-1 truncate"
                        >
                          {{
                            task.projectName || findProject(task.projectId)?.name ||
                            "Không thuộc dự án"
                          }}
                        </span>
                      </span>
                      <ChevronRight
                        class="w-4 h-4 text-rose-300 group-hover:text-rose-600 mt-2 transition-colors shrink-0"
                      />
                    </span>
                  </button>
                </div>
              </section>
            </div>

            <div v-else class="text-center py-6">
              <div
                class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-3"
              >
                <CheckCircle2 class="w-6 h-6 text-emerald-500" />
              </div>
              <p class="text-sm font-medium text-slate-900 mb-1">
                Không có hạn chót hôm nay
              </p>
              <p class="text-xs text-slate-500">
                Toàn công ty không có dự án hoặc nhiệm vụ nào cần hoàn thành trong ngày.
              </p>
            </div>
          </div>
        </div>

        <!-- Activity Feed Widget -->
        <div
          class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col h-[500px]"
        >
          <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <h2 class="font-bold text-slate-900 flex items-center gap-2">
              <Activity class="w-5 h-5 text-violet-500" />
              Hoạt động toàn công ty gần đây
            </h2>
            <button
              @click="showRecentActivities = !showRecentActivities"
              class="text-xs font-semibold text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-2.5 py-1 rounded-lg transition-colors cursor-pointer shadow-2xs"
            >
              {{ showRecentActivities ? 'Ẩn' : 'Xem' }}
            </button>
          </div>

          <div class="p-5 flex-1 overflow-y-auto custom-scrollbar">
            <div
              v-if="showRecentActivities && overviewActivities.length"
              class="relative border-l border-slate-200 ml-4 space-y-6 pb-4"
            >
              <div
                v-for="activity in overviewActivities.slice(0, 8)"
                :key="activity.id"
                class="relative pl-6"
              >
                <!-- Timeline dot -->
                <div class="absolute -left-[17px] top-1">
                  <div
                    :class="[
                      'w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white shadow-sm ring-4 ring-white',
                      `bg-${activity.actor?.color || 'slate'}-500`,
                    ]"
                  >
                    {{ activity.actor?.initials || "HT" }}
                  </div>
                </div>

                <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                  <p class="text-sm text-slate-600 leading-snug">
                    <span class="font-bold text-slate-900">{{
                      activity.actor?.name || "Người dùng hệ thống"
                    }}</span>
                    {{ activity.action }}
                    <span class="font-medium text-slate-900">{{
                      activity.target
                    }}</span>
                  </p>
                  <p
                    v-if="activity.detail"
                    class="text-xs text-slate-500 mt-1 italic"
                  >
                    "{{ activity.detail }}"
                  </p>
                  <p
                    class="text-[10px] font-medium text-slate-400 mt-2 uppercase tracking-wide"
                  >
                    {{ timeAgo(activity.createdAt) }}
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8">
              <p class="text-slate-500 text-sm">
                Chưa có hoạt động nào (hoặc đang ẩn).
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
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

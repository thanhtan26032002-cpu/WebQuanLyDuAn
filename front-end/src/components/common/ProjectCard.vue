<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import {
  AlertTriangle,
  Building2,
  CalendarDays,
  CheckCircle2,
  CirclePause,
  ClipboardList,
  MoreHorizontal,
  ShieldCheck,
  UserRound,
  Users,
} from "@lucide/vue";
import { useProjectWorkspace } from "../../composables/useProjectWorkspace";

const props = defineProps({
  project: { type: Object, required: true },
  compact: Boolean,
  readOnly: Boolean,
  clickable: { type: Boolean, default: true },
});

const router = useRouter();
const {
  projectStatusMap,
  findMember,
  formatDate,
  getTaskDeadlineState,
  projectSettingsModalOpen,
  editingProjectId,
  currentUser,
} = useProjectWorkspace();

const canEditProject = computed(() => {
  if (props.readOnly) return false;
  const role = currentUser.value?.role;
  const userCode = currentUser.value?.code;
  return role === "admin" ||
    (["project_manager", "manager"].includes(role) &&
      [props.project.created_by, props.project.managerId].includes(userCode));
});
const handleEdit = () => {
  if (!canEditProject.value) return;
  editingProjectId.value = props.project.id;
  projectSettingsModalOpen.value = true;
};
const openProject = () => {
  if (props.clickable) router.push(`/projects/${props.project.id}`);
};

const deadlineState = computed(() =>
  props.project.deadlineState || getTaskDeadlineState(props.project.dueDate, props.project.status),
);
const memberIds = computed(() => Array.isArray(props.project.memberIds) ? props.project.memberIds : []);
const memberCount = computed(() => props.project.memberCount ?? memberIds.value.length);
const taskCount = computed(() => props.project.tasks_count ?? props.project.taskCount ?? null);
const managerName = computed(() =>
  props.project.manager?.name || findMember(props.project.managerId)?.name || "Chưa phân công",
);

const visualState = computed(() => {
  if (deadlineState.value === "completed_late") return {
    label: `Hoàn thành trễ ${props.project.lateDays || 0} ngày`,
    detail: "Đã hoàn thành nhưng vượt cam kết ban đầu.",
    icon: AlertTriangle,
    accent: "bg-amber-500",
    badge: "border-amber-200 bg-amber-50 text-amber-700",
    panel: "border-amber-100 bg-amber-50/70 text-amber-800",
    progress: "bg-amber-500",
    issue: true,
  };
  if (props.project.status === "completed") return {
    label: "Đã hoàn thành",
    detail: "Dự án đã được đóng đúng quy trình.",
    icon: CheckCircle2,
    accent: "bg-emerald-500",
    badge: "border-emerald-200 bg-emerald-50 text-emerald-700",
    panel: "border-emerald-100 bg-emerald-50/70 text-emerald-800",
    progress: "bg-emerald-500",
    issue: false,
  };
  if (deadlineState.value === "overdue" || props.project.health === "off_track") return {
    label: deadlineState.value === "overdue" ? `Quá hạn ${props.project.overdueDays || 0} ngày` : "Chậm tiến độ",
    detail: props.project.delayReason || "Cần cập nhật nguyên nhân và kế hoạch khắc phục.",
    icon: AlertTriangle,
    accent: "bg-rose-500",
    badge: "border-rose-200 bg-rose-50 text-rose-700",
    panel: "border-rose-100 bg-rose-50/70 text-rose-800",
    progress: "bg-rose-500",
    issue: true,
  };
  if (props.project.status === "on_hold") return {
    label: "Đang tạm dừng",
    detail: "Dự án đang chờ điều kiện để tiếp tục triển khai.",
    icon: CirclePause,
    accent: "bg-amber-500",
    badge: "border-amber-200 bg-amber-50 text-amber-700",
    panel: "border-amber-100 bg-amber-50/70 text-amber-800",
    progress: "bg-amber-500",
    issue: true,
  };
  if (props.project.health === "at_risk" || deadlineState.value === "due") return {
    label: deadlineState.value === "due" ? "Đến hạn hôm nay" : "Có rủi ro",
    detail: "Cần theo dõi sát để tránh ảnh hưởng cam kết.",
    icon: AlertTriangle,
    accent: "bg-amber-500",
    badge: "border-amber-200 bg-amber-50 text-amber-700",
    panel: "border-amber-100 bg-amber-50/70 text-amber-800",
    progress: "bg-amber-500",
    issue: true,
  };
  if (props.project.status === "planning") return {
    label: "Đang lập kế hoạch",
    detail: "Dự án chưa bắt đầu triển khai.",
    icon: ClipboardList,
    accent: "bg-slate-400",
    badge: "border-slate-200 bg-slate-50 text-slate-600",
    panel: "border-slate-100 bg-slate-50 text-slate-600",
    progress: "bg-slate-500",
    issue: false,
  };
  return {
    label: "Đang ổn định",
    detail: "Tiến độ hiện tại chưa ghi nhận vấn đề.",
    icon: ShieldCheck,
    accent: "bg-emerald-500",
    badge: "border-emerald-200 bg-emerald-50 text-emerald-700",
    panel: "border-emerald-100 bg-emerald-50/70 text-emerald-800",
    progress: "bg-emerald-500",
    issue: false,
  };
});
</script>

<template>
  <article
    :class="[
      'relative flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-200',
      visualState.issue ? visualState.panel.split(' ')[0] : 'border-slate-200',
      clickable ? 'cursor-pointer hover:border-violet-200 hover:shadow-md' : 'cursor-default',
    ]"
    :role="clickable ? 'button' : undefined"
    :tabindex="clickable ? 0 : undefined"
    :aria-label="clickable ? 'Mở dự án ' + project.name : undefined"
    @click="openProject"
    @keydown.enter.prevent="openProject()"
    @keydown.space.prevent="openProject()"
  >
    <div :class="['h-1.5 w-full', visualState.accent]"></div>

    <div :class="['flex flex-1 flex-col', compact ? 'p-4' : 'p-5']">
      <header class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
          <div class="mb-2 flex flex-wrap items-center gap-2">
            <span :class="['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold', visualState.badge]">
              <component :is="visualState.icon" class="h-3.5 w-3.5" />
              {{ visualState.label }}
            </span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
              {{ projectStatusMap[project.status]?.label || project.status }}
            </span>
          </div>
          <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">{{ project.name }}</h3>
          <p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ project.id }}</p>
        </div>
        <button v-if="canEditProject" class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click.stop="handleEdit" title="Chỉnh sửa dự án">
          <MoreHorizontal class="h-4 w-4" />
        </button>
      </header>

      <div
        v-if="visualState.issue || !compact"
        :class="['mt-4 flex items-start gap-2 rounded-xl border px-3 py-2.5 text-xs leading-relaxed', visualState.panel]"
      >
        <component :is="visualState.icon" class="mt-0.5 h-4 w-4 shrink-0" />
        <span class="line-clamp-2">{{ visualState.detail }}</span>
      </div>

      <p v-if="!compact" class="mt-4 line-clamp-2 min-h-10 text-sm leading-relaxed text-slate-600">{{ project.description || "Chưa có mô tả dự án." }}</p>

      <div :class="['mt-4 grid gap-2 text-xs', compact ? 'grid-cols-1' : 'grid-cols-2']">
        <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-slate-500"><UserRound class="h-3.5 w-3.5" /> Phụ trách</span>
          <p class="mt-1 truncate font-bold text-slate-700">{{ managerName }}</p>
        </div>
        <div v-if="!compact" class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-slate-500"><Building2 class="h-3.5 w-3.5" /> Khách hàng</span>
          <p class="mt-1 truncate font-bold text-slate-700">{{ project.customer?.name || "Chưa cập nhật" }}</p>
        </div>
      </div>

      <div class="mt-4">
        <div class="mb-1.5 flex items-center justify-between text-xs">
          <span class="font-semibold text-slate-500">Tiến độ tổng thể</span>
          <strong class="text-slate-900">{{ project.progress || 0 }}%</strong>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
          <div :class="['h-full rounded-full transition-all duration-700', visualState.progress]" :style="{ width: `${project.progress || 0}%` }"></div>
        </div>
      </div>

      <footer class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
        <div class="flex items-center gap-3">
          <span class="flex items-center gap-1.5" :title="`${memberCount} thành viên`"><Users class="h-3.5 w-3.5" /> {{ memberCount }}</span>
          <span v-if="taskCount !== null" class="flex items-center gap-1.5" :title="`${taskCount} nhiệm vụ`"><ClipboardList class="h-3.5 w-3.5" /> {{ taskCount }}</span>
          <span v-if="project.contextLabel" class="font-semibold text-indigo-600">{{ project.contextLabel }}</span>
        </div>
        <span :class="['flex items-center gap-1.5 font-semibold', deadlineState === 'overdue' ? 'text-rose-600' : deadlineState === 'due' ? 'text-amber-700' : 'text-slate-500']">
          <CalendarDays class="h-3.5 w-3.5" />
          {{ deadlineState === "overdue" ? `Quá hạn ${project.overdueDays || 0} ngày` : deadlineState === "completed_late" ? `Trễ ${project.lateDays || 0} ngày` : project.dueDate ? formatDate(project.dueDate) : "Chưa đặt hạn" }}
        </span>
      </footer>
    </div>
  </article>
</template>

<script setup>
import { computed } from "vue";
import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  CircleDashed,
  FolderKanban,
  ShieldCheck,
  Timer,
  UserRound,
} from "@lucide/vue";
import { useProjectWorkspace } from "../../composables/useProjectWorkspace";
import UserAvatar from "./UserAvatar.vue";

const props = defineProps({
  task: { type: Object, required: true },
  showStatus: Boolean,
  readOnly: Boolean,
});

const {
  priorityMap,
  findProject,
  findMember,
  formatDate,
  getTaskDeadlineState,
} = useProjectWorkspace();

const project = computed(() => findProject(props.task.projectId));
const assignee = computed(() =>
  props.task.assigneeId
    ? props.task.assignee || findMember(props.task.assigneeId)
    : null,
);
const assigneeName = computed(() => assignee.value?.name || "Chưa phân công");
const projectName = computed(
  () => props.task.projectName || project.value?.name || "Nhiệm vụ độc lập",
);
const isCompleted = computed(() =>
  ["done", "completed"].includes(props.task.status),
);
const progressValue = computed(() => {
  if (isCompleted.value) return 100;
  return Math.min(100, Math.max(0, Number(props.task.progress || 0)));
});
const remainingProgress = computed(() => Math.max(0, 100 - progressValue.value));

const deadlineState = computed(() =>
  props.task.deadlineState ||
  getTaskDeadlineState(props.task.dueDate, props.task.status),
);

const daysUntilDue = computed(() => {
  const dueDateKey = String(props.task.dueDate || "").split("T")[0];
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dueDateKey)) return null;

  const dueDate = new Date(`${dueDateKey}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.round((dueDate.getTime() - today.getTime()) / 86400000);
});
const overdueDays = computed(() =>
  Number(props.task.overdueDays) ||
  (daysUntilDue.value !== null && daysUntilDue.value < 0
    ? Math.abs(daysUntilDue.value)
    : 0),
);
const showRecoveryState = computed(
  () =>
    !isCompleted.value &&
    (deadlineState.value === "overdue" || Boolean(props.task.delayReason)),
);

const visualState = computed(() => {
  if (deadlineState.value === "completed_late") {
    return {
      issue: true,
      level: "warning",
      label: `Hoàn thành trễ ${props.task.lateDays || 0} ngày`,
      detail: "Nhiệm vụ đã hoàn thành nhưng vượt thời hạn cam kết.",
      icon: AlertTriangle,
      accent: "bg-amber-500",
      border: "border-amber-200",
      badge: "border-amber-200 bg-amber-50 text-amber-800",
      panel: "border-amber-100 bg-amber-50/70 text-amber-800",
      progress: "bg-amber-500",
    };
  }

  if (isCompleted.value) {
    return {
      issue: false,
      level: "completed",
      label: "Đã hoàn thành",
      detail: "Nhiệm vụ đã được hoàn tất.",
      icon: CheckCircle2,
      accent: "bg-emerald-500",
      border: "border-emerald-100",
      badge: "border-emerald-200 bg-emerald-50 text-emerald-700",
      panel: "border-emerald-100 bg-emerald-50/70 text-emerald-800",
      progress: "bg-emerald-500",
    };
  }

  if (props.task.isBlocked) {
    return {
      issue: true,
      level: "danger",
      label: "Đang bị chặn",
      detail:
        props.task.blockedReason ||
        "Nhiệm vụ đang có trở ngại nhưng chưa cập nhật nguyên nhân.",
      icon: AlertTriangle,
      accent: "bg-rose-500",
      border: "border-rose-200",
      badge: "border-rose-200 bg-rose-50 text-rose-700",
      panel: "border-rose-100 bg-rose-50/70 text-rose-800",
      progress: "bg-rose-500",
    };
  }

  if (deadlineState.value === "overdue") {
    return {
      issue: true,
      level: "danger",
      label: `Quá hạn ${overdueDays.value} ngày`,
      detail:
        props.task.delayReason ||
        "Đã quá hạn nhưng chưa cập nhật lý do chậm và kế hoạch khắc phục.",
      icon: AlertTriangle,
      accent: "bg-rose-500",
      border: "border-rose-200",
      badge: "border-rose-200 bg-rose-50 text-rose-700",
      panel: "border-rose-100 bg-rose-50/70 text-rose-800",
      progress: "bg-rose-500",
    };
  }

  if (props.task.delayReason) {
    return {
      issue: true,
      level: "danger",
      label: "Có vấn đề tiến độ",
      detail: props.task.delayReason,
      icon: AlertTriangle,
      accent: "bg-rose-500",
      border: "border-rose-200",
      badge: "border-rose-200 bg-rose-50 text-rose-700",
      panel: "border-rose-100 bg-rose-50/70 text-rose-800",
      progress: "bg-rose-500",
    };
  }

  if (!props.task.assigneeId) {
    return {
      issue: true,
      level: "warning",
      label: "Chưa phân công",
      detail: "Nhiệm vụ chưa có người chịu trách nhiệm thực hiện.",
      icon: UserRound,
      accent: "bg-amber-500",
      border: "border-amber-200",
      badge: "border-amber-200 bg-amber-50 text-amber-800",
      panel: "border-amber-100 bg-amber-50/70 text-amber-800",
      progress: "bg-amber-500",
    };
  }

  if (deadlineState.value === "due") {
    return {
      issue: true,
      level: "warning",
      label: "Đến hạn hôm nay",
      detail: `Còn ${remainingProgress.value}% khối lượng cần hoàn thành trong hôm nay.`,
      icon: Timer,
      accent: "bg-amber-500",
      border: "border-amber-200",
      badge: "border-amber-200 bg-amber-50 text-amber-800",
      panel: "border-amber-100 bg-amber-50/70 text-amber-800",
      progress: "bg-amber-500",
    };
  }

  if (
    daysUntilDue.value !== null &&
    daysUntilDue.value > 0 &&
    daysUntilDue.value <= 3
  ) {
    return {
      issue: true,
      level: "warning",
      label: `Còn ${daysUntilDue.value} ngày`,
      detail: `Sắp đến hạn · đã hoàn thành ${progressValue.value}% công việc.`,
      icon: Timer,
      accent: "bg-amber-500",
      border: "border-amber-200",
      badge: "border-amber-200 bg-amber-50 text-amber-800",
      panel: "border-amber-100 bg-amber-50/70 text-amber-800",
      progress: "bg-amber-500",
    };
  }

  if (!props.task.dueDate) {
    return {
      issue: true,
      level: "warning",
      label: "Chưa đặt thời hạn",
      detail: "Cần bổ sung hạn hoàn thành để theo dõi tiến độ chính xác.",
      icon: CalendarDays,
      accent: "bg-amber-500",
      border: "border-amber-200",
      badge: "border-amber-200 bg-amber-50 text-amber-800",
      panel: "border-amber-100 bg-amber-50/70 text-amber-800",
      progress: "bg-amber-500",
    };
  }

  if (props.task.status === "in_progress") {
    return {
      issue: false,
      level: "healthy",
      label: "Đang ổn định",
      detail: "Tiến độ hiện tại chưa ghi nhận vấn đề.",
      icon: ShieldCheck,
      accent: "bg-emerald-500",
      border: "border-slate-200",
      badge: "border-emerald-200 bg-emerald-50 text-emerald-700",
      panel: "border-emerald-100 bg-emerald-50/70 text-emerald-800",
      progress: "bg-emerald-500",
    };
  }

  return {
    issue: false,
    level: "ready",
    label: "Sẵn sàng thực hiện",
    detail: "Nhiệm vụ đã đủ thông tin để bắt đầu.",
    icon: CircleDashed,
    accent: "bg-slate-400",
    border: "border-slate-200",
    badge: "border-slate-200 bg-slate-50 text-slate-700",
    panel: "border-slate-100 bg-slate-50 text-slate-700",
    progress: "bg-slate-500",
  };
});

const progressCaption = computed(() => {
  if (isCompleted.value) return "Đã hoàn tất";
  if (progressValue.value === 0) return "Chưa bắt đầu";
  return "Đang thực hiện";
});

const statusBorderClass = computed(() => {
  if (isCompleted.value) {
    return "border-emerald-300";
  }
  if (props.task.status === "in_progress") {
    return "border-amber-300";
  }
  return "border-slate-300";
});

const statusAccentClass = computed(() => {
  if (isCompleted.value) {
    return "bg-emerald-500";
  }
  if (props.task.status === "in_progress") {
    return "bg-amber-500";
  }
  return "bg-slate-400";
});

const deadlineClass = computed(() => {
  if (deadlineState.value === "overdue") {
    return "border-rose-200 bg-rose-50 text-rose-700";
  }
  if (
    deadlineState.value === "due" ||
    deadlineState.value === "completed_late" ||
    (daysUntilDue.value !== null && daysUntilDue.value <= 3)
  ) {
    return "border-amber-200 bg-amber-50 text-amber-800";
  }
  if (!props.task.dueDate) {
    return "border-slate-200 bg-slate-50 text-slate-500";
  }
  return "border-transparent bg-transparent text-slate-600";
});

const deadlineText = computed(() => {
  if (deadlineState.value === "overdue") {
    return `Quá hạn ${overdueDays.value} ngày`;
  }
  if (deadlineState.value === "completed_late") {
    return `Trễ ${props.task.lateDays || 0} ngày`;
  }
  if (deadlineState.value === "due") return "Hạn hôm nay";
  return props.task.dueDate ? formatDate(props.task.dueDate) : "Chưa đặt hạn";
});

const priorityBadgeMap = {
  high: "border-rose-200 bg-rose-50 text-rose-700",
  medium: "border-amber-200 bg-amber-50 text-amber-800",
  low: "border-sky-200 bg-sky-50 text-sky-700",
};
</script>

<template>
  <article
    :class="[
      'group relative overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-200',
      statusBorderClass,
      readOnly
        ? 'cursor-pointer hover:shadow-md'
        : 'cursor-grab active:cursor-grabbing',
    ]"
    :data-status="task.status"
  >
    <div :class="['h-1.5 w-full', statusAccentClass]"></div>

    <div class="p-4">
      <header class="flex items-start justify-between gap-2">
        <span
          :class="[
            'inline-flex min-w-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold',
            visualState.badge,
          ]"
        >
          <component :is="visualState.icon" class="h-3.5 w-3.5 shrink-0" />
          <span class="truncate">{{ visualState.label }}</span>
        </span>
        <span
          :class="[
            'shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold',
            priorityBadgeMap[task.priority] || priorityBadgeMap.medium,
          ]"
        >
          {{ priorityMap[task.priority]?.label || "Trung bình" }}
        </span>
      </header>

      <h3 class="mt-3 line-clamp-2 text-[15px] font-bold leading-snug text-slate-900">
        {{ task.title }}
      </h3>

      <div class="mt-2 flex min-w-0 items-center gap-1.5 text-xs text-slate-500">
        <FolderKanban class="h-3.5 w-3.5 shrink-0 text-slate-400" />
        <span class="truncate font-medium">{{ projectName }}</span>
      </div>

      <div
        v-if="visualState.issue"
        :class="[
          'mt-3 flex items-start gap-2 rounded-xl border px-3 py-2.5 text-xs leading-relaxed',
          visualState.panel,
        ]"
      >
        <component :is="visualState.icon" class="mt-0.5 h-4 w-4 shrink-0" />
        <div class="min-w-0">
          <p class="line-clamp-2">{{ visualState.detail }}</p>
          <span
            v-if="showRecoveryState"
            class="mt-1.5 inline-flex rounded-md bg-white/70 px-2 py-0.5 text-[10px] font-bold"
          >
            {{ task.recoveryPlan ? "Đã có kế hoạch khắc phục" : "Chưa có kế hoạch khắc phục" }}
          </span>
        </div>
      </div>

      <div class="mt-4">
        <div class="mb-1.5 flex items-end justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-slate-500">Tiến độ công việc</p>
            <p class="truncate text-xs font-bold text-slate-700">{{ progressCaption }}</p>
          </div>
          <strong class="shrink-0 text-lg leading-none text-slate-900">
            {{ progressValue }}%
          </strong>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            :class="['h-full rounded-full transition-all duration-500', visualState.progress]"
            :style="{ width: progressValue + '%' }"
          ></div>
        </div>
      </div>

      <footer class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
        <span
          :class="[
            'inline-flex min-h-7 min-w-0 flex-1 items-center gap-1.5 rounded-lg border px-2 text-[11px] font-semibold',
            deadlineClass,
          ]"
          :title="task.dueDate ? 'Hạn hoàn thành: ' + formatDate(task.dueDate) : 'Chưa đặt hạn hoàn thành'"
        >
          <CalendarDays class="h-3.5 w-3.5 shrink-0" />
          <span class="truncate">{{ deadlineText }}</span>
        </span>

        <span
          class="flex min-w-0 shrink-0 items-center gap-2"
          :title="'Phụ trách: ' + assigneeName"
        >
          <UserAvatar
            v-if="task.assigneeId"
            :member-id="task.assigneeId"
            size="sm"
            :show-popover="false"
          />
          <span
            v-else
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"
          >
            <UserRound class="h-4 w-4" />
          </span>
          <span class="max-w-24 truncate text-[11px] font-semibold text-slate-600">
            {{ assigneeName }}
          </span>
        </span>
      </footer>
    </div>
  </article>
</template>

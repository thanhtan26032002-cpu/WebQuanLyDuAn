<script setup>
import { computed } from "vue";
import { CalendarDays, MoreHorizontal } from "@lucide/vue";
import { useProjectWorkspace } from "../../composables/useProjectWorkspace";
import UserAvatar from "./UserAvatar.vue";

const props = defineProps({
  task: { type: Object, required: true },
  showStatus: Boolean,
  readOnly: Boolean,
});

const { priorityMap, taskStatusMap, findProject, findMember, formatDate, getTaskDeadlineState } =
  useProjectWorkspace();
const project = computed(() => findProject(props.task.projectId));
const assignee = computed(() => findMember(props.task.assigneeId));

const deadlineState = computed(() =>
  props.task.deadlineState || getTaskDeadlineState(props.task.dueDate, props.task.status),
);

const deadlineClass = computed(() => {
  if (deadlineState.value === "overdue") {
    return "text-rose-600 bg-rose-50 border-rose-200";
  }
  if (deadlineState.value === "due") {
    return "text-amber-700 bg-amber-50 border-amber-200";
  }
  if (deadlineState.value === "completed_late") {
    return "text-amber-700 bg-amber-50 border-amber-200";
  }
  return "text-slate-400 bg-transparent border-transparent";
});

const priorityDotMap = {
  high: "bg-rose-500",
  medium: "bg-amber-500",
  low: "bg-sky-500",
};

const priorityBadgeMap = {
  high: "bg-rose-50 text-rose-600 border border-rose-100",
  medium: "bg-amber-50 text-amber-600 border border-amber-100",
  low: "bg-sky-50 text-sky-600 border border-sky-100",
};

const statusBadgeMap = {
  todo: "bg-slate-100 text-slate-600",
  in_progress: "bg-amber-50 text-amber-700",
  done: "bg-emerald-50 text-emerald-700",
};
</script>

<template>
  <article
    :class="['bg-white p-4 rounded-xl shadow-sm border border-slate-100 hover:shadow-md hover:border-violet-100 transition-all duration-200 group', readOnly ? 'cursor-pointer' : 'cursor-grab active:cursor-grabbing']"
  >
    <!-- Top row: priority + menu -->
    <div class="flex items-center justify-between mb-2.5">
      <div class="flex items-center gap-2">
        <div
          :class="[
            'w-2 h-2 rounded-full shrink-0',
            priorityDotMap[task.priority] || 'bg-slate-400',
          ]"
        ></div>
        <span
          :class="[
            'px-2 py-0.5 text-[11px] font-semibold rounded-full',
            priorityBadgeMap[task.priority] || priorityBadgeMap.medium,
          ]"
        >
          {{ priorityMap[task.priority]?.label || "Trung bình" }}
        </span>
        <span
          v-if="showStatus"
          :class="[
            'px-2 py-0.5 text-[11px] font-semibold rounded-full',
            statusBadgeMap[task.status],
          ]"
        >
          {{ taskStatusMap[task.status]?.label }}
        </span>
      </div>
      <button
        v-if="!readOnly"
        class="text-slate-300 hover:text-slate-600 opacity-0 group-hover:opacity-100 transition-all"
      >
        <MoreHorizontal class="w-4 h-4" />
      </button>
    </div>

    <!-- Title -->
    <h3
      class="font-semibold text-slate-800 text-sm leading-snug mb-1 line-clamp-2"
    >
      {{ task.title }}
    </h3>

    <!-- Project name -->
    <p class="text-xs text-slate-400 mb-3 truncate">
      {{ task.projectName || project?.name || "Không thuộc dự án" }}
    </p>

    <!-- Tags -->
    <div v-if="task.tags?.length" class="flex flex-wrap gap-1 mb-3">
      <span
        v-for="tag in task.tags.slice(0, 3)"
        :key="tag"
        class="px-1.5 py-0.5 bg-slate-50 border border-slate-100 text-slate-500 rounded text-[10px] font-medium uppercase tracking-wider"
      >
        {{ tag }}
      </span>
    </div>

    <!-- Footer -->
    <footer
      class="flex items-center justify-between pt-3 border-t border-slate-50"
    >
      <div
        :class="[
          'flex items-center text-xs font-semibold gap-1 rounded-lg border px-2 py-1',
          deadlineClass,
        ]"
      >
        <CalendarDays class="w-3.5 h-3.5" />
        {{
          deadlineState === "overdue"
            ? `Quá hạn ${task.overdueDays} ngày`
            : deadlineState === "completed_late"
              ? `Trễ ${task.lateDays} ngày`
              : formatDate(task.dueDate)
        }}
      </div>

      <UserAvatar :member-id="task.assigneeId" size="sm" />
    </footer>
  </article>
</template>

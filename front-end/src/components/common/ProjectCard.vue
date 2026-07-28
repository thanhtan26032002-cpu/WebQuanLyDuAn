<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";
import { CalendarDays, ArrowRight, MoreHorizontal } from "@lucide/vue";
import { useProjectWorkspace } from "../../composables/useProjectWorkspace";

const props = defineProps({
  project: { type: Object, required: true },
  compact: Boolean,
});

const router = useRouter();
const {
  projectStatusMap,
  findMember,
  formatDate,
  getTaskDeadlineState,
  projectSettingsModalOpen,
  editingProjectId,
} = useProjectWorkspace();

const handleEdit = () => {
  editingProjectId.value = props.project.id;
  projectSettingsModalOpen.value = true;
};

const deadlineState = computed(() =>
  getTaskDeadlineState(props.project.dueDate, props.project.status)
);

const deadlineClass = computed(() => {
  if (deadlineState.value === "overdue") {
    return "text-red-600 bg-red-50 border-red-200 font-semibold px-2 py-0.5 rounded-lg border";
  }
  if (deadlineState.value === "due") {
    return "text-amber-700 bg-amber-50 border-amber-200 font-semibold px-2 py-0.5 rounded-lg border";
  }
  return "text-slate-400 bg-transparent border-transparent";
});

const gradientMap = {
  purple: "from-violet-500 to-indigo-600",
  violet: "from-violet-500 to-indigo-600",
  indigo: "from-indigo-500 to-violet-600",
  emerald: "from-emerald-500 to-teal-600",
  amber: "from-amber-500 to-orange-600",
  rose: "from-rose-500 to-pink-600",
  sky: "from-sky-500 to-blue-600",
  green: "from-green-500 to-emerald-600",
  orange: "from-orange-500 to-amber-600",
  pink: "from-pink-500 to-rose-600",
  blue: "from-blue-500 to-indigo-600",
};

const progressBgMap = {
  purple: "bg-violet-500",
  violet: "bg-violet-500",
  indigo: "bg-indigo-500",
  emerald: "bg-emerald-500",
  amber: "bg-amber-500",
  rose: "bg-rose-500",
  sky: "bg-sky-500",
  green: "bg-green-500",
  orange: "bg-orange-500",
  pink: "bg-pink-500",
  blue: "bg-blue-500",
};

const getGradient = (color) => gradientMap[color] || gradientMap.indigo;
const getProgressBg = (color) => progressBgMap[color] || progressBgMap.indigo;

const statusBadge = computed(() => {
  const map = {
    active: "bg-emerald-50 text-emerald-700 border border-emerald-100",
    planning: "bg-slate-100 text-slate-600 border border-slate-200",
    on_hold: "bg-amber-50 text-amber-700 border border-amber-100",
    completed: "bg-violet-50 text-violet-700 border border-violet-100",
  };
  return map[props.project.status] || map.planning;
});
</script>

<template>
  <article
    class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 overflow-hidden group cursor-pointer flex flex-col"
    @click="router.push(`/projects/${project.id}`)"
  >
    <!-- Gradient Header -->
    <header
      :class="[
        'px-5 py-5 bg-gradient-to-br relative overflow-hidden',
        getGradient(project.color),
      ]"
    >
      <!-- Decorative -->
      <div
        class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -translate-y-8 translate-x-8 blur-xl"
      ></div>

      <div class="relative flex justify-between items-start">
        <div class="flex-1 min-w-0 pr-3">
          <h3
            class="font-bold text-white text-base leading-tight mb-2 line-clamp-2"
          >
            {{ project.name }}
          </h3>
          <span
            :class="[
              'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm',
            ]"
          >
            {{ projectStatusMap[project.status]?.label }}
          </span>
        </div>
        <button
          class="text-white/70 hover:text-white hover:bg-white/20 p-1.5 rounded-lg transition-colors shrink-0"
          @click.stop="handleEdit"
          title="Chỉnh sửa dự án"
        >
          <MoreHorizontal class="w-4 h-4" />
        </button>
      </div>
    </header>

    <!-- Body -->
    <div class="p-5 flex flex-col flex-1">
      <p class="text-sm text-slate-500 mb-4 line-clamp-2 flex-1">
        {{ project.description }}
      </p>

      <!-- Progress -->
      <div class="mb-4">
        <div class="flex justify-between items-center mb-1.5">
          <span class="text-xs font-medium text-slate-500">Tiến độ</span>
          <span class="text-sm font-bold text-slate-900"
            >{{ project.progress }}%</span
          >
        </div>
        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
          <div
            :class="[
              'h-full rounded-full transition-all duration-700',
              getProgressBg(project.color),
            ]"
            :style="{ width: `${project.progress}%` }"
          ></div>
        </div>
      </div>

      <!-- Footer -->
      <footer class="flex items-center justify-between">
        <!-- Member avatars -->
        <div class="flex -space-x-1.5">
          <div
            v-for="memberId in project.memberIds.slice(0, 4)"
            :key="memberId"
            :class="[
              'w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white ring-2 ring-white shrink-0',
              `bg-${findMember(memberId).color}-500`,
            ]"
            :title="findMember(memberId).name"
          >
            {{ findMember(memberId).initials }}
          </div>
          <div
            v-if="project.memberIds.length > 4"
            class="w-6 h-6 rounded-full bg-slate-100 text-slate-500 ring-2 ring-white flex items-center justify-center text-[9px] font-bold"
          >
            +{{ project.memberIds.length - 4 }}
          </div>
        </div>

        <!-- Due date / Arrow -->
        <div
          :class="[
            'flex items-center gap-1 text-xs transition-colors',
            deadlineClass,
          ]"
        >
          <CalendarDays class="w-3.5 h-3.5" />
          {{ formatDate(project.dueDate) }}
        </div>
      </footer>
    </div>
  </article>
</template>

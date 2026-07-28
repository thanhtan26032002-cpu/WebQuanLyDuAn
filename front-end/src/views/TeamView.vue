<script setup>
import { computed, ref } from 'vue'
import { Users, UserPlus, MoreHorizontal, Mail, ArrowRight, ListTodo, Hash, Plus } from '@lucide/vue'
import { useProjectWorkspace } from '../composables/useProjectWorkspace'

const { members, groups, tasks, activeMemberId, memberDetailModalOpen, openAddMemberModal, addGroupModalOpen, editGroupModalOpen, activeEditGroupId, assignMemberToGroup } = useProjectWorkspace()

const totalOnline = computed(() => members.value.filter(m => m.online).length)
const totalTasksInProgress = computed(() => tasks.value.filter(t => t.status === 'in_progress').length)

// Drag and drop state
const draggedMemberId = ref(null)
const dragOverGroupId = ref(null)
const dragOverUnassigned = ref(false)

const onDragStart = (e, memberId) => {
  draggedMemberId.value = memberId
  // Thiết lập drag image hoặc hiệu ứng nếu cần
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', memberId)
  }
}

const resetDragState = () => {
  draggedMemberId.value = null
  dragOverGroupId.value = null
  dragOverUnassigned.value = false
}

const getDraggedMemberId = (e) => {
  return draggedMemberId.value || e.dataTransfer?.getData('text/plain') || null
}

const onDrop = async (e, targetGroupId) => {
  e.preventDefault()
  const memberId = getDraggedMemberId(e)
  if (memberId) {
    await assignMemberToGroup(memberId, targetGroupId)
  }
  resetDragState()
}

const onDropUnassigned = async (e) => {
  e.preventDefault()
  const memberId = getDraggedMemberId(e)
  if (memberId) {
    await assignMemberToGroup(memberId, null)
  }
  resetDragState()
}

const onDragLeaveGroup = (e, groupId) => {
  if (!e.currentTarget.contains(e.relatedTarget) && dragOverGroupId.value === groupId) {
    dragOverGroupId.value = null
  }
}

const onDragLeaveUnassigned = (e) => {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    dragOverUnassigned.value = false
  }
}

const openEditGroup = (groupId) => {
  activeEditGroupId.value = groupId
  editGroupModalOpen.value = true
}

const getMembersByGroup = (groupId) => {
  const group = groups.value.find(g => g.id === groupId)
  if (!group) return []
  return members.value.filter(m => group.memberIds.includes(m.id))
}

const getUnassignedMembers = () => {
  const assignedIds = groups.value.flatMap(g => g.memberIds)
  return members.value.filter(m => !assignedIds.includes(m.id))
}

const openMemberDetail = (memberId) => {
  activeMemberId.value = memberId
  memberDetailModalOpen.value = true
}

const taskCount = (memberId, status) => tasks.value.filter((task) => task.assigneeId === memberId && task.status === status).length
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 mb-1">Nhóm làm việc</h1>
        <p class="text-slate-500 text-sm">
          Tổng cộng {{ members.length }} thành viên • <span class="text-emerald-500 font-medium">{{ totalOnline }} đang online</span> • {{ totalTasksInProgress }} nhiệm vụ đang làm
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button 
          @click="addGroupModalOpen = true"
          class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-medium hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm shrink-0 flex items-center gap-2"
        >
          <Plus class="w-5 h-5 text-slate-400" /> Tạo nhóm
        </button>
        <button 
          @click="openAddMemberModal()"
          class="bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-5 py-2.5 rounded-xl font-medium hover:shadow-premium transition-all shadow-md shadow-violet-500/25 shrink-0 flex items-center gap-2"
        >
          <UserPlus class="w-5 h-5" /> Thêm thành viên
        </button>
      </div>
    </div>

    <!-- Groups layout -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
      <div 
        v-for="group in groups" 
        :key="group.id" 
        class="rounded-3xl p-6 border transition-all duration-300 relative overflow-hidden group border-t-4"
        :class="[
          `border-t-${group.color}-500`,
          dragOverGroupId === group.id 
            ? 'bg-violet-50/80 border-violet-400 shadow-md ring-2 ring-violet-500 ring-offset-2' 
            : `bg-${group.color}-50/40 border-${group.color}-200/80 hover:border-${group.color}-300 hover:shadow-xl hover:shadow-${group.color}-500/10 hover:-translate-y-1`
        ]"
        @dragover.prevent
        @dragenter.prevent="dragOverGroupId = group.id"
        @dragleave="onDragLeaveGroup($event, group.id)"
        @drop="onDrop($event, group.id)"
      >
        <!-- Top Gradient Banner background -->
        <div :class="['absolute top-0 left-0 right-0 h-20 bg-gradient-to-r pointer-events-none -z-0', `from-${group.color}-500/15 to-${group.color}-500/5`]"></div>

        <!-- Group Header -->
        <div class="flex items-center justify-between mb-6 relative z-10">
          <div class="flex items-center gap-3">
            <div :class="['w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-md ring-2 ring-white', `bg-${group.color}-500 text-white`]">
              {{ group.icon }}
            </div>
            <div>
              <h2 :class="['text-xl font-bold text-slate-900 transition-colors', `group-hover:text-${group.color}-600`]">{{ group.name }}</h2>
              <p class="text-sm text-slate-500 flex items-center gap-1.5">
                <span :class="['w-2 h-2 rounded-full inline-block', `bg-${group.color}-500`]"></span>
                {{ group.memberIds.length }} thành viên
              </p>
            </div>
          </div>
          <button @click="openEditGroup(group.id)" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-white/80 shadow-sm transition-all">
            <MoreHorizontal class="w-5 h-5" />
          </button>
        </div>

        <p class="text-sm text-slate-600 mb-6 relative z-10">{{ group.description }}</p>

        <!-- Members in Group -->
        <div class="space-y-4 min-h-[50px] relative z-10">
          <article 
            v-for="member in getMembersByGroup(group.id)" 
            :key="member.id" 
            draggable="true"
            @dragstart="onDragStart($event, member.id)"
            @dragend="resetDragState"
            class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:shadow-md transition-shadow group/card cursor-grab active:cursor-grabbing"
            @click="openMemberDetail(member.id)"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="relative">
                  <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-sm', `bg-gradient-to-br from-${member.color}-400 to-${member.color}-600`]">
                    {{ member.initials }}
                  </div>
                  <div :class="['absolute -bottom-1 -right-1 w-3.5 h-3.5 border-2 border-white rounded-full', member.online ? 'bg-emerald-500' : 'bg-slate-300']"></div>
                </div>
                <div>
                  <h3 class="font-bold text-slate-900 leading-tight group-hover/card:text-violet-600 transition-colors">{{ member.name }}</h3>
                  <p class="text-xs text-slate-500 mt-0.5">{{ member.role }}</p>
                </div>
              </div>
              
              <div class="flex items-center gap-4 text-sm text-slate-500 font-medium">
                <div class="text-center hidden sm:block">
                  <span class="block text-lg font-bold text-slate-800 leading-none mb-0.5">{{ taskCount(member.id, 'in_progress') }}</span>
                  <span class="text-[10px] uppercase">Đang làm</span>
                </div>
                <ArrowRight class="w-4 h-4 text-slate-300 group-hover/card:text-violet-500 group-hover/card:translate-x-1 transition-all" />
              </div>
            </div>
          </article>
        </div>

        <!-- Add member to group button -->
        <div class="relative mt-4 z-10">
          <button 
            @click="openAddMemberModal(group.id)"
            :class="[
              'w-full py-3 border-2 border-dashed rounded-2xl font-medium transition-all flex items-center justify-center gap-2',
              `border-${group.color}-200/80 text-${group.color}-600 hover:border-${group.color}-400 hover:bg-${group.color}-50/50 hover:shadow-sm`
            ]"
          >
            <UserPlus class="w-4 h-4" /> Thêm người / Kéo thả
          </button>
          
        </div>
      </div>
    </div>

    <!-- Unassigned Members -->
    <div 
      class="pt-8 transition-all duration-200 rounded-3xl p-6 mt-4 border-2 border-transparent"
      :class="{ 'bg-slate-100/80 border-slate-300 border-dashed': dragOverUnassigned }"
      @dragover.prevent
      @dragenter.prevent="dragOverUnassigned = true"
      @dragleave="onDragLeaveUnassigned"
      @drop="onDropUnassigned"
    >
      <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
        <Hash class="w-5 h-5 text-slate-400" /> Thành viên chưa phân nhóm
        <span v-if="dragOverUnassigned" class="text-sm text-slate-500 font-medium ml-2 animate-pulse">(Thả vào đây để loại khỏi nhóm)</span>
      </h3>
      <div v-if="getUnassignedMembers().length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 min-h-[60px]">
        <article 
          v-for="member in getUnassignedMembers()" 
          :key="member.id" 
          draggable="true"
          @dragstart="onDragStart($event, member.id)"
          @dragend="resetDragState"
          class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing flex items-center gap-3"
          @click="openMemberDetail(member.id)"
        >
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold text-white shadow-sm shrink-0', `bg-gradient-to-br from-${member.color}-400 to-${member.color}-600`]">
            {{ member.initials }}
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="font-bold text-slate-900 text-sm truncate">{{ member.name }}</h3>
            <p class="text-xs text-slate-500 truncate">{{ member.role }}</p>
          </div>
        </article>
      </div>
      <div v-else class="min-h-[90px] rounded-2xl border-2 border-dashed border-slate-200 bg-white/60 px-4 py-6 text-center text-sm font-medium text-slate-400">
        Thả thành viên từ một nhóm vào đây để chuyển về chưa phân nhóm.
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { X, Users, Search, Check } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { members, projects, manageMembersModalOpen, editingProjectId, updateProjectMembers } = useProjectWorkspace()

const project = computed(() => projects.value.find(p => p.id === editingProjectId.value))

const searchQuery = ref('')
const selectedMemberIds = ref([])

watch(manageMembersModalOpen, (isOpen) => {
  if (isOpen && project.value) {
    selectedMemberIds.value = [...project.value.memberIds]
    searchQuery.value = ''
  }
})

const filteredMembers = computed(() => {
  if (!searchQuery.value) return members.value
  const query = searchQuery.value.toLowerCase()
  return members.value.filter(m => 
    m.name.toLowerCase().includes(query) || m.role.toLowerCase().includes(query)
  )
})

const toggleMember = (memberId) => {
  const index = selectedMemberIds.value.indexOf(memberId)
  if (index >= 0) {
    selectedMemberIds.value.splice(index, 1)
  } else {
    selectedMemberIds.value.push(memberId)
  }
}

const selectAll = () => {
  selectedMemberIds.value = members.value.map(m => m.id)
}

const deselectAll = () => {
  selectedMemberIds.value = []
}

const submit = () => {
  if (editingProjectId.value) {
    updateProjectMembers(editingProjectId.value, selectedMemberIds.value)
  }
  manageMembersModalOpen.value = false
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') manageMembersModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="manageMembersModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="manageMembersModalOpen = false"></div>
      
      <!-- Modal Content -->
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[85vh] overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <Users class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Thành viên dự án</h2>
              <p class="text-sm text-slate-500">Quản lý những ai có quyền tham gia dự án này.</p>
            </div>
          </div>
          <button type="button" @click="manageMembersModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div class="p-6 overflow-hidden flex flex-col flex-1 gap-4">
          <!-- Search & Actions -->
          <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="relative w-full">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="searchQuery" type="text" placeholder="Tìm kiếm thành viên..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:border-violet-300 focus:ring-2 focus:ring-violet-100 transition-all" />
            </div>
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-center">
              <button @click="selectAll" class="text-xs font-medium text-violet-600 hover:text-violet-700">Chọn tất cả</button>
              <span class="text-slate-300">|</span>
              <button @click="deselectAll" class="text-xs font-medium text-slate-500 hover:text-slate-700">Bỏ chọn hết</button>
            </div>
          </div>

          <!-- Member List -->
          <div class="flex-1 overflow-y-auto custom-scrollbar -mx-2 px-2 pb-2">
            <div class="space-y-1.5">
              <label 
                v-for="member in filteredMembers" 
                :key="member.id"
                class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer hover:bg-slate-50"
                :class="selectedMemberIds.includes(member.id) ? 'border-violet-200 bg-violet-50/30' : 'border-transparent'"
              >
                <!-- Checkbox -->
                <div class="relative flex items-center justify-center">
                  <input type="checkbox" :checked="selectedMemberIds.includes(member.id)" @change="toggleMember(member.id)" class="peer sr-only" />
                  <div class="w-5 h-5 rounded border-2 border-slate-300 peer-checked:bg-violet-600 peer-checked:border-violet-600 transition-colors flex items-center justify-center">
                    <Check class="w-3.5 h-3.5 text-white opacity-0 peer-checked:opacity-100 transition-opacity" />
                  </div>
                </div>

                <!-- Avatar -->
                <div :class="['w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white shrink-0', `bg-${member.color}-500`]">
                  {{ member.initials }}
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-slate-900 leading-tight truncate">{{ member.name }}</p>
                  <p class="text-xs text-slate-500 truncate">{{ member.role }}</p>
                </div>
              </label>

              <div v-if="filteredMembers.length === 0" class="text-center py-8 text-sm text-slate-500">
                Không tìm thấy thành viên nào.
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div class="text-sm font-medium text-slate-600">
            Đã chọn <strong class="text-violet-600">{{ selectedMemberIds.length }}</strong> thành viên
          </div>
          <div class="flex gap-3">
            <button type="button" @click="manageMembersModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
              Hủy
            </button>
            <button @click="submit" class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
              <Check class="w-4 h-4" /> Cập nhật
            </button>
          </div>
        </footer>
      </div>
    </div>
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
  background-color: #cbd5e1;
  border-radius: 20px;
}
</style>

<script setup>
import { computed, ref, watch } from 'vue'
import { X, CalendarDays, Flag, CheckCircle2, MessageSquare, Paperclip, Send, Clock, User, Download, FileText, Trash2, Edit2, Check, ListChecks, Plus } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'
import UserAvatar from './UserAvatar.vue'
import DownloadArchiveModal from '../modals/DownloadArchiveModal.vue'

const props = defineProps({
  task: {
    type: Object,
    required: true
  }
})

const { 
  tasks, projects, members, comments, 
  projectStatusMap, priorityMap, taskStatusMap, 
  taskModalOpen, activeTaskId, 
  findMember, findProject, formatDate, getTaskDeadlineState, updateTask, loadComments, addComment, uploadFile,
  downloadArchive, downloadSingleFile
} = useProjectWorkspace()

const isDownloadModalOpen = ref(false)
const taskTypeLabels = { task: 'Công việc', analysis: 'Phân tích yêu cầu', ui_ux: 'Thiết kế UI/UX', frontend: 'Phát triển Frontend', backend: 'Phát triển Backend', mobile: 'Ứng dụng Mobile', api: 'API / Tích hợp', database: 'Cơ sở dữ liệu', devops: 'DevOps / Hạ tầng', testing: 'Kiểm thử / QA', security: 'Bảo mật', documentation: 'Tài liệu', research: 'Nghiên cứu', maintenance: 'Bảo trì', feature: 'Tính năng', bug: 'Lỗi cần sửa', milestone: 'Cột mốc' }

const task = computed(() => tasks.value.find(t => t.id === activeTaskId.value))
const project = computed(() => findProject(task.value?.projectId))
const assignee = computed(() => findMember(task.value?.assigneeId))
const taskComments = computed(() => comments.value.filter(c => c.taskId === activeTaskId.value).sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)))

const deadlineInfo = computed(() => {
  const state = getTaskDeadlineState(task.value?.dueDate, task.value?.status)

  if (state === 'overdue') {
    return {
      label: 'Quá hạn',
      textClass: 'text-rose-600',
      iconClass: 'bg-rose-50 text-rose-600 border-rose-200',
    }
  }
  if (state === 'due') {
    return {
      label: 'Đến hạn',
      textClass: 'text-amber-700',
      iconClass: 'bg-amber-50 text-amber-600 border-amber-200',
    }
  }
  if (task.value?.status === 'done') {
    return {
      label: 'Đã hoàn thành',
      textClass: 'text-emerald-600',
      iconClass: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    }
  }
  return {
    label: 'Đúng tiến độ',
    textClass: 'text-emerald-600',
    iconClass: 'bg-slate-50 text-slate-500 border-slate-100',
  }
})

const newComment = ref('')
const isEditing = ref(false)
const editedTask = ref({})

const startEditing = () => {
  if (!task.value) return
  editedTask.value = JSON.parse(JSON.stringify(task.value))
  editedTask.value.tagsInput = Array.isArray(task.value?.tags) ? task.value.tags.join(', ') : (task.value?.tags || '')
  isEditing.value = true
}

watch(activeTaskId, async (newVal) => {
  if (newVal) {
    isEditing.value = false
    if (task.value) {
      editedTask.value = JSON.parse(JSON.stringify(task.value))
      editedTask.value.tagsInput = Array.isArray(task.value?.tags) ? task.value.tags.join(', ') : (task.value?.tags || '')
    }
    await loadComments(newVal)
  }
})

const closePanel = () => {
  activeTaskId.value = null
  isEditing.value = false
}

const handleSave = () => {
  const tagsArray = editedTask.value.tagsInput 
    ? editedTask.value.tagsInput.split(',').map(s => s.trim()).filter(Boolean) 
    : []

  updateTask(task.value.id, {
    title: editedTask.value.title,
    description: editedTask.value.description,
    status: editedTask.value.status,
    priority: editedTask.value.priority,
    type: editedTask.value.type || 'task',
    progress: Number(editedTask.value.progress),
    projectId: editedTask.value.projectId || null,
    assigneeId: editedTask.value.assigneeId || null,
    startDate: editedTask.value.startDate || null,
    dueDate: editedTask.value.dueDate || null,
    estimatedHours: editedTask.value.estimatedHours || null,
    tags: tagsArray
  })
  isEditing.value = false
}

const fileInput = ref(null)
const attachedFile = ref(null)
const isUploading = ref(false)

const triggerFileInput = () => {
  if (fileInput.value) fileInput.value.click()
}

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  isUploading.value = true
  const res = await uploadFile(file, 'TaskComment', task.value.id)
  if (res && res.attachment) {
    attachedFile.value = res.attachment
  } else {
    alert('Không thể tải lên file đính kèm.')
  }
  isUploading.value = false
  if (fileInput.value) fileInput.value.value = ''
}

const removeAttachment = () => {
  attachedFile.value = null
}

const handleDownloadArchive = async (payload) => {
  await downloadArchive(payload.targetType, payload.targetCode, payload.fileName, payload.format)
  isDownloadModalOpen.value = false
}

const submitComment = async () => {
  if (!newComment.value.trim() && !attachedFile.value) return
  
  const fileUrl = attachedFile.value?.file_path || null
  const fileName = attachedFile.value?.file_name || null

  const res = await addComment(task.value.id, newComment.value, fileUrl, fileName)
  
  if (res && res.success === false && res.errors) {
    alert(res.errors._general || 'Vui lòng kiểm tra lại dữ liệu.')
  } else if (res && res.success) {
    newComment.value = ''
    attachedFile.value = null
  }
}

const timeAgo = (dateStr) => {
  const diff = (new Date() - new Date(dateStr)) / 1000
  if (diff < 60) return 'Vừa xong'
  if (diff < 3600) return `${Math.floor(diff/60)} phút trước`
  if (diff < 86400) return `${Math.floor(diff/3600)} giờ trước`
  return formatDate(dateStr)
}

// Checklist logic
const newChecklistText = ref('')
const addChecklist = () => {
  if (!newChecklistText.value.trim()) return
  if (!task.value.checklists) task.value.checklists = []
  task.value.checklists.push({
    id: Date.now(),
    text: newChecklistText.value,
    completed: false
  })
  updateProgressFromChecklist()
  newChecklistText.value = ''
}
const toggleChecklist = (item) => {
  item.completed = !item.completed
  updateProgressFromChecklist()
}
const removeChecklist = (id) => {
  task.value.checklists = task.value.checklists.filter(c => c.id !== id)
  updateProgressFromChecklist()
}
const updateProgressFromChecklist = () => {
  if (!task.value.checklists?.length) return
  const completed = task.value.checklists.filter(c => c.completed).length
  const total = task.value.checklists.length
  task.value.progress = Math.round((completed / total) * 100)
}

// WorkLog logic
const showWorkLogForm = ref(false)
const newWorkLogTime = ref('')
const newWorkLogNote = ref('')
const newWorkLogFiles = ref([])
const newWorkLogChecklists = ref([])

const addWorkLog = () => {
  if (!newWorkLogTime.value) return
  if (!task.value.workLogs) task.value.workLogs = []
  
  // Create log
  task.value.workLogs.unshift({
    id: Date.now(),
    memberId: 1, // current user
    time: newWorkLogTime.value,
    note: newWorkLogNote.value,
    date: new Date().toISOString().split('T')[0],
    checklists: [...newWorkLogChecklists.value],
    files: [...newWorkLogFiles.value]
  })
  
  // Mark checklists as completed
  if (newWorkLogChecklists.value.length > 0 && task.value.checklists) {
    task.value.checklists.forEach(item => {
      if (newWorkLogChecklists.value.includes(item.id)) {
        item.completed = true
      }
    })
    updateProgressFromChecklist()
  }

  // Push files to task attachments
  if (newWorkLogFiles.value.length > 0) {
    if (!task.value.files) task.value.files = []
    task.value.files.unshift(...newWorkLogFiles.value)
  }
  
  showWorkLogForm.value = false
  newWorkLogTime.value = ''
  newWorkLogNote.value = ''
  newWorkLogFiles.value = []
  newWorkLogChecklists.value = []
}

// Temporary file selection for work log
const isWorkLogUploading = ref(false)
const onWorkLogFileSelect = async (e) => {
  if (e.target.files && e.target.files.length > 0) {
    isWorkLogUploading.value = true
    for (const file of Array.from(e.target.files)) {
      const res = await uploadFile(file, 'Task', task.value.id)
      if (res && res.attachment) {
        newWorkLogFiles.value.push({
          name: res.attachment.file_name,
          size: (res.attachment.size_bytes / 1024 / 1024).toFixed(2) + ' MB',
          uploadedBy: res.attachment.uploaded_by,
          uploadedAt: res.attachment.created_at,
          url: res.attachment.file_path
        })
      }
    }
    isWorkLogUploading.value = false
  }
}
const removeWorkLogFile = (idx) => {
  newWorkLogFiles.value.splice(idx, 1)
}

// Task Attachment Upload logic
const taskFileInput = ref(null)
const openTaskFileUpload = () => {
  if (taskFileInput.value) {
    taskFileInput.value.click()
  }
}
const isTaskFileUploading = ref(false)
const onTaskFileSelect = async (e) => {
  if (e.target.files && e.target.files.length > 0) {
    isTaskFileUploading.value = true
    for (const file of Array.from(e.target.files)) {
      const res = await uploadFile(file, 'Task', task.value.id)
      if (res && res.attachment) {
        if (!task.value.files) task.value.files = []
        task.value.files.unshift({
          name: res.attachment.file_name,
          size: (res.attachment.size_bytes / 1024 / 1024).toFixed(2) + ' MB',
          uploadedBy: res.attachment.uploaded_by,
          uploadedAt: res.attachment.created_at,
          url: res.attachment.file_path
        })
      }
    }
    isTaskFileUploading.value = false
  }
  if (taskFileInput.value) taskFileInput.value.value = ''
}

const formatDateTime = (isoStr) => {
  if (!isoStr) return ''
  const d = new Date(isoStr)
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <div v-if="task" class="fixed inset-0 z-50 overflow-hidden flex justify-end">
    <!-- Backdrop -->
    <div 
      class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
      @click="closePanel"
    ></div>

    <!-- Panel -->
    <div class="relative w-full max-w-xl h-full bg-white shadow-2xl flex flex-col transform transition-transform duration-300 ease-in-out z-10 border-l border-slate-100">
      <!-- Header -->
      <header class="px-6 py-4 flex items-center justify-between border-b border-slate-100 bg-white sticky top-0 z-20">
        <div class="flex items-center gap-3">
          <div :class="['w-8 h-8 flex items-center justify-center rounded-lg', task.status === 'done' ? 'bg-emerald-100 text-emerald-600' : 'bg-violet-100 text-violet-600']">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <select 
            v-if="isEditing" 
            v-model="editedTask.projectId"
            class="text-sm font-semibold text-slate-700 bg-slate-100 border-none rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-violet-500 max-w-[240px] cursor-pointer"
          >
            <option :value="null">— Không có dự án —</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-else class="text-sm font-medium text-slate-500">{{ project?.name || 'Không có dự án' }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button 
            v-if="!isEditing"
            @click="startEditing"
            class="p-2 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-xl transition-colors"
          >
            <Edit2 class="w-5 h-5" />
          </button>
          <button 
            v-else
            @click="handleSave"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white font-medium text-sm rounded-lg hover:bg-violet-700 transition-colors shadow-sm"
          >
            <Check class="w-4 h-4" /> Lưu
          </button>
          <button 
            @click="closePanel"
            class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </header>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto px-6 py-6 custom-scrollbar">
        <!-- Title & Status -->
        <div class="mb-8">
          <template v-if="!isEditing">
            <h2 class="text-2xl font-bold text-slate-900 mb-4">{{ task.title }}</h2>
          </template>
          <template v-else>
            <input 
              v-model="editedTask.title"
              class="w-full text-2xl font-bold text-slate-900 mb-4 border-b border-slate-300 focus:border-violet-500 focus:outline-none pb-1 bg-slate-50 px-2 rounded-t"
            />
          </template>
          
          <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Trạng thái:</span>
              <select 
                v-if="isEditing" 
                v-model="editedTask.status"
                class="text-sm font-semibold rounded-lg px-2 py-1 bg-slate-100 border-none focus:ring-2 focus:ring-violet-500"
              >
                <option v-for="(v, k) in taskStatusMap" :key="k" :value="k">{{ v.label }}</option>
              </select>
              <span v-else :class="['px-2.5 py-1 text-xs font-semibold rounded-full border', `bg-${taskStatusMap[task.status]?.color || 'slate'}-50 text-${taskStatusMap[task.status]?.color || 'slate'}-700 border-${taskStatusMap[task.status]?.color || 'slate'}-200`]">
                {{ taskStatusMap[task.status]?.label || task.status }}
              </span>
            </div>
            
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Ưu tiên:</span>
              <select 
                v-if="isEditing" 
                v-model="editedTask.priority"
                class="text-sm font-semibold rounded-lg px-2 py-1 bg-slate-100 border-none focus:ring-2 focus:ring-violet-500"
              >
                <option v-for="(v, k) in priorityMap" :key="k" :value="k">{{ v.label }}</option>
              </select>
              <span v-else class="flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                <Flag :class="['w-3.5 h-3.5', task.priority === 'high' ? 'text-rose-500' : task.priority === 'medium' ? 'text-amber-500' : 'text-sky-500']" />
                {{ priorityMap[task.priority]?.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Description -->
        <div class="mb-8">
          <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
            <FileText class="w-4 h-4 text-slate-400" /> Mô tả
          </h3>
          <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <textarea 
              v-if="isEditing" 
              v-model="editedTask.description"
              class="w-full h-24 bg-white border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500"
              placeholder="Nhập mô tả nhiệm vụ..."
            ></textarea>
            <p v-else class="text-slate-600 text-sm whitespace-pre-line leading-relaxed">
              {{ task.description || 'Chưa có mô tả nào cho nhiệm vụ này.' }}
            </p>
          </div>
        </div>

        <!-- Checklists -->
        <div class="mb-8" v-if="!isEditing">
          <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">
            <ListChecks class="w-4 h-4 text-slate-400" /> Công việc con
          </h3>
          <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm">
            
            <!-- List -->
            <div class="space-y-2 mb-4">
              <div v-for="item in task.checklists || []" :key="item.id" class="flex items-start gap-3 group">
                <button 
                  @click="toggleChecklist(item)"
                  :class="['mt-0.5 shrink-0 w-5 h-5 rounded flex items-center justify-center border transition-colors', item.completed ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-slate-300 hover:border-violet-400 text-transparent']"
                >
                  <Check class="w-3.5 h-3.5" />
                </button>
                <span :class="['text-sm flex-1 pt-0.5 transition-colors', item.completed ? 'text-slate-400 line-through' : 'text-slate-700']">
                  {{ item.text }}
                </span>
                <button @click="removeChecklist(item.id)" class="opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-rose-500 transition-all">
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>
            
            <!-- Add new -->
            <div class="flex items-center gap-2 mt-2">
              <input 
                v-model="newChecklistText" 
                @keyup.enter="addChecklist"
                placeholder="Thêm mục công việc con..." 
                class="flex-1 bg-slate-50 border border-slate-200 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors"
              />
              <button 
                @click="addChecklist"
                :disabled="!newChecklistText.trim()"
                class="px-3 py-1.5 bg-violet-100 text-violet-700 font-medium text-sm rounded-lg hover:bg-violet-200 transition-colors disabled:opacity-50"
              >
                Thêm
              </button>
            </div>
          </div>
        </div>

        <!-- Work Logs (Time Tracking) -->
        <div class="mb-8" v-if="!isEditing">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Clock class="w-4 h-4 text-slate-400" /> Báo cáo tiến độ công việc
            </h3>
            <button 
              @click="showWorkLogForm = !showWorkLogForm"
              class="text-xs font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-2 py-1 rounded flex items-center gap-1 transition-colors"
            >
              <Plus class="w-3.5 h-3.5" /> Báo cáo tiến độ
            </button>
          </div>
          
          <!-- Log Form -->
          <div v-if="showWorkLogForm" class="bg-violet-50 border border-violet-100 p-4 rounded-xl mb-4 animate-in slide-in-from-top-2 duration-200 space-y-4">
            <div class="flex gap-3">
              <div class="w-28 shrink-0">
                <label class="text-xs font-semibold text-slate-600 block mb-1">Giờ hoàn thành</label>
                <input type="time" v-model="newWorkLogTime" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-violet-400" />
              </div>
              <div class="flex-1">
                <label class="text-xs font-semibold text-slate-600 block mb-1">Ghi chú công việc</label>
                <input type="text" v-model="newWorkLogNote" placeholder="Ví dụ: Thiết kế xong form A..." class="w-full bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-violet-400" @keyup.enter="addWorkLog" />
              </div>
            </div>
            
            <!-- Link Checklists -->
            <div v-if="task.checklists?.filter(c => !c.completed).length" class="bg-white border border-slate-200 rounded-lg p-3">
              <label class="text-xs font-semibold text-slate-600 block mb-2">Đánh dấu công việc con đã hoàn thành:</label>
              <div class="space-y-1.5 max-h-32 overflow-y-auto custom-scrollbar">
                <label v-for="item in task.checklists.filter(c => !c.completed)" :key="item.id" class="flex items-center gap-2 cursor-pointer group">
                  <input type="checkbox" :value="item.id" v-model="newWorkLogChecklists" class="w-4 h-4 text-violet-600 rounded border-slate-300 focus:ring-violet-500 cursor-pointer" />
                  <span class="text-sm text-slate-700 group-hover:text-slate-900">{{ item.text }}</span>
                </label>
              </div>
            </div>

            <!-- Upload File -->
            <div class="bg-white border border-slate-200 rounded-lg p-3">
              <label class="text-xs font-semibold text-slate-600 block mb-2">Tệp minh chứng (Tùy chọn):</label>
              <input type="file" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 transition-colors" @change="onWorkLogFileSelect" />
              <div v-if="isWorkLogUploading" class="text-xs text-violet-600 mt-2 italic">Đang tải lên...</div>
              <div v-if="newWorkLogFiles.length" class="mt-2 space-y-1">
                <div v-for="(file, idx) in newWorkLogFiles" :key="idx" class="flex items-center justify-between bg-slate-50 rounded px-2 py-1">
                  <span class="text-xs text-slate-600 truncate mr-2">{{ file.name }}</span>
                  <button @click="removeWorkLogFile(idx)" class="text-slate-400 hover:text-rose-500"><X class="w-3.5 h-3.5" /></button>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-1 border-t border-violet-100">
              <button @click="showWorkLogForm = false" class="px-3 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-200 rounded-lg transition-colors">Hủy</button>
              <button @click="addWorkLog" class="px-3 py-1.5 text-xs font-medium bg-violet-600 text-white rounded-lg shadow-sm hover:bg-violet-700 transition-colors">Lưu báo cáo</button>
            </div>
          </div>
          
          <!-- Log List -->
          <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden mb-8">
            <div v-if="task.workLogs?.length" class="divide-y divide-slate-100 max-h-[300px] overflow-y-auto custom-scrollbar">
              <div v-for="log in task.workLogs" :key="log.id" class="p-4 flex items-start gap-3 hover:bg-slate-50 transition-colors">
                <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0 mt-0.5', `bg-${findMember(log.memberId).color}-500`]">
                  {{ findMember(log.memberId).initials }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex justify-between items-start mb-1">
                    <p class="text-sm font-semibold text-slate-900">{{ findMember(log.memberId).name }}</p>
                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">{{ log.time }}</span>
                  </div>
                  <p class="text-xs text-slate-500 mb-2">{{ formatDate(log.date) }} • {{ log.note || 'Hoàn thành công việc' }}</p>
                  
                  <div v-if="log.checklists?.length || log.files?.length" class="space-y-2 mt-2 pt-2 border-t border-slate-100">
                    <div v-if="log.checklists?.length" class="text-xs text-slate-600">
                      <span class="font-semibold text-slate-700 block mb-1">Đã hoàn thành:</span>
                      <ul class="space-y-1">
                        <li v-for="cid in log.checklists" :key="cid" class="flex items-center gap-1.5 text-slate-500">
                          <Check class="w-3 h-3 text-emerald-500" />
                          <span class="truncate">{{ task.checklists.find(c => c.id === cid)?.text || 'Nhiệm vụ con' }}</span>
                        </li>
                      </ul>
                    </div>
                    <div v-if="log.files?.length" class="flex flex-wrap gap-2">
                      <div v-for="f in log.files" :key="f.name" class="flex items-center gap-1.5 bg-white border border-slate-200 px-2 py-1 rounded text-xs text-slate-600 max-w-[200px]">
                        <FileText class="w-3 h-3 text-violet-500 shrink-0" />
                        <span class="truncate">{{ f.name }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-6 text-center text-slate-400 text-sm font-medium">
              Chưa có báo cáo tiến độ nào.
            </div>
          </div>
        </div>

        <!-- Progress -->
        <div class="mb-8">
          <h3 class="text-sm font-bold text-slate-900 mb-3">Tiến độ ({{ isEditing ? editedTask.progress : task.progress }}%)</h3>
          <div class="flex items-center gap-4">
            <input 
              v-if="isEditing" 
              type="range" 
              min="0" max="100" 
              v-model="editedTask.progress"
              class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-violet-600"
            />
            <div v-else class="h-2 flex-1 bg-slate-100 rounded-full overflow-hidden">
              <div 
                class="h-full bg-gradient-to-r from-violet-500 to-indigo-500 rounded-full transition-all"
                :style="{ width: `${task.progress}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Attributes Grid -->
        <div class="grid grid-cols-2 gap-4 mb-8">
          <!-- Assignee -->
          <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide block mb-2">Người phụ trách</span>
            <div v-if="isEditing" class="mt-1">
              <select v-model="editedTask.assigneeId" class="w-full text-sm font-semibold bg-slate-100 border-none rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 cursor-pointer text-slate-800">
                <option :value="null">— Chưa phân công —</option>
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div v-else class="flex items-center gap-3">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shadow-sm', `bg-${assignee?.color || 'slate'}-500`]">
                {{ assignee?.initials || '?' }}
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-900">{{ assignee?.name || 'Chưa giao' }}</p>
                <p class="text-xs text-slate-500">{{ assignee?.role || 'Thành viên' }}</p>
              </div>
            </div>
          </div>
          
          <!-- Due Date -->
          <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide block mb-2">Hạn chót</span>
            <div v-if="isEditing" class="mt-1">
              <input type="date" v-model="editedTask.dueDate" class="w-full text-sm font-semibold bg-slate-100 border-none rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 cursor-pointer text-slate-800" />
            </div>
            <div v-else class="flex items-center gap-3">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center border', deadlineInfo.iconClass]">
                <CalendarDays class="w-4 h-4" />
              </div>
              <div>
                <p :class="['text-sm font-semibold', task.dueDate ? deadlineInfo.textClass : 'text-slate-900']">{{ formatDate(task.dueDate) || 'Chưa thiết lập' }}</p>
                <p v-if="task.dueDate" class="text-xs">
                  <span :class="['font-semibold', deadlineInfo.textClass]">{{ deadlineInfo.label }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Loại nhiệm vụ</span>
            <select v-if="isEditing" v-model="editedTask.type" class="w-full rounded-lg border-none bg-slate-100 px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-violet-500">
              <option v-for="(label, value) in taskTypeLabels" :key="value" :value="value">{{ label }}</option>
            </select>
            <p v-else class="text-sm font-bold text-slate-800">{{ taskTypeLabels[task.type] || 'Công việc' }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Ngày bắt đầu</span>
            <input v-if="isEditing" v-model="editedTask.startDate" type="date" class="w-full rounded-lg border-none bg-slate-100 px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-violet-500" />
            <p v-else class="text-sm font-bold text-slate-800">{{ formatDate(task.startDate) }}</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Ước lượng</span>
            <input v-if="isEditing" v-model="editedTask.estimatedHours" type="number" min="0" step="0.5" class="w-full rounded-lg border-none bg-slate-100 px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-violet-500" />
            <p v-else class="text-sm font-bold text-slate-800">{{ task.estimatedHours ? `${task.estimatedHours} giờ` : 'Chưa ước lượng' }}</p>
          </div>
        </div>

        <!-- Tags / Labels -->
        <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm mb-8">
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wide block mb-2">Nhãn nhiệm vụ</span>
          <div v-if="isEditing">
            <input 
              v-model="editedTask.tagsInput" 
              placeholder="Nhập nhãn, ngăn cách bằng dấu phẩy (Ví dụ: Frontend, UI/UX, Bug)" 
              class="w-full text-sm font-semibold bg-slate-100 border-none rounded-lg px-3 py-2 focus:ring-2 focus:ring-violet-500 text-slate-800" 
            />
            <p class="text-[11px] text-slate-400 mt-1.5">Ngăn cách nhiều nhãn bằng dấu phẩy (,)</p>
          </div>
          <div v-else class="flex flex-wrap gap-1.5">
            <span v-for="tag in task.tags" :key="tag" class="px-2.5 py-1 bg-violet-50 text-violet-700 border border-violet-100 rounded-lg text-xs font-semibold uppercase tracking-wide">
              {{ tag }}
            </span>
            <span v-if="!task.tags || !task.tags.length" class="text-sm text-slate-400 italic">Chưa có nhãn nhiệm vụ</span>
          </div>
        </div>

        <!-- Attachments -->
        <div class="mb-8">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Paperclip class="w-4 h-4 text-slate-400" /> Tệp đính kèm
            </h3>
            <div class="flex gap-2">
              <button @click="isDownloadModalOpen = true" :disabled="!task.files?.length" class="text-xs font-medium text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded transition-colors disabled:opacity-50">
                Tải xuống tất cả
              </button>
              <button @click="openTaskFileUpload" :disabled="isTaskFileUploading" class="text-xs font-medium text-violet-600 hover:text-violet-700 bg-violet-50 hover:bg-violet-100 px-2 py-1 rounded transition-colors disabled:opacity-50">
                {{ isTaskFileUploading ? 'Đang tải...' : '+ Tải lên' }}
              </button>
            </div>
            <input type="file" multiple ref="taskFileInput" @change="onTaskFileSelect" class="hidden" />
          </div>
          
          <div v-if="task.files?.length" class="space-y-2">
            <div v-for="file in task.files" :key="file.name" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-3 shadow-sm hover:shadow-md transition-shadow group">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center">
                  <FileText class="w-5 h-5" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-800 line-clamp-1">{{ file.name }}</p>
                  <p class="text-xs text-slate-500">{{ file.size }} • {{ formatDateTime(file.uploadedAt) }} • Tải lên bởi {{ findMember(file.uploadedBy).name }}</p>
                </div>
              </div>
              <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity gap-1">
                <button @click.prevent="downloadSingleFile(file.url || file.file_path, file.name)" title="Tải xuống" class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg"><Download class="w-4 h-4" /></button>
                <button class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg"><Trash2 class="w-4 h-4" /></button>
              </div>
            </div>
          </div>
          <div v-else class="bg-slate-50 border border-dashed border-slate-300 rounded-xl p-6 text-center">
            <Paperclip class="w-6 h-6 text-slate-300 mx-auto mb-2" />
            <p class="text-sm font-medium text-slate-500">Chưa có tệp nào đính kèm.</p>
          </div>
        </div>

        <!-- Proof of Work Attachments in Comment section -->
        <div>
          <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
            <MessageSquare class="w-4 h-4 text-slate-400" /> Bình luận ({{ taskComments.length }})
          </h3>
          
          <!-- Comment input -->
          <div class="flex gap-3 mb-6">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
              US
            </div>
            <div class="flex-1 relative">
              <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload" />
              <textarea 
                v-model="newComment"
                class="w-full bg-white border border-slate-200 rounded-xl p-3 pb-12 text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all resize-none min-h-[90px]"
                placeholder="Cập nhật tiến độ... Bạn có thể tải lên tệp minh chứng ở đây."
                @keydown.enter.prevent="submitComment"
              ></textarea>
              
              <div v-if="attachedFile" class="absolute bottom-12 left-3 right-3 bg-slate-50 p-2 mb-1 rounded-lg border border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-slate-700 truncate">
                  <Paperclip class="w-4 h-4 text-violet-500 shrink-0" />
                  <span class="truncate">{{ attachedFile.file_name }}</span>
                </div>
                <button @click="removeAttachment" class="text-slate-400 hover:text-red-500 transition-colors">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <div class="absolute bottom-2 right-2 left-2 flex items-center justify-between border-t border-slate-100 pt-2">
                <button @click="triggerFileInput" :disabled="isUploading" class="px-2 py-1 text-xs font-medium text-slate-500 hover:text-violet-600 hover:bg-violet-50 rounded flex items-center gap-1.5 transition-colors disabled:opacity-50">
                  <Paperclip class="w-3.5 h-3.5" /> {{ isUploading ? 'Đang tải...' : 'Đính kèm file' }}
                </button>
                <button 
                  @click="submitComment"
                  :disabled="(!newComment.trim() && !attachedFile) || isUploading"
                  :class="['px-3 py-1.5 rounded text-white text-xs font-medium transition-colors flex items-center gap-1.5', (newComment.trim() || attachedFile) ? 'bg-violet-600 hover:bg-violet-700' : 'bg-slate-300 cursor-not-allowed']"
                >
                  <Send class="w-3.5 h-3.5" /> Bình luận
                </button>
              </div>
            </div>
          </div>

          <!-- Comments list -->
          <div class="space-y-6">
            <div v-for="comment in taskComments" :key="comment.id" class="flex gap-3">
              <div :class="['w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0 mt-0.5 shadow-sm bg-violet-500']">
                {{ comment.user?.name ? comment.user.name.substring(0, 2).toUpperCase() : '??' }}
              </div>
              <div class="flex-1">
                <div class="bg-slate-50 border border-slate-100 rounded-2xl rounded-tl-none p-3 shadow-sm inline-block max-w-full">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm font-bold text-slate-800">{{ comment.user?.name || 'Người dùng' }}</span>
                    <span class="text-xs text-slate-400 font-medium">{{ timeAgo(comment.createdAt) }}</span>
                  </div>
                  <p v-if="comment.text" class="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed">{{ comment.text }}</p>
                  
                  <!-- Hiển thị file đính kèm -->
                  <div v-if="comment.file_url" class="mt-3 inline-flex items-center gap-3 p-2 bg-white border border-slate-200 rounded-lg shadow-sm">
                    <div class="w-8 h-8 rounded bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                      <FileText class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0 pr-4">
                      <p class="text-sm font-medium text-slate-700 truncate max-w-[200px]" :title="comment.file_name">{{ comment.file_name || 'File đính kèm' }}</p>
                    </div>
                    <button @click.prevent="downloadSingleFile(comment.file_url, comment.file_name)" title="Tải xuống" class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition-colors shrink-0">
                      <Download class="w-4 h-4" />
                    </button>
                  </div>
                  
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </div>

  <DownloadArchiveModal
    :is-open="isDownloadModalOpen"
    target-type="Task"
    :target-code="task?.id"
    @close="isDownloadModalOpen = false"
    @download="handleDownloadArchive"
  />
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
  border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
  background-color: #94a3b8;
}
</style>

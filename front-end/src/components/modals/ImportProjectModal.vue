<script setup>
import { ref, watch } from 'vue'
import { X, UploadCloud, CheckCircle2, Server, FolderKanban, Columns, Loader2, FileJson, Code, Webhook } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { importProjectModalOpen, addProject, formatBytes, notify } = useProjectWorkspace()

const step = ref(1) // 1: Select Source, 2: Uploading & Success
const selectedSource = ref('file') // 'file', 'jira', 'trello', 'asana'
const isDragging = ref(false)
const progress = ref(0)
const uploadComplete = ref(false)
const selectedFileName = ref('')
const selectedFiles = ref([])

const sources = [
  { id: 'file', name: 'File dữ liệu', desc: 'JSON, CSV, Excel', icon: FileJson, color: 'text-violet-600', bg: 'bg-violet-100', border: 'border-violet-200' },
  { id: 'github', name: 'GitHub', desc: 'Kho mã nguồn', icon: Code, color: 'text-slate-800', bg: 'bg-slate-200', border: 'border-slate-300' },
  { id: 'gitlab', name: 'GitLab', desc: 'CI/CD & Issues', icon: Webhook, color: 'text-orange-600', bg: 'bg-orange-100', border: 'border-orange-200' },
  { id: 'jira', name: 'Jira Software', desc: 'Nhập qua API Token', icon: Server, color: 'text-blue-600', bg: 'bg-blue-100', border: 'border-blue-200' },
  { id: 'trello', name: 'Trello Boards', desc: 'Đồng bộ Workspace', icon: Columns, color: 'text-sky-600', bg: 'bg-sky-100', border: 'border-sky-200' },
  { id: 'asana', name: 'Asana', desc: 'Nhập Project/Task', icon: FolderKanban, color: 'text-rose-600', bg: 'bg-rose-100', border: 'border-rose-200' },
]

const reset = () => {
  step.value = 1
  selectedSource.value = 'file'
  progress.value = 0
  uploadComplete.value = false
  selectedFileName.value = ''
  selectedFiles.value = []
  isDragging.value = false
}

// Watch to reset when closed
watch(importProjectModalOpen, (newVal) => {
  if (!newVal) {
    setTimeout(reset, 300)
  }
})

const onDragOver = (e) => {
  e.preventDefault()
  isDragging.value = true
}

const onDragLeave = (e) => {
  e.preventDefault()
  isDragging.value = false
}

const onDrop = (e) => {
  e.preventDefault()
  isDragging.value = false
  const files = e.dataTransfer.files
  if (files.length > 0) {
    handleFiles(files)
  }
}

const onFileSelect = (e) => {
  if (e.target.files.length > 0) {
    handleFiles(e.target.files)
  }
}

const handleFiles = (files) => {
  const fileArray = Array.from(files)
  selectedFiles.value = [...selectedFiles.value, ...fileArray]
  selectedFileName.value = selectedFiles.value.map(f => f.name).join(', ')
}

const removeSelectedFile = (index) => {
  selectedFiles.value.splice(index, 1)
  selectedFileName.value = selectedFiles.value.map(f => f.name).join(', ')
}

const simulateUpload = () => {
  step.value = 2
  progress.value = 0
  
  const interval = setInterval(() => {
    progress.value += Math.floor(Math.random() * 15) + 5
    if (progress.value >= 100) {
      progress.value = 100
      clearInterval(interval)
      uploadComplete.value = true
      
      // Simulate adding a project
      setTimeout(async () => {
        const sourceName = sources.find(s => s.id === selectedSource.value)?.name || 'Nguồn ngoài'
        let projName = `Dự án nhập từ ${sourceName}`
        if (selectedSource.value === 'file' && selectedFiles.value.length > 0) {
          const firstFile = selectedFiles.value[0].name
          const nameWithoutExt = firstFile.substring(0, firstFile.lastIndexOf('.')) || firstFile
          projName = `Dự án: ${nameWithoutExt}`
        }

        await addProject({
          name: projName,
          description: `Dữ liệu được đồng bộ từ ${sourceName} vào ${new Date().toLocaleDateString('vi-VN')}`,
          status: 'planning',
          color: 'indigo',
          dueDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // +30 days
          memberIds: [1],
          files: selectedSource.value === 'file' ? selectedFiles.value : []
        })
        notify('Đã tải và nhập dữ liệu dự án thành công!')
      }, 500)
    }
  }, 300)
}

const closeModal = () => {
  importProjectModalOpen.value = false
}
</script>

<template>
  <div v-if="importProjectModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6">
    <!-- Backdrop -->
    <div 
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
      @click="step === 1 ? closeModal() : null"
    ></div>

    <!-- Modal Panel -->
    <div 
      class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-300"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white z-10 shrink-0">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Tải dự án lên</h2>
          <p class="text-sm text-slate-500 font-medium mt-0.5">Nhập dữ liệu dự án từ hệ thống khác</p>
        </div>
        <button 
          v-if="step === 1 || uploadComplete"
          @click="closeModal" 
          class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Content Step 1: Select Source & Upload -->
      <div v-if="step === 1" class="p-6 overflow-y-auto custom-scrollbar">
        <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wider">1. Chọn Nguồn Dữ Liệu</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-8">
          <button
            v-for="source in sources"
            :key="source.id"
            @click="selectedSource = source.id"
            :class="[
              'p-4 rounded-2xl border-2 text-left transition-all duration-200 relative overflow-hidden group',
              selectedSource === source.id ? 'border-violet-500 bg-violet-50/50 shadow-md shadow-violet-500/10' : 'border-slate-100 bg-white hover:border-slate-200 hover:bg-slate-50'
            ]"
          >
            <div v-if="selectedSource === source.id" class="absolute top-2 right-2 text-violet-600">
              <CheckCircle2 class="w-4 h-4" />
            </div>
            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center mb-3 transition-transform group-hover:scale-110', source.bg, source.color]">
              <component :is="source.icon" class="w-5 h-5" />
            </div>
            <h4 class="font-semibold text-slate-900 text-sm leading-tight mb-1">{{ source.name }}</h4>
            <p class="text-[10px] text-slate-500 font-medium">{{ source.desc }}</p>
          </button>
        </div>

        <!-- Conditional Input Area -->
        <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wider">2. Nạp Dữ Liệu</h3>
        
        <!-- File Dropzone -->
        <div v-if="selectedSource === 'file'">
          <div 
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            :class="[
              'border-2 border-dashed rounded-3xl p-10 flex flex-col items-center justify-center text-center transition-all duration-300',
              isDragging ? 'border-violet-500 bg-violet-50 scale-[1.02]' : 'border-slate-200 bg-slate-50 hover:bg-slate-100/50',
              selectedFileName ? 'border-emerald-400 bg-emerald-50/30' : ''
            ]"
          >
            <input type="file" id="project-upload" multiple class="hidden" @change="onFileSelect" />
            
            <div v-if="selectedFiles.length === 0" class="flex flex-col items-center">
              <div class="w-16 h-16 rounded-2xl bg-white shadow-sm flex items-center justify-center mb-4 text-violet-600">
                <UploadCloud class="w-8 h-8" />
              </div>
              <h4 class="text-lg font-bold text-slate-900 mb-2">Kéo thả file dự án vào đây</h4>
              <p class="text-sm text-slate-500 font-medium mb-6">Hỗ trợ tất cả các định dạng file đính kèm</p>
              <label for="project-upload" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 font-medium rounded-xl hover:bg-slate-50 cursor-pointer transition-colors shadow-sm inline-block">
                Chọn file từ máy tính
              </label>
            </div>
            
            <div v-else class="flex flex-col items-center w-full">
              <div class="w-14 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center mb-3 text-emerald-600">
                <FileJson class="w-7 h-7" />
              </div>
              <h4 class="text-base font-bold text-slate-900 mb-1">Đã chọn {{ selectedFiles.length }} file đính kèm</h4>
              <div class="max-h-36 overflow-y-auto w-full max-w-md space-y-2 my-3 text-left custom-scrollbar pr-1">
                <div v-for="(f, idx) in selectedFiles" :key="idx" class="bg-white border border-slate-200 rounded-xl p-2.5 flex items-center justify-between shadow-sm">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0 font-bold text-xs">
                      FILE
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-slate-800 truncate">{{ f.name }}</p>
                      <p class="text-[11px] text-slate-500">{{ formatBytes(f.size) }}</p>
                    </div>
                  </div>
                  <button @click.stop="removeSelectedFile(idx)" class="text-slate-400 hover:text-rose-500 p-1.5 rounded-lg hover:bg-rose-50 transition-colors shrink-0">
                    <X class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <label for="project-upload" class="text-sm text-slate-500 hover:text-violet-600 cursor-pointer font-medium underline inline-block mt-1">
                Chọn thêm file khác
              </label>
            </div>
          </div>
        </div>

        <!-- URL/API Input for Third Party -->
        <div v-else class="bg-slate-50 border border-slate-100 rounded-3xl p-6">
          <div class="mb-4 flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center mb-3 text-slate-400">
              <Server class="w-5 h-5" />
            </div>
            <h4 class="text-base font-bold text-slate-900 mb-1">Kết nối qua API</h4>
            <p class="text-sm text-slate-500">Nhập API Token hoặc URL dự án công khai</p>
          </div>
          
          <div class="space-y-4 max-w-md mx-auto">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5 uppercase">API Token / Workspace URL</label>
              <input type="text" placeholder="Ví dụ: https://trello.com/b/xyz123..." class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-violet-500/10 focus:border-violet-400 outline-none transition-all" />
            </div>
          </div>
        </div>
      </div>

      <!-- Content Step 2: Uploading -->
      <div v-if="step === 2" class="p-12 flex flex-col items-center justify-center text-center">
        <div class="relative w-24 h-24 mb-6">
          <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
            <circle class="text-slate-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>
            <circle 
              :class="uploadComplete ? 'text-emerald-500' : 'text-violet-500'" 
              class="stroke-current transition-all duration-300 ease-out" 
              stroke-width="8" 
              stroke-linecap="round" 
              cx="50" cy="50" r="40" fill="transparent" 
              :stroke-dasharray="251.2" 
              :stroke-dashoffset="251.2 - (251.2 * progress) / 100"
            ></circle>
          </svg>
          <div class="absolute inset-0 flex items-center justify-center">
            <CheckCircle2 v-if="uploadComplete" class="w-10 h-10 text-emerald-500 animate-in zoom-in" />
            <span v-else class="text-lg font-bold text-slate-700">{{ progress }}%</span>
          </div>
        </div>
        
        <h3 class="text-xl font-bold text-slate-900 mb-2">
          {{ uploadComplete ? 'Hoàn tất tải lên!' : 'Đang xử lý dữ liệu...' }}
        </h3>
        <p class="text-slate-500 text-sm max-w-sm">
          {{ uploadComplete 
            ? 'Dữ liệu dự án đã được đồng bộ thành công vào hệ thống. Bạn đã có thể bắt đầu làm việc.' 
            : 'Vui lòng không đóng cửa sổ này trong khi chúng tôi đang đồng bộ các bản ghi nhiệm vụ và thành viên.' 
          }}
        </p>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3 shrink-0">
        <button 
          v-if="step === 1"
          @click="closeModal" 
          class="px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-200/50 rounded-xl transition-colors"
        >
          Hủy bỏ
        </button>
        <button 
          v-if="step === 1"
          @click="simulateUpload"
          :disabled="selectedSource === 'file' && selectedFiles.length === 0"
          :class="[
            'px-6 py-2.5 text-sm font-medium text-white rounded-xl transition-all shadow-md flex items-center gap-2',
            (selectedSource === 'file' && selectedFiles.length === 0)
              ? 'bg-slate-300 shadow-none cursor-not-allowed'
              : 'bg-gradient-to-r from-violet-500 to-indigo-600 hover:shadow-violet-500/25 hover:shadow-lg hover:-translate-y-0.5'
          ]"
        >
          Bắt đầu nạp dữ liệu
        </button>
        
        <button 
          v-if="uploadComplete"
          @click="closeModal"
          class="px-8 py-2.5 text-sm font-medium text-white bg-emerald-500 hover:bg-emerald-600 rounded-xl transition-colors shadow-md shadow-emerald-500/25"
        >
          Xong & Đóng
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { X, UploadCloud, File, AlertCircle } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { fileUploadModalOpen, editingProjectId, uploadFilesToProject, uploadFile } = useProjectWorkspace()

const isDragging = ref(false)
const selectedFiles = ref([])
const isUploading = ref(false)
const uploadProgress = ref(0)

watch(fileUploadModalOpen, (isOpen) => {
  if (!isOpen) {
    selectedFiles.value = []
    isUploading.value = false
    uploadProgress.value = 0
  }
})

const onDragEnter = (e) => {
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
  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    handleFiles(Array.from(e.dataTransfer.files))
  }
}

const onFileSelect = (e) => {
  if (e.target.files && e.target.files.length > 0) {
    handleFiles(Array.from(e.target.files))
  }
}

const formatBytes = (bytes, decimals = 2) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

const handleFiles = (files) => {
  const newFiles = files.map(f => ({
    fileObj: f,
    name: f.name,
    size: formatBytes(f.size),
    uploadedAt: new Date().toISOString(),
  }))
  selectedFiles.value = [...selectedFiles.value, ...newFiles]
}

const removeSelectedFile = (index) => {
  selectedFiles.value.splice(index, 1)
}

const submit = async () => {
  if (selectedFiles.value.length === 0 || !editingProjectId.value) return
  
  isUploading.value = true
  uploadProgress.value = 0
  
  const totalFiles = selectedFiles.value.length
  let uploadedCount = 0
  const uploadedFiles = []
  
  for (const file of selectedFiles.value) {
    const res = await uploadFile(file.fileObj, 'Project', editingProjectId.value)
    if (res && res.attachment) {
      uploadedFiles.push({
        code: res.attachment.code,
        name: res.attachment.file_name,
        size: formatBytes(res.attachment.size_bytes),
        uploadedAt: res.attachment.created_at || new Date().toISOString(),
        uploadedBy: res.attachment.uploaded_by,
        url: res.attachment.file_path
      })
    }
    uploadedCount++
    uploadProgress.value = (uploadedCount / totalFiles) * 100
  }
  
  if (uploadedFiles.length > 0) {
    uploadFilesToProject(editingProjectId.value, uploadedFiles)
  }
  
  isUploading.value = false
  fileUploadModalOpen.value = false
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape' && !isUploading.value) fileUploadModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="fileUploadModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="!isUploading && (fileUploadModalOpen = false)"></div>
      
      <!-- Modal Content -->
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh] overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <UploadCloud class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Tải lên tệp đính kèm</h2>
              <p class="text-sm text-slate-500">Tải tài liệu, hình ảnh lên dự án hiện tại.</p>
            </div>
          </div>
          <button v-if="!isUploading" type="button" @click="fileUploadModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div class="p-6 overflow-y-auto space-y-6 custom-scrollbar flex-1">
          <!-- Dropzone -->
          <div 
            class="relative border-2 border-dashed rounded-2xl p-8 flex flex-col items-center justify-center text-center transition-all bg-slate-50"
            :class="isDragging ? 'border-violet-500 bg-violet-50/50' : 'border-slate-300 hover:border-violet-400 hover:bg-slate-50/80'"
            @dragenter.prevent="onDragEnter"
            @dragleave.prevent="onDragLeave"
            @dragover.prevent
            @drop.prevent="onDrop"
          >
            <input type="file" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" @change="onFileSelect" :disabled="isUploading" />
            
            <div class="w-16 h-16 rounded-full bg-white shadow-sm flex items-center justify-center mb-4 text-violet-500">
              <UploadCloud class="w-8 h-8" />
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-1">Kéo thả tệp vào đây</h3>
            <p class="text-sm text-slate-500 mb-4">hoặc nhấn để duyệt tệp từ thiết bị của bạn</p>
            <span class="text-xs font-medium text-slate-400 bg-slate-200/50 px-3 py-1 rounded-full">Hỗ trợ mọi định dạng (Tối đa 10MB mỗi tệp)</span>
          </div>

          <!-- Selected Files List -->
          <div v-if="selectedFiles.length > 0" class="space-y-3">
            <h4 class="text-sm font-semibold text-slate-700">Đã chọn {{ selectedFiles.length }} tệp</h4>
            <div class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-2">
              <div v-for="(file, idx) in selectedFiles" :key="idx" class="flex items-center justify-between bg-white border border-slate-200 rounded-xl p-3">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                    <File class="w-5 h-5" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ file.name }}</p>
                    <p class="text-xs text-slate-500">{{ file.size }}</p>
                  </div>
                </div>
                <button v-if="!isUploading" @click="removeSelectedFile(idx)" class="text-slate-400 hover:text-rose-500 p-2 rounded-lg hover:bg-rose-50 transition-colors">
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
          
          <!-- Upload Progress -->
          <div v-if="isUploading" class="space-y-2">
            <div class="flex justify-between text-sm font-medium">
              <span class="text-slate-700">Đang tải lên...</span>
              <span class="text-violet-600">{{ Math.round(uploadProgress) }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-violet-500 rounded-full transition-all duration-300" :style="{ width: `${uploadProgress}%` }"></div>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="fileUploadModalOpen = false" :disabled="isUploading" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-50">
            Hủy
          </button>
          <button @click="submit" :disabled="selectedFiles.length === 0 || isUploading" class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all disabled:opacity-50 disabled:cursor-not-allowed">
            <UploadCloud v-if="!isUploading" class="w-4 h-4" />
            <svg v-else class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            {{ isUploading ? 'Đang tải lên...' : 'Bắt đầu tải lên' }}
          </button>
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

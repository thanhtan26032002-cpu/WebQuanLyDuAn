<script setup>
import { ref, watch } from 'vue'
import { X, Download, FileArchive } from '@lucide/vue'

const props = defineProps({
  isOpen: Boolean,
  targetType: String,
  targetCode: String,
})

const emit = defineEmits(['close', 'download'])

const fileName = ref('attachments')
const format = ref('.zip')
const isDownloading = ref(false)
const error = ref('')

watch(() => props.isOpen, (val) => {
  if (val) {
    fileName.value = 'attachments'
    format.value = '.zip'
    isDownloading.value = false
    error.value = ''
  }
})

const handleSubmit = () => {
  error.value = ''
  if (!fileName.value.trim()) {
    error.value = 'Vui lòng nhập tên tệp.'
    return
  }
  
  isDownloading.value = true
  // Trigger event to parent
  emit('download', {
    targetType: props.targetType,
    targetCode: props.targetCode,
    fileName: fileName.value.trim(),
    format: format.value
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div 
        class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal -->
      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <!-- Header -->
        <header class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center">
              <FileArchive class="w-5 h-5" />
            </div>
            <div>
              <h2 class="text-lg font-bold text-slate-800">Tải xuống tất cả</h2>
              <p class="text-xs text-slate-500">Đóng gói tệp đính kèm thành 1 tệp duy nhất</p>
            </div>
          </div>
          <button @click="$emit('close')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <form @submit.prevent="handleSubmit" class="p-6">
          <div class="space-y-4">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Tên tệp *</label>
              <input 
                v-model="fileName" 
                type="text" 
                @input="error = ''"
                :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', error ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']"
                placeholder="Nhập tên tệp..."
              />
              <p v-if="error" class="text-xs font-medium text-red-500 mt-1">{{ error }}</p>
            </div>
            
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Định dạng</label>
              <select 
                v-model="format" 
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none appearance-none cursor-pointer"
              >
                <option value=".zip">.ZIP (Phổ biến, khuyên dùng)</option>
                <option value=".tar">.TAR (Lưu trữ không nén)</option>
                <option value=".tar.gz">.TAR.GZ (Nén cho Linux/Mac)</option>
              </select>
            </div>
          </div>

          <!-- Footer -->
          <div class="mt-8 flex justify-end gap-3">
            <button type="button" @click="$emit('close')" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
              Hủy
            </button>
            <button 
              type="submit" 
              :disabled="isDownloading"
              class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all disabled:opacity-50"
            >
              <Download class="w-4 h-4" /> 
              {{ isDownloading ? 'Đang tải...' : 'Tải xuống' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

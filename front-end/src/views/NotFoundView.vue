<script setup>
import { useRouter } from 'vue-router'
import { Home, AlertCircle } from '@lucide/vue'

const router = useRouter()
</script>

<template>
  <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
    <!-- Decorative graphic -->
    <div class="relative mb-8">
      <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-violet-100 to-indigo-100 flex items-center justify-center mx-auto">
        <AlertCircle class="w-16 h-16 text-violet-400" />
      </div>
      <div class="absolute -top-2 -right-2 w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-black text-sm">404</div>
    </div>

    <h1 class="text-4xl font-bold text-slate-900 mb-3">Không tìm thấy trang</h1>
    <p class="text-slate-500 text-lg mb-8 max-w-md">
      Trang bạn đang tìm không tồn tại hoặc đã bị di chuyển sang địa chỉ khác.
    </p>

    <div class="flex flex-col sm:flex-row gap-3">
      <button
        @click="router.push('/')"
        class="flex items-center justify-center gap-2 bg-gradient-to-r from-violet-500 to-indigo-600 text-white px-6 py-3 rounded-xl font-medium shadow-md shadow-violet-500/25 hover:shadow-lg transition-all"
      >
        <Home class="w-5 h-5" /> Về trang chủ
      </button>
      <button
        @click="router.back()"
        class="flex items-center justify-center gap-2 bg-white border border-slate-200 text-slate-700 px-6 py-3 rounded-xl font-medium hover:bg-slate-50 transition-colors"
      >
        Quay lại
      </button>
    </div>
  </div>
</template>

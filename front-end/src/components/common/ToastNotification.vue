<script setup>
import { CheckCircle2 } from '@lucide/vue'

defineProps({
  message: {
    type: String,
    required: true
  }
})
</script>

<template>
  <div class="bg-slate-800 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 font-medium text-sm">
    <CheckCircle2 class="w-5 h-5 text-emerald-400" />
    {{ message }}
  </div>
</template>

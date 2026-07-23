<script setup>
import IconBase from './IconBase.vue'

defineProps({ title: String, subtitle: String, actionLabel: String })
defineEmits(['action'])
</script>

<template>
  <div class="section-title">
    <div><h2>{{ title }}</h2><p>{{ subtitle }}</p></div>
    <button v-if="actionLabel" class="text-button" @click="$emit('action')">{{ actionLabel }}<IconBase name="arrowRight" :size="16" /></button>
  </div>
</template>

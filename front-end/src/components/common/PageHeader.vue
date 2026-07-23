<script setup>
import IconBase from './IconBase.vue'

defineProps({
  eyebrow: String,
  title: { type: String, required: true },
  subtitle: String,
  actionLabel: String,
  actionIcon: { type: String, default: 'plus' },
})

defineEmits(['action'])
</script>

<template>
  <div class="page-heading">
    <div>
      <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
      <p v-if="subtitle">{{ subtitle }}</p>
    </div>
    <button v-if="actionLabel" class="primary-button" @click="$emit('action')">
      <IconBase :name="actionIcon" :size="18" />{{ actionLabel }}
    </button>
  </div>
</template>

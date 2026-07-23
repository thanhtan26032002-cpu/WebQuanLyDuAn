<script setup>
import { ref } from 'vue'
import { Mail, Phone, Briefcase } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const props = defineProps({
  memberId: {
    type: [Number, String],
    required: true
  },
  size: {
    type: String,
    default: 'md' // sm, md, lg
  },
  showPopover: {
    type: Boolean,
    default: true
  }
})

const { findMember } = useProjectWorkspace()
const member = computed(() => findMember(props.memberId))

const isHovered = ref(false)

const sizeClasses = {
  sm: 'w-7 h-7 text-[10px]',
  md: 'w-10 h-10 text-xs',
  lg: 'w-16 h-16 text-lg'
}
</script>

<script>
import { computed } from 'vue'
</script>

<template>
  <div 
    class="relative inline-block"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Avatar -->
    <div 
      :class="[
        'rounded-full flex items-center justify-center font-bold text-white shrink-0 cursor-pointer shadow-sm', 
        `bg-${member.color}-500`,
        sizeClasses[size]
      ]"
    >
      {{ member.initials }}
    </div>

    <!-- Popover -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0 translate-y-1"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 translate-y-1"
    >
      <div 
        v-if="showPopover && isHovered" 
        class="absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 bg-white/90 backdrop-blur-xl border border-white/40 shadow-xl rounded-2xl p-4 cursor-default pointer-events-none sm:pointer-events-auto"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
      >
        <div class="flex items-start gap-3 mb-3">
          <div :class="['w-12 h-12 rounded-full flex items-center justify-center font-bold text-white shrink-0 shadow-sm', `bg-${member.color}-500`]">
            {{ member.initials }}
          </div>
          <div>
            <h4 class="font-bold text-slate-900 leading-tight">{{ member.name }}</h4>
            <p class="text-xs text-violet-600 font-medium">{{ member.role }}</p>
            <span class="inline-block mt-1 px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-semibold uppercase tracking-wider">{{ member.department }}</span>
          </div>
        </div>
        
        <div class="space-y-2 mt-3 pt-3 border-t border-slate-100/50">
          <a :href="'mailto:' + member.email" class="flex items-center gap-2 text-xs text-slate-600 hover:text-violet-600 transition-colors pointer-events-auto">
            <Mail class="w-3.5 h-3.5 text-slate-400" /> {{ member.email }}
          </a>
          <div v-if="member.phone" class="flex items-center gap-2 text-xs text-slate-600">
            <Phone class="w-3.5 h-3.5 text-slate-400" /> {{ member.phone }}
          </div>
        </div>
        
        <!-- Triangle pointer -->
        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white border-b border-r border-slate-100 rotate-45"></div>
      </div>
    </Transition>
  </div>
</template>

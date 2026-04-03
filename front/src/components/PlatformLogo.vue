<script setup>
import { computed } from 'vue'

const props = defineProps({
  platform: { type: String, default: '' },
  size: { type: String, default: 'md' },
})

const imgHeight = computed(() => ({
  sm: 'h-5',
  md: 'h-7',
  lg: 'h-9',
}[props.size]))

const isMeta = computed(() => props.platform === 'meta')
const isGoogle = computed(() => props.platform === 'google')

const logoSrc = computed(() => {
  if (isMeta.value) return '/logos/meta.svg'
  if (isGoogle.value) return '/logos/google.svg'
  return ''
})
</script>

<template>
  <div v-if="logoSrc" class="relative flex items-center justify-center">
    <!-- Glow behind logo -->
    <div
      :class="[
        'absolute inset-0 -inset-x-2 rounded-2xl blur-xl animate-glow',
        isMeta ? 'bg-blue-500/30' : 'bg-amber-400/20',
      ]"
    />
    <!-- Logo -->
    <img
      :src="logoSrc"
      :alt="platform"
      :class="[
        imgHeight,
        'relative z-10 w-auto animate-logo-float',
        isMeta ? 'dark:brightness-0 dark:invert' : '',
      ]"
    />
  </div>
</template>

<style scoped>
@keyframes logo-float {
  0%, 100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-2px) scale(1.02);
  }
}

@keyframes glow {
  0%, 100% {
    opacity: 0.2;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.15);
  }
}

.animate-logo-float {
  animation: logo-float 3s ease-in-out infinite;
}

.animate-glow {
  animation: glow 3s ease-in-out infinite;
}
</style>

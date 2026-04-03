<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <div
    :class="
      cn('rounded-2xl border border-border/50 bg-card text-card-foreground', props.class)
    "
  >
    <slot />
  </div>
</template>

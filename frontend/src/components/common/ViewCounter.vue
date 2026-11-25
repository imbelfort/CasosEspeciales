<template>
  <p class="view-counter" :title="`${totalVisitas} visitas en toda la app`">
    <Eye :size="16" aria-hidden="true" />
    <span>Visitas en esta vista: <strong>{{ visitas }}</strong></span>
  </p>
</template>

<script setup>
import { Eye } from '@lucide/vue';
import { useViewCounter } from '../../services/useViewCounter';

const { visitas, totalVisitas } = useViewCounter();
</script>

<style scoped>
.view-counter {
  margin: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-muted);
  font-size: calc(0.875rem * var(--font-scale, 1));
}
.view-counter svg {
  flex-shrink: 0;
}
.view-counter strong {
  color: var(--text);
  font-variant-numeric: tabular-nums;
}
</style>

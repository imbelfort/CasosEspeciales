<template>
  <div class="view-counter-badge" :title="`Esta vista ha sido visitada ${visitas} veces (${totalVisitas} visitas en toda la app)`">
    <Eye :size="14" class="counter-icon" />
    <span class="counter-label">Visitas en esta vista:</span>
    <span class="counter-num">{{ visitas }}</span>
  </div>
</template>

<script setup>
import { Eye } from '@lucide/vue';
import { useViewCounter } from '../../services/useViewCounter';

const { visitas, totalVisitas } = useViewCounter();
</script>

<style scoped>
.view-counter-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.65rem;
  border-radius: 20px;
  background: var(--surface-alt);
  color: var(--text-muted);
  border: 1px solid var(--border);
  font-size: 0.78rem;
  font-weight: 600;
  box-shadow: 0 1px 2px var(--shadow-color);
  user-select: none;
}
.counter-icon {
  color: var(--primary);
  flex-shrink: 0;
}
.counter-label {
  opacity: 0.85;
}
.counter-num {
  background: var(--primary-subtle);
  color: var(--primary-text);
  padding: 0.05rem 0.4rem;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.75rem;
}
</style>

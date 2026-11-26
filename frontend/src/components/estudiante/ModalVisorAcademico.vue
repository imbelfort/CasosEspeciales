<template>
  <dialog
    ref="dialogo"
    class="visor-academico"
    :aria-labelledby="tituloId"
    @cancel.prevent="$emit('close')"
  >
    <div v-if="visible" class="visor-panel">
      <div class="modal-header">
        <div class="modal-title-with-icon">
          <component :is="icono" :size="24" class="visor-icono" />
          <div>
            <h3 :id="tituloId">{{ titulo }}</h3>
            <p class="modal-subtitle">{{ subtitulo }}</p>
          </div>
        </div>
        <button type="button" @click="$emit('close')" class="visor-cerrar" autofocus aria-label="Cerrar ventana">
          <X :size="20" />
        </button>
      </div>

      <div class="modal-body-scroll">
        <slot name="antes-del-contenido" />
        <slot />
      </div>

      <div class="modal-footer">
        <div v-if="$slots.aviso" class="footer-tip">
          <Info :size="15" class="inline-icon text-info" />
          <slot name="aviso" />
        </div>
        <button type="button" @click="$emit('close')" class="visor-listo">{{ textoCierre }}</button>
      </div>
    </div>
  </dialog>
</template>

<script setup>
import { ref, useId, watchEffect } from 'vue';
import { Info, X } from '@lucide/vue';

const props = defineProps({
  visible: Boolean,
  titulo: String,
  subtitulo: String,
  icono: [Object, Function],
  textoCierre: String
});

defineEmits(['close']);

const dialogo = ref(null);
const tituloId = useId();

watchEffect(() => {
  const dialog = dialogo.value;
  if (!dialog) return;
  if (props.visible && !dialog.open) dialog.showModal();
  if (!props.visible && dialog.open) dialog.close();
}, { flush: 'post' });
</script>

<style scoped>
.visor-academico {
  width: min(72rem, calc(100% - 2rem));
  max-width: none;
  max-height: calc(100dvh - 2rem);
  margin: auto;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
  color: var(--text);
  box-shadow: 0 20px 64px rgb(0 0 0 / 24%);
  overflow: hidden;
}

.visor-academico::backdrop {
  background: rgb(0 0 0 / 48%);
}

.visor-panel {
  display: flex;
  flex-direction: column;
  max-height: calc(100dvh - 2rem - 2px);
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
}

.modal-header { border-bottom: 1px solid var(--border); }
.modal-footer { border-top: 1px solid var(--border); }

.modal-title-with-icon {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.visor-icono {
  box-sizing: content-box;
  flex-shrink: 0;
  padding: 0.6rem;
  border-radius: var(--radius-lg);
  background: var(--primary-subtle);
  color: var(--primary-text);
}

.modal-title-with-icon h3 {
  margin: 0;
  color: var(--text);
  font-size: 1.25rem;
  line-height: 1.25;
}

.modal-subtitle,
.footer-tip {
  color: var(--text-muted);
  font-size: 0.875rem;
}

.modal-subtitle { margin: 0.25rem 0 0; }

.modal-body-scroll {
  flex: 1;
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
  padding: 1.25rem;
  background: var(--bg);
}

.footer-tip {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.footer-tip svg { flex: none; }

.visor-cerrar,
.visor-listo {
  flex: none;
  border: 0;
  cursor: pointer;
  font: inherit;
  font-weight: 650;
}

.visor-cerrar {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: var(--bg);
  color: var(--text-muted);
}

.visor-cerrar:hover { background: var(--surface-alt); }

.visor-listo {
  margin-inline-start: auto;
  min-height: 2.5rem;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-lg);
  background: var(--primary);
  color: var(--on-primary);
}

.visor-listo:hover { background: var(--primary-hover); }

.visor-academico :deep(:focus-visible) {
  outline: 2px solid var(--border-focus);
  outline-offset: 2px;
}

@media (max-width: 600px) {
  .visor-academico {
    width: calc(100% - 1rem);
    max-height: calc(100dvh - 1rem);
  }

  .visor-panel { max-height: calc(100dvh - 1rem - 2px); }

  .modal-header,
  .modal-footer,
  .modal-body-scroll { padding: 0.75rem; }

  .modal-title-with-icon h3 { font-size: 1.05rem; }
  .visor-icono { display: none; }
  .modal-footer { flex-wrap: wrap; }
}
</style>

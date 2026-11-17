<template>
  <button type="button" class="btn-action" :disabled="descargando" @click="descargar">
    <Download :size="15" aria-hidden="true" />
    {{ descargando ? 'Descargando...' : 'Descargar CSV' }}
  </button>
  <small v-if="error" role="alert">{{ error }}</small>
</template>

<script setup>
import { ref } from 'vue';
import { Download } from '@lucide/vue';
import api from '../../services/api';

const props = defineProps({
  oficio: { type: Object, required: true }
});

const descargando = ref(false);
const error = ref('');

const descargar = async () => {
  descargando.value = true;
  error.value = '';
  try {
    const { data } = await api.get(`/oficios-cpd/${props.oficio.id}/csv`, { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = `${props.oficio.numero_oficio}.csv`;
    enlace.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch {
    error.value = 'No se pudo descargar el CSV de este oficio.';
  } finally {
    descargando.value = false;
  }
};
</script>

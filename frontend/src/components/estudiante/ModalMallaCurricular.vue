<template>
  <ModalVisorAcademico
    :visible="visible"
    titulo="Malla Curricular Digital"
    subtitulo="Plan de Estudios oficial digitalizado FICCT - UAGRM."
    :icono="GraduationCap"
    texto-cierre="Cerrar Malla"
    @close="$emit('close')"
  >
    <MallaCurricularDigital
      :materias="materias"
      :materias-solicitadas-ids="materiasSolicitadasIds"
      :materias-aprobadas-ids="materiasAprobadasIds"
      :materias-inscritas-ids="materiasInscritasIds"
      @select-materia="$emit('select-materia', $event)"
    />

    <template #aviso>
      <span>Selecciona una asignatura elegible para abrir el formulario de Caso Especial.</span>
    </template>
  </ModalVisorAcademico>
</template>

<script setup>
import { GraduationCap } from '@lucide/vue';
import ModalVisorAcademico from './ModalVisorAcademico.vue';
import MallaCurricularDigital from './MallaCurricularDigital.vue';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  materias: {
    type: Array,
    default: () => []
  },
  materiasAprobadasIds: {
    type: Set,
    required: true
  },
  materiasInscritasIds: {
    type: Set,
    required: true
  },
  materiasSolicitadasIds: {
    type: Array,
    default: () => []
  }
});

defineEmits(['close', 'select-materia']);
</script>

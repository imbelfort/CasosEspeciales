<template>
  <section class="resumen-cupos" aria-label="Cupos del oficio">
    <h4>{{ titulo }}</h4>
    <p>{{ estudiantes }} estudiante(s) · {{ solicitudes.length }} caso(s) · {{ cupos }} cupo(s)</p>
    <div v-if="grupos.length" class="table-responsive" tabindex="0" role="region" aria-label="Cupos por materia y grupo">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Materia</th>
            <th scope="col">Grupo</th>
            <th scope="col">Cupos del lote</th>
            <th scope="col">Disponibles en el sistema</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="{ grupo, cantidad } in grupos" :key="grupo.id">
            <th scope="row">{{ grupo.materia?.sigla }} · {{ grupo.materia?.nombre }}</th>
            <td>{{ grupo.sigla_grupo }}</td>
            <td>{{ cantidad }}</td>
            <td>{{ grupo.cupos_disponibles ?? 'Sin dato' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  titulo: { type: String, required: true },
  solicitudes: { type: Array, required: true }
});

const estudiantes = computed(() => new Set(props.solicitudes.map(sol => sol.id_estudiante)).size);
const cupos = computed(() => grupos.value.reduce((total, fila) => total + fila.cantidad, 0));

const grupos = computed(() => {
  const porGrupo = new Map();
  for (const solicitud of props.solicitudes) {
    for (const { grupo } of solicitud.grupos || []) {
      if (!grupo) continue;
      if (!porGrupo.has(grupo.id)) porGrupo.set(grupo.id, { grupo, cantidad: 0 });
      porGrupo.get(grupo.id).cantidad++;
    }
  }
  return [...porGrupo.values()].sort((a, b) =>
    (a.grupo.materia?.sigla || '').localeCompare(b.grupo.materia?.sigla || '')
    || a.grupo.sigla_grupo.localeCompare(b.grupo.sigla_grupo));
});
</script>

<style scoped>
.resumen-cupos {
  display: grid;
  gap: 0.625rem;
  padding: 0.875rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface-alt);
}

.resumen-cupos h4,
.resumen-cupos p { margin: 0; }
.resumen-cupos p { color: var(--text-muted); }
</style>

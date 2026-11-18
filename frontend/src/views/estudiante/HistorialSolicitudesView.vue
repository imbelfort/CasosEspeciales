<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Historial y Trazabilidad de Mis Solicitudes (CU7)</h2>
        <p class="view-desc">
          Registro cronológico auditado de todas las solicitudes, cambios de estado y resoluciones efectuadas en el sistema.
        </p>
      </div>
      <button @click="cargarCasos" class="btn-secondary">
        <RefreshCw :size="15" class="inline-icon" /> Actualizar Historial
      </button>
    </div>

    <!-- TARJETAS DE HISTORIAL POR CASO -->
    <div class="historial-container">
      <div v-for="sol in solicitudes" :key="sol.id" class="historial-card">
        <div class="historial-card-header">
          <div class="card-title-group">
            <span class="code-tag">{{ sol.codigo }}</span>
            <span class="tipo-tag">{{ sol.tipo_caso }}</span>
            <span class="periodo-tag">Gestión: {{ sol.periodo?.gestion }}</span>
          </div>
          <span :class="['status-badge', sol.estado]">{{ sol.estado }}</span>
        </div>

        <div class="historial-meta">
          <p><strong>Motivo inicial:</strong> {{ sol.motivo }}</p>
          <div v-if="sol.evaluacion" class="eval-note">
            <strong>Resolución de Dirección:</strong> {{ sol.evaluacion.decision }}
            <span v-if="sol.evaluacion.justificacion"> - {{ sol.evaluacion.justificacion }}</span>
            <span v-if="sol.motivo_rechazo" class="text-danger"> (Rechazo: {{ sol.motivo_rechazo }})</span>
          </div>
        </div>

        <!-- LÍNEA DE TIEMPO DEL CASO -->
        <h4 class="timeline-title">Eventos y Auditoría Registrados (CU7):</h4>
        <div class="timeline">
          <div v-for="h in sol.historial" :key="h.id" class="timeline-item">
            <span class="timeline-dot"></span>
            <div class="timeline-content">
              <div class="timeline-header">
                <strong>{{ h.accion }}</strong>
                <span class="timeline-date">{{ formatearFecha(h.fecha_evento) }}</span>
              </div>
              <div class="timeline-transicion">
                Transición: <span class="badge-sm">{{ h.estado_anterior || 'INICIO' }}</span> 
                <ArrowRight :size="12" class="inline-icon" /> 
                <span class="badge-sm">{{ h.estado_nuevo }}</span>
              </div>
              <p class="timeline-obs">{{ h.observacion }}</p>
            </div>
          </div>
          <div v-if="!sol.historial || sol.historial.length === 0" class="text-muted">
            Sin eventos registrados en auditoría.
          </div>
        </div>
      </div>

      <div v-if="solicitudes.length === 0" class="no-records">
        <History :size="32" class="text-muted mb-2" />
        <p>No tienes antecedentes de solicitudes registradas.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RefreshCw, ArrowRight, History } from '@lucide/vue';
import api from '../../services/api';

const solicitudes = ref([]);

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const cargarCasos = async () => {
  try {
    const res = await api.get('/solicitudes/mis-casos');
    solicitudes.value = res.data;
  } catch (e) {
    console.error(e);
  }
};

onMounted(cargarCasos);
</script>

<style scoped>
.role-view {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.view-desc {
  color: #64748b;
  font-size: 0.9rem;
  margin: 0.2rem 0 0 0;
}
.btn-secondary {
  background: #64748b;
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.inline-icon {
  vertical-align: middle;
}
.historial-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.historial-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1.2rem;
  background: #f8fafc;
}
.historial-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.8rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.card-title-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.code-tag {
  background: #1e3a8a;
  color: white;
  padding: 0.25rem 0.6rem;
  border-radius: 4px;
  font-family: monospace;
  font-weight: bold;
}
.tipo-tag {
  font-weight: 600;
  color: #334155;
}
.periodo-tag {
  font-size: 0.85rem;
  color: #64748b;
}
.status-badge {
  padding: 0.25rem 0.65rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
}
.status-badge.ENVIADA { background: #fef3c7; color: #b45309; }
.status-badge.APROBADA_DIRECCION { background: #dbeafe; color: #1d4ed8; }
.status-badge.APROBADA { background: #dcfce7; color: #15803d; }
.status-badge.RECHAZADA_DIRECCION { background: #fee2e2; color: #b91c1c; }
.status-badge.REMITIDA_A_CPD { background: #f3e8ff; color: #7e22ce; }
.status-badge.HABILITADA { background: #bbf7d0; color: #166534; }
.status-badge.OBSERVADA { background: #ffedd5; color: #c2410c; }
.historial-meta {
  font-size: 0.9rem;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.8rem;
  margin-bottom: 1rem;
}
.eval-note {
  background: #eff6ff;
  padding: 0.5rem 0.8rem;
  border-radius: 4px;
  margin-top: 0.5rem;
  color: #1e40af;
}
.timeline-title {
  margin: 0 0 0.8rem 0;
  font-size: 0.9rem;
  color: #0f172a;
}
.timeline {
  border-left: 2px solid #cbd5e1;
  padding-left: 1.2rem;
}
.timeline-item {
  position: relative;
  margin-bottom: 1rem;
}
.timeline-dot {
  position: absolute;
  left: -1.55rem;
  top: 0.2rem;
  width: 10px;
  height: 10px;
  background: #1e3a8a;
  border-radius: 50%;
}
.timeline-header {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
}
.timeline-date {
  font-size: 0.8rem;
  color: #94a3b8;
}
.timeline-transicion {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0.2rem 0;
}
.badge-sm {
  background: #e2e8f0;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  font-family: monospace;
}
.timeline-obs {
  margin: 0.3rem 0 0 0;
  font-size: 0.85rem;
  color: #334155;
}
.no-records {
  text-align: center;
  padding: 3rem 1rem;
  color: #94a3b8;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.text-danger { color: #dc2626; }
.text-muted { color: #94a3b8; }
.mb-2 { margin-bottom: 0.5rem; }
</style>

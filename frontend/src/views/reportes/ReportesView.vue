<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Auditoría de Reportes y Estadísticas (CU8)</h2>
        <p class="view-desc">Métricas acumuladas del sistema y distribución porcentual dinámica de estados.</p>
      </div>
      <button @click="cargarReportes" class="btn-secondary">
        <RefreshCw :size="15" class="inline-icon" /> Actualizar Datos
      </button>
    </div>

    <!-- TARJETAS DE CONTEO GENERAL -->
    <div class="metrics-grid" v-if="reporteGeneral">
      <div class="metric-card">
        <span class="metric-label">
          <Files :size="14" class="inline-icon" /> Total Solicitudes
        </span>
        <span class="metric-val">{{ reporteGeneral.total_solicitudes }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">
          <Clock :size="14" class="inline-icon text-warning" /> Pendientes Evaluación
        </span>
        <span class="metric-val text-warning">{{ reporteGeneral.pendientes_evaluacion }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">
          <CheckCircle2 :size="14" class="inline-icon text-info" /> Aprobadas Dirección
        </span>
        <span class="metric-val text-info">{{ reporteGeneral.aprobadas_direccion }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">
          <CreditCard :size="14" class="inline-icon text-success" /> Listas con Pago
        </span>
        <span class="metric-val text-success">{{ reporteGeneral.aprobadas_con_pago }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">
          <Send :size="14" class="inline-icon text-primary" /> Remitidas CPD
        </span>
        <span class="metric-val text-primary">{{ reporteGeneral.remitidas_cpd }}</span>
      </div>
      <div class="metric-card">
        <span class="metric-label">
          <CheckCheck :size="14" class="inline-icon text-success" /> Habilitadas CPD
        </span>
        <span class="metric-val text-success">{{ reporteGeneral.habilitadas_cpd }}</span>
      </div>
    </div>

    <!-- DISTRIBUCIÓN PORCENTUAL DINÁMICA CU8 -->
    <h3 class="mt-4">
      <PieChart :size="18" class="inline-icon" /> Distribución Porcentual por Estado (ROUND(COUNT(*) * 100.0 / total, 2))
    </h3>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Estado</th>
            <th>Cantidad</th>
            <th>Porcentaje del Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="est in reporteEstados" :key="est.estado">
            <td><span :class="['status-badge', est.estado]">{{ est.estado }}</span></td>
            <td>{{ est.cantidad }}</td>
            <td><strong>{{ est.porcentaje }}%</strong></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { 
  RefreshCw, 
  Files, 
  Clock, 
  CheckCircle2, 
  CreditCard, 
  Send, 
  CheckCheck, 
  PieChart 
} from '@lucide/vue';
import api from '../../services/api';

const reporteGeneral = ref(null);
const reporteEstados = ref([]);

const cargarReportes = async () => {
  try {
    const [resG, resE] = await Promise.all([
      api.get('/reportes/general'),
      api.get('/reportes/por-estado')
    ]);
    reporteGeneral.value = resG.data;
    reporteEstados.value = resE.data?.estados || [];
  } catch (e) {
    console.error(e);
  }
};

onMounted(cargarReportes);
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
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}
.metric-card {
  background: #f8fafc;
  padding: 1.2rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.metric-label {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 600;
}
.metric-val {
  display: block;
  font-size: 1.8rem;
  font-weight: 800;
  margin-top: 0.4rem;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
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
.text-warning { color: #d97706; }
.text-info { color: #0284c7; }
.text-success { color: #16a34a; }
.text-primary { color: #2563eb; }
.inline-icon { vertical-align: middle; }
.mt-4 { margin-top: 1.5rem; }
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
</style>

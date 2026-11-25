<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Centro de Notificaciones y Alertas (CU7)</h2>
        <p class="view-desc">Avisos automáticos sobre recepciones, evaluaciones de Dirección y respuestas técnicas de CPD.</p>
      </div>
      <button @click="cargarNotificaciones" class="btn-secondary">
        <RefreshCw :size="15" class="inline-icon" /> Actualizar
      </button>
    </div>

    <!-- LISTADO DE NOTIFICACIONES -->
    <div class="notif-list">
      <div 
        v-for="n in notificaciones" 
        :key="n.id" 
        class="notif-card"
        :class="{ 'no-leida': !n.fecha_lectura }"
      >
        <div class="notif-icon-col">
          <Bell v-if="!n.fecha_lectura" :size="20" class="text-primary" />
          <CheckCheck v-else :size="20" class="text-muted" />
        </div>
        <div class="notif-content">
          <div class="notif-header">
            <h4>{{ n.asunto }}</h4>
            <span class="notif-time">{{ formatearFecha(n.fecha_creacion) }}</span>
          </div>
          <p class="notif-msg">{{ n.mensaje }}</p>
          <div class="notif-footer">
            <span v-if="n.solicitud" class="code-tag">
              Caso: {{ n.solicitud.codigo }}
            </span>
            <button 
              v-if="!n.fecha_lectura" 
              @click="marcarComoLeida(n)" 
              class="btn-text"
            >
              Marcar como leída
            </button>
            <span v-else class="read-tag">Leída</span>
          </div>
        </div>
      </div>

      <div v-if="notificaciones.length === 0" class="no-records">
        <Inbox :size="32" class="text-muted mb-2" />
        <p>No tienes notificaciones registradas en este momento.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RefreshCw, Bell, CheckCheck, Inbox } from '@lucide/vue';
import api from '../../services/api';

const notificaciones = ref([]);

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const cargarNotificaciones = async () => {
  try {
    const res = await api.get('/notificaciones');
    notificaciones.value = res.data;
  } catch (e) {
    console.error(e);
  }
};

const marcarComoLeida = async (n) => {
  try {
    await api.post(`/notificaciones/${n.id}/leer`);
    n.fecha_lectura = new Date().toISOString();
  } catch (e) {
    console.error(e);
  }
};

onMounted(cargarNotificaciones);
</script>

<style scoped>
.role-view {
  background: var(--surface);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border);
}
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.view-desc {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0.2rem 0 0 0;
}
.inline-icon {
  vertical-align: middle;
}
.notif-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.notif-card {
  display: flex;
  gap: 1rem;
  padding: 1.2rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface-alt);
  transition: all 0.2s;
}
.notif-card.no-leida {
  background: var(--surface);
  border-left: 4px solid #1e3a8a;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.notif-icon-col {
  padding-top: 0.2rem;
}
.notif-content {
  flex: 1;
}
.notif-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.4rem;
}
.notif-header h4 {
  margin: 0;
  font-size: 1rem;
  color: var(--text);
}
.notif-time {
  font-size: 0.8rem;
  color: #94a3b8;
}
.notif-msg {
  margin: 0 0 0.8rem 0;
  font-size: 0.9rem;
  color: #475569;
  line-height: 1.4;
}
.notif-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.code-tag {
  background: #e2e8f0;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.8rem;
}
.btn-text {
  background: none;
  border: none;
  color: #1e3a8a;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  padding: 0;
}
.read-tag {
  font-size: 0.8rem;
  color: #94a3b8;
}
.no-records {
  text-align: center;
  padding: 3rem 1rem;
  color: #94a3b8;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.text-primary { color: #1e3a8a; }
.text-muted { color: #94a3b8; }
.mb-2 { margin-bottom: 0.5rem; }
</style>

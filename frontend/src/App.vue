<template>
  <div class="app-container">
    <Navbar :usuario="usuario" @logout="onLogout" />
    
    <!-- Barra de Navegación Estricta por Rol (RBAC de base_datos_actual.md) -->
    <div v-if="usuario" class="role-nav-bar">
      <div class="nav-links">
        <!-- VISTAS DEL ESTUDIANTE -->
        <template v-if="rolActual === 'ESTUDIANTE'">
          <router-link to="/estudiante" class="nav-link" :class="{ active: $route.path === '/estudiante' }">
            <FileText :size="16" class="nav-icon" /> Mis Solicitudes (CU2)
          </router-link>
          <router-link to="/estudiante/historial" class="nav-link" :class="{ active: $route.path === '/estudiante/historial' }">
            <History :size="16" class="nav-icon" /> Historial de Casos (CU7)
          </router-link>
          <router-link to="/estudiante/notificaciones" class="nav-link" :class="{ active: $route.path === '/estudiante/notificaciones' }">
            <Bell :size="16" class="nav-icon" /> Notificaciones
            <span v-if="notificacionesNoLeidas > 0" class="badge-count">{{ notificacionesNoLeidas }}</span>
          </router-link>
        </template>

        <!-- VISTAS DE DIRECCIÓN Y PERSONAL ACADÉMICO -->
        <template v-if="rolActual === 'DIRECTOR' || rolActual === 'PERSONAL_ACADEMICO'">
          <router-link to="/direccion" class="nav-link">
            <GraduationCap :size="16" class="nav-icon" /> Dirección de Carrera (CU3/CU4/CU5)
          </router-link>
          <router-link to="/reportes" class="nav-link">
            <BarChart3 :size="16" class="nav-icon" /> Reportes y Estadísticas (CU8)
          </router-link>
        </template>

        <!-- VISTAS DE CPD -->
        <template v-if="rolActual === 'CPD'">
          <router-link to="/cpd" class="nav-link">
            <Server :size="16" class="nav-icon" /> Procesamiento CPD (CU6)
          </router-link>
          <router-link to="/reportes" class="nav-link">
            <BarChart3 :size="16" class="nav-icon" /> Reportes y Estadísticas (CU8)
          </router-link>
        </template>
      </div>
    </div>

    <main class="content-wrapper">
      <router-view :usuario="usuario" @auth-success="onAuthSuccess" />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { 
  FileText, 
  History, 
  Bell, 
  GraduationCap, 
  Server, 
  BarChart3 
} from '@lucide/vue';
import Navbar from './components/layout/Navbar.vue';
import api from './services/api';

const route = useRoute();
const usuario = ref(null);
const notificacionesNoLeidas = ref(0);

const rolActual = computed(() => {
  if (!usuario.value || !usuario.value.roles || usuario.value.roles.length === 0) return 'ESTUDIANTE';
  return usuario.value.roles[0].nombre;
});

const onAuthSuccess = (u) => {
  usuario.value = u;
  cargarNotificacionesCount();
};

const onLogout = () => {
  usuario.value = null;
  notificacionesNoLeidas.value = 0;
};

const cargarNotificacionesCount = async () => {
  if (!usuario.value || rolActual.value !== 'ESTUDIANTE') return;
  try {
    const res = await api.get('/notificaciones');
    notificacionesNoLeidas.value = res.data.filter(n => !n.fecha_lectura).length;
  } catch (e) {}
};

watch(() => route.path, () => {
  if (usuario.value && rolActual.value === 'ESTUDIANTE') {
    cargarNotificacionesCount();
  }
});

onMounted(async () => {
  const token = localStorage.getItem('token');
  if (token) {
    try {
      const res = await api.get('/auth/me');
      usuario.value = res.data;
      cargarNotificacionesCount();
    } catch (e) {
      localStorage.removeItem('token');
      usuario.value = null;
    }
  }
});
</script>

<style scoped>
.app-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.role-nav-bar {
  background: white;
  border-bottom: 1px solid #e2e8f0;
  padding: 0.5rem 2rem;
}
.nav-links {
  display: flex;
  gap: 0.8rem;
  max-width: 1280px;
  margin: 0 auto;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  text-decoration: none;
  color: #475569;
  font-weight: 600;
  border-radius: 6px;
  transition: all 0.2s;
  font-size: 0.9rem;
}
.nav-link.router-link-active, .nav-link.active {
  background: #1e3a8a;
  color: white;
}
.nav-icon {
  flex-shrink: 0;
}
.badge-count {
  background: #ef4444;
  color: white;
  font-size: 0.7rem;
  padding: 0.1rem 0.4rem;
  border-radius: 10px;
  font-weight: bold;
}
.content-wrapper {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 1rem;
}
</style>

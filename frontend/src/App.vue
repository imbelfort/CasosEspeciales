<template>
  <div class="app-container">
    <Navbar :usuario="usuario" @logout="onLogout" />
    
    <!-- Navegación por rol -->
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
        </template>

        <!-- VISTAS DE DIRECCIÓN Y PERSONAL ACADÉMICO -->
        <template v-if="rolActual === 'DIRECTOR' || rolActual === 'PERSONAL_ACADEMICO'">
          <router-link to="/direccion" class="nav-link">
            <GraduationCap :size="16" class="nav-icon" /> Dirección de Carrera (CU3/CU4/CU5)
          </router-link>
        </template>

        <!-- VISTAS DE CPD -->
        <template v-if="rolActual === 'CPD'">
          <router-link to="/cpd" class="nav-link">
            <Server :size="16" class="nav-icon" /> Procesamiento CPD (CU6)
          </router-link>
        </template>
        <router-link v-if="usuario.roles?.some(rol => ['CPD', 'DIRECTOR', 'PERSONAL_ACADEMICO'].includes(rol.nombre))" to="/reportes" class="nav-link">
          <BarChart3 :size="16" class="nav-icon" /> Reportes y Estadísticas (CU8)
        </router-link>
        <router-link v-if="usuario.roles?.some(rol => rol.nombre === 'CPD')" to="/cpd/bitacora" class="nav-link">
          <History :size="16" class="nav-icon" /> Bitácora CPD
        </router-link>
      </div>

    </div>

    <main class="content-wrapper">
      <router-view :usuario="usuario" @auth-success="onAuthSuccess" />
    </main>

    <footer class="app-footer">
      <div class="footer-content">
        <p class="footer-brand"><strong><a href="http://localhost:5173/">Casos Especiales</a></strong></p>
        <ViewCounter />
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { 
  FileText, 
  History, 
  GraduationCap, 
  Server, 
  BarChart3 
} from '@lucide/vue';
import Navbar from './components/layout/Navbar.vue';
import ViewCounter from './components/common/ViewCounter.vue';
import api from './services/api';

const usuario = ref(null);

const rolActual = computed(() => {
  if (!usuario.value || !usuario.value.roles || usuario.value.roles.length === 0) return 'ESTUDIANTE';
  return usuario.value.roles[0].nombre;
});

const onAuthSuccess = (u) => {
  usuario.value = u;
};

const onLogout = () => {
  usuario.value = null;
};

onMounted(async () => {
  const token = localStorage.getItem('token');
  if (token) {
    try {
      const res = await api.get('/auth/me');
      usuario.value = res.data;
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
  background-color: var(--bg);
  color: var(--text);
  transition: background-color 0.25s ease, color 0.25s ease;
}
.role-nav-bar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0.45rem 1.8rem;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.8rem;
}
.nav-links {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.8rem;
  text-decoration: none;
  color: var(--text-muted);
  font-weight: 600;
  border-radius: var(--radius-pill, 100px);
  transition: all 0.15s ease;
  font-size: 0.82rem;
  line-height: 1.35;
  border: 1px solid transparent;
}
.nav-link:hover {
  background: var(--surface-alt);
  color: var(--text);
}
.nav-link.router-link-exact-active, .nav-link.active {
  background: var(--primary);
  color: var(--on-primary);
}
.nav-link:focus-visible {
  outline: 2px solid var(--text);
  outline-offset: 2px;
}
.nav-icon {
  flex-shrink: 0;
}
.app-footer {
  background: var(--surface);
  border-top: 1px solid var(--border);
}
.footer-content {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem 1rem;
  padding: 0.75rem 1rem;
  max-width: 1280px;
  margin: 0 auto;
}
.footer-brand {
  margin: 0;
  font-size: calc(0.875rem * var(--font-scale, 1));
}
.footer-brand a {
  color: inherit;
  text-underline-offset: 0.15em;
}
.footer-brand a:focus-visible {
  outline: 2px solid var(--text);
  outline-offset: 2px;
}
.content-wrapper {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 1rem;
}
</style>

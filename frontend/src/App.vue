<template>
  <div class="app-container">
    <Navbar :usuario="usuario" @logout="onLogout">
      <template v-if="usuario" #navegacion>
      <div class="nav-links">
        <!-- VISTAS DEL ESTUDIANTE -->
        <template v-if="rolActual === 'ESTUDIANTE'">
          <router-link to="/estudiante" class="nav-link" :class="{ active: $route.path === '/estudiante' }">
            Mis solicitudes
          </router-link>
          <router-link to="/estudiante/historial" class="nav-link" :class="{ active: $route.path === '/estudiante/historial' }">
            Historial de casos
          </router-link>
        </template>

        <!-- VISTAS DE DIRECCIÓN Y PERSONAL ACADÉMICO -->
        <template v-if="rolActual === 'DIRECTOR' || rolActual === 'PERSONAL_ACADEMICO'">
          <router-link to="/direccion" class="nav-link">
            Dirección de Carrera
          </router-link>
        </template>

        <!-- VISTAS DE CPD -->
        <template v-if="usuario.roles?.some(rol => rol.nombre === 'CPD')">
          <router-link to="/cpd" class="nav-link">
            Procesamiento CPD
          </router-link>
          <router-link to="/cpd/usuarios" class="nav-link">
            Registro de usuarios
          </router-link>
        </template>
        <router-link v-if="usuario.roles?.some(rol => ['CPD', 'DIRECTOR', 'PERSONAL_ACADEMICO'].includes(rol.nombre))" to="/reportes" class="nav-link">
          Reportes y estadísticas
        </router-link>
        <router-link v-if="usuario.roles?.some(rol => rol.nombre === 'CPD')" to="/cpd/bitacora" class="nav-link">
          Bitácora CPD
        </router-link>
      </div>

      </template>
    </Navbar>

    <main class="content-wrapper">
      <router-view :usuario="usuario" @auth-success="onAuthSuccess" />
    </main>

    <footer class="app-footer">
      <div class="footer-content">
        <p class="footer-brand"><strong><router-link to="/">Casos Especiales</router-link></strong></p>
        <ViewCounter />
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
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
.nav-links {
  display: flex;
  justify-content: center;
  gap: .25rem;
  flex-wrap: wrap;
}
.nav-link {
  display: flex;
  align-items: center;
  padding: .6rem .85rem;
  text-decoration: none;
  color: var(--text);
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
  background: var(--primary-subtle);
  color: var(--primary-text);
}
.nav-link:focus-visible {
  outline: 2px solid var(--text);
  outline-offset: 2px;
}
@media (max-width: 48rem) {
  .nav-links { flex-direction: column; }
  .nav-link { min-height: 2.75rem; }
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
@media (max-width: 36rem) {
  .footer-content {
    flex-direction: column;
    text-align: center;
  }
}
.content-wrapper {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 1rem;
}
</style>

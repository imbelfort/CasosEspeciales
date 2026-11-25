import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '../views/auth/LoginView.vue';
import EstudianteDashboard from '../views/estudiante/EstudianteDashboard.vue';
import HistorialSolicitudesView from '../views/estudiante/HistorialSolicitudesView.vue';
import DireccionDashboard from '../views/direccion/DireccionDashboard.vue';
import CpdDashboard from '../views/cpd/CpdDashboard.vue';
import ReportesView from '../views/reportes/ReportesView.vue';

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', component: LoginView, meta: { guest: true } },
  
  // Rutas Estudiante
  { path: '/estudiante', component: EstudianteDashboard, meta: { auth: true, role: 'ESTUDIANTE' } },
  { path: '/estudiante/historial', component: HistorialSolicitudesView, meta: { auth: true, role: 'ESTUDIANTE' } },
  { path: '/estudiante/notificaciones', redirect: { path: '/estudiante', query: { avisos: '1' } } },

  // Rutas Administrativas
  { path: '/direccion', component: DireccionDashboard, meta: { auth: true, roles: ['DIRECTOR', 'PERSONAL_ACADEMICO'] } },
  { path: '/cpd', component: CpdDashboard, meta: { auth: true, roles: ['CPD', 'DIRECTOR'] } },
  { path: '/cpd/bitacora', component: () => import('../views/cpd/BitacoraCpdView.vue'), meta: { auth: true, role: 'CPD' } },
  
  // Reportes exclusivo Administrativos / CPD
  { path: '/reportes', component: ReportesView, meta: { auth: true, roles: ['DIRECTOR', 'PERSONAL_ACADEMICO', 'CPD'] } },
  
  { path: '/:pathMatch(.*)*', redirect: '/login' }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  if (to.meta.auth && !token) {
    next({ path: '/login', query: to.query.avisos === '1' ? { avisos: '1' } : {} });
  } else if (to.meta.guest && token) {
    next({ path: '/estudiante', query: to.query.avisos === '1' ? { avisos: '1' } : {} });
  } else {
    next();
  }
});

export default router;

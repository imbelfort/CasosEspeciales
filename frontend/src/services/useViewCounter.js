import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';

export function useViewCounter() {
  const route = useRoute();
  const visitas = ref(0);
  const totalVisitas = ref(0);

  const incrementarVisita = () => {
    const claveRuta = `visitas_${route.name || route.path.replace(/\//g, '_') || 'inicio'}`;
    const actual = parseInt(localStorage.getItem(claveRuta) || '0') + 1;
    localStorage.setItem(claveRuta, actual.toString());
    visitas.value = actual;

    const globalTotal = parseInt(localStorage.getItem('visitas_globales_total') || '0') + 1;
    localStorage.setItem('visitas_globales_total', globalTotal.toString());
    totalVisitas.value = globalTotal;
  };

  onMounted(() => {
    incrementarVisita();
  });

  watch(() => route.path, () => {
    incrementarVisita();
  });

  return {
    visitas,
    totalVisitas,
  };
}

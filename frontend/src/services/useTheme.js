import { ref } from 'vue';

// Estado global reactivo
const tema = ref(localStorage.getItem('theme_preset') || 'institucional'); // institucional | esmeralda | purpura
const modoOscuro = ref(localStorage.getItem('theme_dark') === 'true');
const escalaFuente = ref(parseInt(localStorage.getItem('theme_font_scale') || '100')); // 85% a 130%

export function useTheme() {
  const aplicarConfiguracion = () => {
    const root = document.documentElement;

    // Aplicar clase o atributo dark
    if (modoOscuro.value) {
      root.classList.add('dark');
      root.setAttribute('data-mode', 'dark');
    } else {
      root.classList.remove('dark');
      root.setAttribute('data-mode', 'light');
    }

    // Aplicar tema de color
    root.setAttribute('data-theme', tema.value);

    // Aplicar escala de fuente
    root.style.setProperty('--font-scale', `${escalaFuente.value / 100}`);
    root.style.fontSize = `${16 * (escalaFuente.value / 100)}px`;

    // Persistir en LocalStorage
    localStorage.setItem('theme_preset', tema.value);
    localStorage.setItem('theme_dark', modoOscuro.value ? 'true' : 'false');
    localStorage.setItem('theme_font_scale', escalaFuente.value.toString());
  };

  const setTema = (nuevoTema) => {
    tema.value = nuevoTema;
    aplicarConfiguracion();
  };

  const toggleModoOscuro = () => {
    modoOscuro.value = !modoOscuro.value;
    aplicarConfiguracion();
  };

  const aumentarFuente = () => {
    if (escalaFuente.value < 130) {
      escalaFuente.value += 5;
      aplicarConfiguracion();
    }
  };

  const disminuirFuente = () => {
    if (escalaFuente.value > 85) {
      escalaFuente.value -= 5;
      aplicarConfiguracion();
    }
  };

  const reiniciarFuente = () => {
    escalaFuente.value = 100;
    aplicarConfiguracion();
  };

  return {
    tema,
    modoOscuro,
    escalaFuente,
    setTema,
    toggleModoOscuro,
    aumentarFuente,
    disminuirFuente,
    reiniciarFuente,
    aplicarConfiguracion
  };
}

import { ref } from 'vue';

// 3 Temas por grupo de edad: 'ninos' | 'jovenes' | 'adultos'
const tema = ref(localStorage.getItem('theme_preset') || 'jovenes');

// Modo oscuro: 'light' | 'dark' | 'auto' (según la hora: noche 19:00 - 06:59 activa oscuro)
const modoOscuro = ref(localStorage.getItem('theme_dark_mode') || 'auto');

// Alto contraste: boolean
const altoContraste = ref(localStorage.getItem('theme_high_contrast') === 'true');

// Tamaño de fuente a elegir: 80% a 140% (pasos de 5%)
const escalaFuente = ref(parseInt(localStorage.getItem('theme_font_scale') || '100'));

let autoTimer = null;

export function useTheme() {
  const esNocheSegunHora = () => {
    const hora = new Date().getHours();
    return hora >= 19 || hora < 7;
  };

  const aplicarConfiguracion = () => {
    const root = document.documentElement;

    // 1. Determinar modo activo (claro u oscuro)
    let oscuroActivo = false;
    if (modoOscuro.value === 'dark') {
      oscuroActivo = true;
    } else if (modoOscuro.value === 'light') {
      oscuroActivo = false;
    } else if (modoOscuro.value === 'auto') {
      oscuroActivo = esNocheSegunHora();
    }

    if (oscuroActivo) {
      root.classList.add('dark');
      root.setAttribute('data-mode', 'dark');
    } else {
      root.classList.remove('dark');
      root.setAttribute('data-mode', 'light');
    }

    // 2. Aplicar Tema (niños, jóvenes, adultos)
    root.setAttribute('data-theme', tema.value);

    // 3. Aplicar Alto Contraste
    if (altoContraste.value) {
      root.classList.add('high-contrast');
      root.setAttribute('data-contrast', 'high');
    } else {
      root.classList.remove('high-contrast');
      root.removeAttribute('data-contrast');
    }

    // 4. Aplicar Escala de Fuente
    root.style.setProperty('--font-scale', `${escalaFuente.value / 100}`);
    root.style.fontSize = `${16 * (escalaFuente.value / 100)}px`;

    // Persistir configuración
    localStorage.setItem('theme_preset', tema.value);
    localStorage.setItem('theme_dark_mode', modoOscuro.value);
    localStorage.setItem('theme_high_contrast', altoContraste.value ? 'true' : 'false');
    localStorage.setItem('theme_font_scale', escalaFuente.value.toString());

    // Configurar temporizador para modo auto si no existe
    if (modoOscuro.value === 'auto' && !autoTimer) {
      autoTimer = setInterval(() => {
        if (modoOscuro.value === 'auto') {
          aplicarConfiguracion();
        }
      }, 60000); // Chequear cada minuto
    }
  };

  const setTema = (nuevoTema) => {
    tema.value = nuevoTema;
    aplicarConfiguracion();
  };

  const setModoOscuro = (modo) => {
    // modo: 'light' | 'dark' | 'auto'
    modoOscuro.value = modo;
    aplicarConfiguracion();
  };

  const toggleAltoContraste = () => {
    altoContraste.value = !altoContraste.value;
    aplicarConfiguracion();
  };

  const setEscalaFuente = (valor) => {
    escalaFuente.value = Math.min(140, Math.max(80, parseInt(valor)));
    aplicarConfiguracion();
  };

  const aumentarFuente = () => {
    if (escalaFuente.value < 140) {
      escalaFuente.value += 5;
      aplicarConfiguracion();
    }
  };

  const disminuirFuente = () => {
    if (escalaFuente.value > 80) {
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
    altoContraste,
    escalaFuente,
    setTema,
    setModoOscuro,
    toggleAltoContraste,
    setEscalaFuente,
    aumentarFuente,
    disminuirFuente,
    reiniciarFuente,
    aplicarConfiguracion,
  };
}

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
    './storage/framework/views/*.php',
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './app/**/*.php',
  ],

  /**
   * SAFELIST
   * Используем ТОЛЬКО для:
   * – динамических классов
   * – состояний (active:, hover:, focus-visible:)
   * – градиентов и теней
   * – z-index / safe-area утилит (важно для bottom-nav и bottom-sheet)
   */
  safelist: [
    /* =====================================================
     * BRAND COLORS (POOF)
     * ===================================================== */
    'bg-poof-400','bg-poof-500','bg-poof-600',
    'text-poof-400','text-poof-500','text-poof-600',
    'border-poof-400','border-poof-400/30',
    'from-poof-400','from-poof-400/30',
    'via-poof-400','to-poof-400',

    /* ===============================
     * RADIO / INDICATORS
     * =============================== */
    'border-neutral-600',
    'border-yellow-400',
    'w-5','h-5',
    'w-2','h-2',

    /* ===============================
     * DIVIDER
     * =============================== */
    'shadow-[inset_0_1px_1px_rgba(0,0,0,0.6)]',
    'shadow-[inset_0_2px_3px_rgba(0,0,0,0.8)]',

    /* =====================================================
     * STATUS / ACCENT COLORS
     * ===================================================== */
    'bg-yellow-300','bg-yellow-400','bg-yellow-500',
    'text-yellow-300','text-yellow-400','text-yellow-500',
    'bg-green-400','bg-green-500',
    'text-green-400','text-green-500',
    'bg-blue-400','bg-blue-500',
    'text-blue-400','text-blue-500',
    'bg-red-400','bg-red-500',
    'text-red-400','text-red-500',

    /* =====================================================
     * DARK THEME (GRAY / NEUTRAL)
     * ===================================================== */
    'bg-gray-700','bg-gray-800','bg-gray-900','bg-gray-950',
    'bg-neutral-800','bg-neutral-900','bg-neutral-950',
    'text-white','text-black',
    'text-gray-200','text-gray-300','text-gray-400','text-gray-500',
    'border-gray-700','border-gray-800','border-gray-950',
    'border-neutral-700','border-neutral-800','border-neutral-900',

    /* =====================================================
     * OPACITY / GLASS
     * ===================================================== */
    'bg-gray-700/70',
    'bg-gray-900/40',
    'bg-black/20',
    'bg-black/70',
    'border-white/5',
    'border-green-400/30',
    'bg-blue-500/90',

    // ✅ ДОБАВИЛ: твои "отсутствующие" классы
    'bg-white/5',
    'bg-white/10',
    'border-white/10',
    'text-white/60',
    'placeholder:text-white/40',

    /* =====================================================
     * GRADIENTS (Buttons / Cards)
     * ===================================================== */
    'bg-gradient-to-b','bg-gradient-to-t','bg-gradient-to-r',
    'from-yellow-300','from-yellow-400','from-yellow-500',
    'via-yellow-300','via-yellow-400',
    'to-yellow-300','to-yellow-400','to-yellow-500',
    'from-gray-950','via-gray-900','to-gray-800',
    'from-transparent','to-transparent',

    /* =====================================================
     * SHADOW / DEPTH (очень важно для кнопок)
     * ===================================================== */
    'shadow-sm','shadow-md','shadow-lg',
    'shadow-green-400/20',
    'shadow-yellow-400/10','shadow-yellow-400/20','shadow-yellow-400/25','shadow-yellow-400/40',
    'hover:shadow-yellow-400/40',

    /* =====================================================
     * RADIUS / SHAPE
     * ===================================================== */
    'rounded-lg','rounded-xl','rounded-2xl','rounded-3xl','rounded-full',

    /* =====================================================
     * INTERACTION / ANIMATION
     * ===================================================== */
    'transition-all',
    'transition-colors',
    'transition-transform',
    'duration-150','duration-200','duration-300',
    'ease-out',
    'active:scale-95',
    'hover:bg-gray-700',
    'hover:bg-gray-800',
    'hover:text-gray-200',

    /* =====================================================
     * FOCUS / A11Y (как в топовых приложениях)
     * ===================================================== */
    'focus:outline-none',
    'focus:ring-2',
    'focus:ring-yellow-400/30',
    'focus-visible:outline-none',
    'focus-visible:ring-2',
    'focus-visible:ring-yellow-400/40',
    'focus-visible:ring-yellow-400/50',

    /* =====================================================
     * TYPOGRAPHY
     * ===================================================== */
    'text-xs','text-sm','text-center',
    'font-medium','font-semibold','font-bold',
    'leading-snug',

    /* =====================================================
     * LAYOUT HELPERS (используются динамически)
     * ===================================================== */
    'fixed','bottom-0','inset-x-0',
    'mx-auto','max-w-md',

    /* =====================================================
     * Z-INDEX SCALE (важно для nav/sheets/modals)
     * ===================================================== */
    'z-0','z-10','z-20','z-30','z-40','z-50',
    'z-60','z-61','z-70','z-80','z-90','z-100',

    // ✅ Арбитрарные z-index (стабильно для Tailwind JIT)
    'z-[1]','z-[10]','z-[30]','z-[40]','z-[50]','z-[60]','z-[61]','z-[100]','z-[999]','z-[9999]',

    /* =====================================================
     * SAFE AREA (если используешь свои утилиты)
     * ===================================================== */
    'pb-safe','pt-safe',
  ],

  theme: {
    extend: {
      colors: {
        poof: {
          400: 'rgb(244 203 87)', // основной бренд
          500: 'rgb(234 193 77)', // hover
          600: 'rgb(214 173 57)', // pressed
        },
      },

      /**
       * ✅ Расширяем zIndex официально
       */
      zIndex: {
        60: '60',
        61: '61',
        70: '70',
        80: '80',
        90: '90',
        100: '100',
        999: '999',
        9999: '9999',
      },
    },
  },

  plugins: [],
}

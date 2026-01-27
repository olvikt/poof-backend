import initCarousel from './carousel'
import initMap from './map'

(() => {
  // ❗ защита от повторной инициализации
  if (window.__POOF_ORDER_CREATE_INIT__) return;
  window.__POOF_ORDER_CREATE_INIT__ = true;

  window.POOF = window.POOF || {};
  const POOF = window.POOF;

  initCarousel()
  initMap()

  // ------------------------------------------------------------
  // Date Picker (global access)
  // ------------------------------------------------------------
  function openDatePicker() {
    const input = document.createElement('input');
    input.type = 'date';
    input.style.position = 'fixed';
    input.style.opacity = '0';
    input.style.pointerEvents = 'none';
    document.body.appendChild(input);

    input.addEventListener('change', () => {
      if (!input.value) return;

      try {
        const wireRoot = document.querySelector('[wire\\:id]');
        const cmp =
          wireRoot &&
          window.Livewire?.find(wireRoot.getAttribute('wire:id'));

        cmp?.set('scheduled_date', input.value);
      } catch (_) {}

      document.body.removeChild(input);
    }, { once: true });

    if (input.showPicker) input.showPicker();
    else input.click();
  }

  POOF.openDatePicker = openDatePicker;
  window.openDatePicker = openDatePicker;
})();

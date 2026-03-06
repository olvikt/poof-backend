export default (props = {}) => ({
  /* ================== PROPS ================== */
  slots: props.slots,
  model: props.model,
  scheduledDate: props.scheduledDate,
  today: props.today,
  tomorrow: props.tomorrow,

  /* ================== STATE ================== */
  i: 0,
  noSlotsToday: false,

  /* ================== INIT ================== */
  init() {
    this.i = Number(this.model ?? 0);

    this.$watch('scheduledDate', () => {
      this.recompute(true);
    });

    this.$nextTick(() => {
      this.recompute(true);
    });
  },

  /* ================== TIME HELPERS ================== */
  now() {
    return new Date();
  },

  isToday() {
    return this.scheduledDate === this.today;
  },

  parseTime(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    const d = new Date();
    d.setHours(h, m, 0, 0);
    return d;
  },

  /**
   * 🔥 СЛОТ СЧИТАЕТСЯ ПРОШЕДШИМ,
   * если его НАЧАЛО <= текущего времени
   */
  isPast(slot) {
    if (!this.isToday()) return false;
    return this.parseTime(slot.from) <= this.now();
  },

  isAvailable(slot) {
    return slot.enabled !== false && !this.isPast(slot);
  },

  /* ================== LABEL ================== */
  label() {
    const slot = this.slots[this.i];
    return slot ? `${slot.from}–${slot.to}` : '';
  },

  /* ================== CORE ================== */
  firstAvailableIndex() {
    for (let idx = 0; idx < this.slots.length; idx++) {
      if (this.isAvailable(this.slots[idx])) return idx;
    }
    return null;
  },

  recompute(scroll = false) {
    const first = this.firstAvailableIndex();

    this.noSlotsToday = this.isToday() && first === null;

    // 🔥 если все слоты сегодня прошли — ничего не выбираем
    if (this.noSlotsToday) {
      this.i = null;
      return;
    }

    if (this.i === null || !this.isAvailable(this.slots[this.i])) {
      this.i = first ?? 0;
      this.sync();
    }

    if (scroll) {
      this.$nextTick(() => {
        this.scrollToIndex(this.i);
      });
    }
  },

  /* ================== ACTIONS ================== */
  select(idx) {
    if (!this.isAvailable(this.slots[idx])) return;

    this.i = idx;
    this.sync();
    this.scrollToIndex(idx);
  },

  /**
   * 🔥 КНОПКА "НА ЗАВТРА"
   */
  pickTomorrow() {
    this.setDate(this.tomorrow);
  },

  setDate(date) {
    this.scheduledDate = date;

    // 🔥 общаемся с Livewire безопасно
    Livewire.dispatch('set-scheduled-date', { date });
  },

  scrollToIndex(idx) {
    if (idx === null) return;

    const track = this.$refs.track;
    if (!track) return;

    const el = track.children[idx];
    if (!el) return;

    el.scrollIntoView({
      behavior: 'smooth',
      inline: 'center',
      block: 'nearest',
    });
  },

  /* ================== LIVEWIRE ================== */
  sync() {
    if (this.i === null) return;

    this.model = this.i;

    Livewire.dispatch('set-time-slot', {
      index: this.i,
    });
  },
});

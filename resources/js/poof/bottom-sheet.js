window.bottomSheet = (name) => ({
  open: false,
  name,
  startY: 0,
  currentY: 0,
  dragging: false,

  openSheet(e) {
    if (!e?.detail || e.detail.name !== this.name) return

    this.open = true
    document.body.style.overflow = 'hidden'
    document.documentElement.classList.add('overflow-hidden', 'sheet-open')

    this.$nextTick(() => {
      if (this.$refs.sheet) {
        this.$refs.sheet.style.transform = 'translateY(0)'
      }
    })
  },

  closeSheet(e) {
    if (e?.detail?.name && e.detail.name !== this.name) return
    this.close()
  },

  startDrag(e) {
    window.__activeBottomSheet = this
    this.dragging = true
    this.startY = e.clientY

    window.addEventListener('pointermove', this.onDrag)
    window.addEventListener('pointerup', this.endDrag)
  },

  onDrag: (e) => {
    const sheet = window.__activeBottomSheet?.$refs?.sheet
    if (!sheet || !window.__activeBottomSheet) return

    const delta = e.clientY - window.__activeBottomSheet.startY

    if (delta > 0) {
      sheet.style.transform = `translateY(${delta}px)`
    }
  },

  endDrag: (e) => {
    const sheet = window.__activeBottomSheet?.$refs?.sheet
    if (!sheet || !window.__activeBottomSheet) return

    const delta = e.clientY - window.__activeBottomSheet.startY

    if (delta > 120) {
      window.__activeBottomSheet.close()
    } else {
      sheet.style.transform = 'translateY(0)'
    }

    window.removeEventListener('pointermove', window.__activeBottomSheet.onDrag)
    window.removeEventListener('pointerup', window.__activeBottomSheet.endDrag)
    window.__activeBottomSheet.dragging = false
  },

  close() {
    if (this.$refs.sheet) {
      this.$refs.sheet.style.transform = 'translateY(100%)'
    }

    setTimeout(() => {
      this.open = false
      document.body.style.overflow = ''
      document.documentElement.classList.remove('overflow-hidden', 'sheet-open')
    }, 250)
  },

  init() {},
})

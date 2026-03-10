export default function bottomSheet() {
  window.bottomSheet = (name) => ({
    name,
    open: false,
    startY: 0,
    currentY: 0,
    dragging: false,
    closeTimeout: null,

    init() {
      this.boundOnDrag = this.onDrag.bind(this)
      this.boundEndDrag = this.endDrag.bind(this)
    },

    openSheet(e) {
      if (!e?.detail || e.detail.name !== this.name) return

      clearTimeout(this.closeTimeout)
      this.open = true
      document.body.style.overflow = 'hidden'

      this.$nextTick(() => {
        this.$refs.sheet.style.transition = 'transform 300ms ease-out'
        this.$refs.sheet.style.transform = 'translateY(100%)'

        requestAnimationFrame(() => {
          this.$refs.sheet.style.transform = 'translateY(0)'
        })
      })
    },

    closeSheet(e) {
      if (e?.detail?.name && e.detail.name !== this.name) return
      this.close()
    },

    startDrag(e) {
      this.dragging = true
      this.startY = e.clientY
      this.currentY = e.clientY

      this.$refs.sheet.style.transition = 'none'
      window.addEventListener('pointermove', this.boundOnDrag)
      window.addEventListener('pointerup', this.boundEndDrag)
    },

    onDrag(e) {
      if (!this.dragging) return

      this.currentY = e.clientY
      const delta = this.currentY - this.startY

      if (delta > 0) {
        this.$refs.sheet.style.transform = `translateY(${delta}px)`
      }
    },

    endDrag(e) {
      if (!this.dragging) return

      this.dragging = false
      this.currentY = e.clientY
      const delta = this.currentY - this.startY

      this.$refs.sheet.style.transition = 'transform 300ms ease-out'

      if (delta > 120) {
        this.close()
      } else {
        this.$refs.sheet.style.transform = 'translateY(0)'
      }

      window.removeEventListener('pointermove', this.boundOnDrag)
      window.removeEventListener('pointerup', this.boundEndDrag)
    },

    close() {
      if (!this.open) return

      this.$refs.sheet.style.transition = 'transform 300ms ease-out'
      this.$refs.sheet.style.transform = 'translateY(100%)'
      window.removeEventListener('pointermove', this.boundOnDrag)
      window.removeEventListener('pointerup', this.boundEndDrag)

      clearTimeout(this.closeTimeout)
      this.closeTimeout = setTimeout(() => {
        this.open = false
        this.dragging = false
        document.body.style.overflow = 'auto'
      }, 300)
    },
  })
}

export default function bottomSheet(name) {
  return {
    isOpen: false,

    init() {
      window.addEventListener('sheet:open', (e) => {
        if (e.detail?.name !== name) return

        this.isOpen = true

        // 🔑 Ждём:
        // 1) окончания transition
        // 2) 2 кадра layout-а (RAF)
        // 3) только потом считаем sheet "открытым"
        setTimeout(() => {
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              window.dispatchEvent(
                new CustomEvent('poof:sheet-opened', {
                  detail: { name }
                })
              )
            })
          })
        }, 350) // ← duration transition
      })

      window.addEventListener('sheet:close', (e) => {
        if (!e.detail || e.detail.name === name) {
          this.isOpen = false
        }
      })
    },

    close() {
      this.isOpen = false
    },
  }
}

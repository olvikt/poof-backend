export default function bottomSheet(name) {
    return {
        isOpen: false,

        init() {
            window.addEventListener('sheet:open', (e) => {
                if (e.detail?.name === name) {
                    this.isOpen = true
                }
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
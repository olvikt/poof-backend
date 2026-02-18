<button
    x-data="poofCourierOnlineToggle()"
    @click="toggle"
    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold transition
        {{ $online ? 'bg-emerald-500 text-black' : 'bg-zinc-700 text-gray-300' }}"
>
    <span class="text-xs">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>
</button>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('poofCourierOnlineToggle', () => ({
        async toggle() {

            // OFFLINE -> ONLINE
            if (!this.$wire.online) {

                if (!navigator.geolocation) {
                    alert('Геолокація не підтримується')
                    return
                }

                navigator.geolocation.getCurrentPosition(
                    async (pos) => {

                        const payload = {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                            accuracy: pos.coords.accuracy ?? null
                        }

                        // ✅ сразу пишем координаты в Livewire
                        if (window.Livewire?.dispatch) {
                            window.Livewire.dispatch('courier-location', payload)
                        }

                        // ✅ переключаем статус
                        await this.$wire.goOnline()

                        // ✅ говорим фронту "курьер онлайн" (запустит watchPosition)
                        window.dispatchEvent(new Event('courier:online'))

                        // ✅ гарантируем, что карта домонтируется после морфа
                        window.dispatchEvent(new Event('map:init'))
                    },

                    async (err) => {
                        console.warn('Geolocation error:', err)

                        // DEV fallback
                        const fallback = { lat: 50.4501, lng: 30.5234, accuracy: null }

                        if (window.Livewire?.dispatch) {
                            window.Livewire.dispatch('courier-location', fallback)
                        }

                        await this.$wire.goOnline()

                        window.dispatchEvent(new Event('courier:online'))
                        window.dispatchEvent(new Event('map:init'))
                    },

                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 0 }
                )

                return
            }

            // ONLINE -> OFFLINE
            await this.$wire.goOffline()

            window.dispatchEvent(new Event('courier:offline'))
            window.dispatchEvent(new Event('map:init'))
        }
    }))
})
</script>
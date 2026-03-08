export default function addressAutocomplete() {
  return {
    search: '',
    lat: null,
    lng: null,
    street: null,
    house: null,
    city: null,
    suggestions: [],
    suggestionsMessage: null,
    debounceTimer: null,
    abortController: null,
    requestId: 0,
    isLoadingSuggestions: false,

    init() {
      this.search = this.$wire.entangle('search', true)
      this.lat = this.$wire.entangle('lat')
      this.lng = this.$wire.entangle('lng')
      this.street = this.$wire.entangle('street')
      this.house = this.$wire.entangle('house')
      this.city = this.$wire.entangle('city')
      this.suggestions = this.$wire.entangle('suggestions', true)
      this.suggestionsMessage = this.$wire.entangle('suggestionsMessage', true)

      this.$watch('search', (value) => {
        const query = String(value ?? '').trim()

        if (this.debounceTimer) {
          clearTimeout(this.debounceTimer)
        }

        if (this.abortController) {
          this.abortController.abort()
          this.abortController = null
        }

        if (query.length < 3) {
          this.isLoadingSuggestions = false
          this.$wire.call('setPhotonSuggestions', [], null)
          return
        }

        this.debounceTimer = setTimeout(() => this.fetchSuggestions(query), 300)
      })
    },

    async fetchSuggestions(query) {
      const currentRequestId = ++this.requestId
      this.abortController = new AbortController()
      this.isLoadingSuggestions = true

      const { lat, lng } = this.getBiasCoordinates()
      const params = new URLSearchParams({ q: query })

      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        params.set('lat', lat.toFixed(6))
        params.set('lng', lng.toFixed(6))
      }

      try {
        const response = await fetch('/api/geocode?' + params.toString(), {
          signal: this.abortController.signal,
        })

        if (currentRequestId !== this.requestId) {
          return
        }

        if (!response.ok) {
          this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено')
          return
        }

        const items = await response.json()
        const normalizedItems = Array.isArray(items) ? items : []

        this.$wire.call(
          'setPhotonSuggestions',
          normalizedItems,
          normalizedItems.length ? null : 'Адресу не знайдено',
        )
      } catch (error) {
        if (error?.name !== 'AbortError' && currentRequestId === this.requestId) {
          this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено')
        }
      } finally {
        if (currentRequestId === this.requestId) {
          this.isLoadingSuggestions = false
        }
      }
    },

    getBiasCoordinates() {
      const fallback = { lat: 48.45, lng: 34.98 }
      const mapCenter = window.POOF?.map?.instance?.getCenter?.()

      if (mapCenter && Number.isFinite(mapCenter.lat) && Number.isFinite(mapCenter.lng)) {
        return {
          lat: Number(mapCenter.lat.toFixed(6)),
          lng: Number(mapCenter.lng.toFixed(6)),
        }
      }

      const numericLat = Number(this.lat)
      const numericLng = Number(this.lng)

      if (Number.isFinite(numericLat) && Number.isFinite(numericLng)) {
        return {
          lat: Number(numericLat.toFixed(6)),
          lng: Number(numericLng.toFixed(6)),
        }
      }

      return fallback
    },

    escapeRegExp(value) {
      return String(value ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    },

    highlight(text) {
      const source = String(text ?? '')
      const query = String(this.search ?? '').trim()

      if (!query || source === '') {
        return source
      }

      const regex = new RegExp('(' + this.escapeRegExp(query) + ')', 'ig')

      return source.replace(
        regex,
        '<mark class="bg-yellow-400 text-black px-1 rounded">$1</mark>',
      )
    },
  }
}

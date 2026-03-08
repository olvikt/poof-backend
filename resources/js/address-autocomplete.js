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


      window.addEventListener('address:reverse-geocoded', (event) => {
        const item = event.detail?.item

        if (!item || typeof item !== 'object') {
          return
        }

        this.search = item.label ?? ''
        this.street = item.street ?? ''
        this.city = item.city ?? ''

        this.lat = item.lat
        this.lng = item.lng

        this.$wire.set('search', this.search)
        this.$wire.set('street', this.street)
        this.$wire.set('city', this.city)
        this.$wire.set('lat', this.lat ?? null)
        this.$wire.set('lng', this.lng ?? null)
      })
      this.$watch('search', (value) => {
        if (typeof value === 'object' && value !== null) {
          this.search = value.label ?? value.name ?? value.street ?? ''
          return
        }

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
        const suggestions = Array.isArray(items) ? items : []
        const normalizedItems = suggestions.map((item) => this.normalizeSuggestion(item)).filter(Boolean)

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

    normalizeSuggestion(item) {
      if (!item || typeof item !== 'object') {
        return null
      }

      const lat = Number(item.lat)
      const lng = Number(item.lng)

      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null
      }

      const name = String(item.name ?? '').trim()
      const street = String(item.street ?? '').trim()
      const city = String(item.city ?? '').trim()
      const house = String(item.housenumber ?? item.house ?? '').trim()
      const line1 = String(item.line1 ?? '').trim()
      const line2 = String(item.line2 ?? '').trim()

      const streetLabel = [street, house].filter(Boolean).join(' ').trim()
      const label = streetLabel || street || name || String(item.label ?? '').trim() || line1 || city

      return {
        ...item,
        lat,
        lng,
        street: street || null,
        city: city || null,
        line1: line1 || null,
        line2: line2 || null,
        label,
      }
    },

    selectSuggestion(item) {
      if (!item || typeof item !== 'object') {
        return
      }

      this.search = item.label ?? ''

      this.street = item.street ?? ''
      this.city = item.city ?? ''

      this.lat = item.lat
      this.lng = item.lng

      this.suggestions = []
      this.suggestionsMessage = null

      this.$wire.set('search', this.search)
      this.$wire.set('street', this.street)
      this.$wire.set('city', this.city)
      this.$wire.set('lat', this.lat)
      this.$wire.set('lng', this.lng)
      this.$wire.set('suggestions', [])
      this.$wire.set('suggestionsMessage', null)
      this.$wire.set('activeSuggestionIndex', -1)

      if (this.lat !== null && this.lng !== null) {
        window.dispatchEvent(
          new CustomEvent('map:set-location', {
            detail: { lat: this.lat, lng: this.lng, source: 'autocomplete', zoom: 17 },
          }),
        )
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

function safeString(value) {
  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value.trim()
  if (typeof value === 'number') return String(value)
  if (typeof value === 'object') {
    if (typeof value.label === 'string') return value.label
    if (typeof value.name === 'string') return value.name
    if (typeof value.street === 'string') return value.street
    return ''
  }
  return ''
}

function buildAddressLabel(item) {
  const parts = [
    safeString(item.street),
    safeString(item.house || item.housenumber),
    safeString(item.city),
  ].filter(Boolean)

  return parts.join(' ')
}

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
    abortController: null,
    requestId: 0,
    isLoadingSuggestions: false,
    _debounceTimer: null,

    safe(value) {
      if (typeof value === 'string') return value
      if (typeof value === 'number') return String(value)

      if (value && typeof value === 'object') {
        return safeString(value.label) || safeString(value.name) || safeString(value.street) || safeString(value.value)
      }

      return ''
    },

    debounce(fn, delay = 300) {
      clearTimeout(this._debounceTimer)
      this._debounceTimer = setTimeout(fn, delay)
    },

    normalizeText(value) {
      if (typeof value === 'string') return value.trim()

      if (value && typeof value === 'object') {
        return String(value.label ?? value.name ?? value.value ?? value.street ?? '').trim()
      }

      return String(value ?? '').trim()
    },

    init() {
      this.search = this.$wire.entangle('search', true)
      this.lat = this.$wire.entangle('lat')
      this.lng = this.$wire.entangle('lng')
      this.street = this.$wire.entangle('street')
      this.house = this.$wire.entangle('house')
      this.city = this.$wire.entangle('city')
      this.suggestions = this.$wire.entangle('suggestions', true)
      this.suggestionsMessage = this.$wire.entangle('suggestionsMessage', true)

      const syncAddressInputs = (item) => {
        const streetInput = document.querySelector('[data-address-street]')
        const houseInput = document.querySelector('[data-address-house]')
        const cityInput = document.querySelector('[data-address-city]')
        const regionInput = document.querySelector('[data-address-region]')
        const searchInput = document.querySelector('[data-address-search]')

        if (searchInput) searchInput.value = this.safe(item?.label)
        if (streetInput) streetInput.value = this.safe(item?.street)
        if (houseInput) houseInput.value = this.safe(item?.house)
        if (cityInput) cityInput.value = this.safe(item?.city)
        if (regionInput) regionInput.value = this.safe(item?.region)
      }

      const applyAddressItem = (item) => {
        if (!item || typeof item !== 'object') return

        const street = safeString(item.street)
        const house = safeString(item.housenumber ?? item.house)
        const city = safeString(item.city)

        const label =
          safeString(item.label) ||
          [street, house].filter(Boolean).join(' ') ||
          street ||
          safeString(item.name)

        const lat = Number(item.lat)
        const lng = Number(item.lng)

        this.search = label
        this.street = street
        this.house = house
        this.city = city

        this.lat = Number.isFinite(lat) ? lat : null
        this.lng = Number.isFinite(lng) ? lng : null

        this.suggestions = []
        this.suggestionsMessage = null

        this.$wire.set('search', this.search)
        this.$wire.set('street', this.street)
        this.$wire.set('house', this.house)
        this.$wire.set('city', this.city)
        this.$wire.set('lat', this.lat)
        this.$wire.set('lng', this.lng)
        this.$wire.set('suggestions', [])
        this.$wire.set('suggestionsMessage', null)
      }

      window.addEventListener('address:reverse-geocoded', (e) => {
        const item = e.detail?.item
        if (!item) return

        const streetInput = document.querySelector('[data-address-street]')
        const houseInput = document.querySelector('[data-address-house]')
        const cityInput = document.querySelector('[data-address-city]')
        const regionInput = document.querySelector('[data-address-region]')

        if (streetInput) streetInput.value = this.safe(item.street)
        if (houseInput) houseInput.value = this.safe(item.house)
        if (cityInput) cityInput.value = this.safe(item.city)
        if (regionInput) regionInput.value = this.safe(item.region)

        applyAddressItem(item)
      })

      window.addEventListener('map:set-address', (event) => {
        const item = event.detail?.item ?? event.detail
        if (!item || typeof item !== 'object') {
          return
        }

        syncAddressInputs(item)
        applyAddressItem(item)
      })
      let lastQuery = ''

      this.$watch('search', (value) => {
        if (typeof value === 'object' && value !== null) {
          this.search = safeString(value.label) || safeString(value.name) || safeString(value.street)
          return
        }

        const query = String(value ?? '').trim()

        if (query === lastQuery) {
          return
        }

        lastQuery = query

        this.debounce(() => {
          this.fetchSuggestions(query)
        }, 300)
      })
    },

    async fetchSuggestions(query = this.search) {
      const normalizedQuery =
        typeof query === 'string'
          ? query.trim()
          : String(query ?? '').trim()

      if (!normalizedQuery || normalizedQuery.length < 3) {
        this.isLoadingSuggestions = false
        this.$wire.call('setPhotonSuggestions', [], null)
        return
      }

      const currentRequestId = ++this.requestId

      if (this.abortController) {
        this.abortController.abort()
      }

      this.abortController = new AbortController()
      this.isLoadingSuggestions = true

      console.debug('Geocode query:', normalizedQuery)

      const { lat, lng } = this.getBiasCoordinates()
      const params = new URLSearchParams({ q: normalizedQuery })

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

      if (!Number.isFinite(Number(item.lat)) || !Number.isFinite(Number(item.lng))) {
        return null
      }

      const street = safeString(item.street)
      const house = safeString(item.housenumber || item.house)
      const city = safeString(item.city)
      const name = safeString(item.name)
      const line1 = this.normalizeText(item.line1)
      const line2 = this.normalizeText(item.line2)

      const streetLabel = [street, house].filter(Boolean).join(' ').trim()

      const label =
        streetLabel ||
        street ||
        name ||
        safeString(item.label) ||
        line1 ||
        city

      return {
        ...item,
        lat: Number(item.lat),
        lng: Number(item.lng),
        street: street || null,
        house: house || null,
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

      this.search = safeString(item.label) || safeString(item.street)
      this.street = safeString(item.street)
      this.house = safeString(item.house || item.housenumber)
      this.city = safeString(item.city)

      this.lat = Number.isFinite(Number(item.lat)) ? Number(item.lat) : null
      this.lng = Number.isFinite(Number(item.lng)) ? Number(item.lng) : null

      this.suggestions = []
      this.suggestionsMessage = null

      this.$wire.set('search', this.search)
      this.$wire.set('street', this.street)
      this.$wire.set('house', this.house)
      this.$wire.set('city', this.city)
      this.$wire.set('region', this.normalizeText(item.region) || null)
      this.$wire.set('lat', this.lat ?? null)
      this.$wire.set('lng', this.lng ?? null)
      this.$wire.set('suggestions', [])
      this.$wire.set('suggestionsMessage', null)
      this.$wire.set('activeSuggestionIndex', -1)

      if (Number.isFinite(this.lat) && Number.isFinite(this.lng)) {
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

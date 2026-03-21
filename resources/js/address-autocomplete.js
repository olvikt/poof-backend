const API_BASE = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')
const RECENT_ADDRESSES_KEY = 'poof:recent-addresses:v1'
const RECENT_ADDRESSES_LIMIT = 5

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

function canUseLocalStorage() {
  try {
    return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
  } catch (_) {
    return false
  }
}

function isFiniteCoordinate(value) {
  return Number.isFinite(Number(value))
}

export default function addressAutocomplete() {
  return {
    search: '',
    lat: null,
    lng: null,
    street: null,
    house: null,
    city: null,
    region: null,
    suggestions: [],
    suggestionsMessage: null,
    recentAddresses: [],
    abortController: null,
    requestId: 0,
    isLoadingSuggestions: false,
    prefixCache: {},
    _debounceTimer: null,
    addressLocked: false,
    isApplyingSelection: false,
    isAddressSearchOpen: false,
    manualClearActive: false,
    manualClearCoordinates: null,
    isResolvingUserLocation: false,
    hasResolvedUserLocation: false,
    activeSearchSession: false,
    mapCenterLat: null,
    mapCenterLng: null,
    geoActionState: 'idle',
    geoActionHint: '',
    geoActionHintTimer: null,

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

    buildDisplayLabel(item = {}) {
      const line1 = [safeString(item.street), safeString(item.house || item.housenumber)].filter(Boolean).join(' ').trim()
      const line2 = [safeString(item.city), safeString(item.region)].filter(Boolean).join(', ').trim()

      return safeString(item.label) || [line1, safeString(item.city)].filter(Boolean).join(', ') || line1 || line2 || safeString(item.name)
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
      const region = safeString(item.region)
      const name = safeString(item.name)
      const line1 = this.normalizeText(item.line1) || [street, house].filter(Boolean).join(' ').trim() || null
      const line2 = this.normalizeText(item.line2) || [city, region].filter(Boolean).join(', ').trim() || null
      const label = this.buildDisplayLabel({ ...item, street, house, city, region, line1, line2 }) || name || line1

      return {
        ...item,
        lat: Number(item.lat),
        lng: Number(item.lng),
        street: street || null,
        house: house || null,
        city: city || null,
        region: region || null,
        line1: line1 || null,
        line2: line2 || null,
        label,
        value: label,
      }
    },

    isValidRecentAddress(item) {
      if (!item || typeof item !== 'object') return false
      if (!isFiniteCoordinate(item.lat) || !isFiniteCoordinate(item.lng)) return false

      const label = this.normalizeText(item.label)
      const line1 = this.normalizeText(item.line1)
      const city = this.normalizeText(item.city)
      const street = this.normalizeText(item.street)
      const house = this.normalizeText(item.house)
      const primaryText = label || line1 || [street, house].filter(Boolean).join(' ').trim()

      if (!primaryText) return false

      const normalizedPrimary = primaryText.toLowerCase()
      if (normalizedPrimary === 'unknown location' || normalizedPrimary === 'невідома адреса') {
        return false
      }

      return Boolean(line1 || street || city)
    },

    normalizeRecentItem(item) {
      const normalized = this.normalizeSuggestion(item)
      if (!normalized || !this.isValidRecentAddress(normalized)) return null

      return {
        label: normalized.label,
        line1: normalized.line1,
        line2: normalized.line2,
        street: normalized.street,
        house: normalized.house,
        city: normalized.city,
        region: normalized.region,
        lat: normalized.lat,
        lng: normalized.lng,
      }
    },

    getRecentStorageUserId() {
      const userId = this.normalizeText(document.documentElement?.dataset?.userId)
      return userId || null
    },

    getRecentStorageKey() {
      const userId = this.getRecentStorageUserId()
      return userId ? `${RECENT_ADDRESSES_KEY}:${userId}` : null
    },

    loadRecentAddresses() {
      const storageKey = this.getRecentStorageKey()

      if (!canUseLocalStorage() || !storageKey) {
        this.recentAddresses = []
        return
      }

      try {
        const raw = window.localStorage.getItem(storageKey)
        const items = JSON.parse(raw || '[]')
        const sanitizedItems = Array.isArray(items)
          ? items.map((item) => this.normalizeRecentItem(item)).filter(Boolean).slice(0, RECENT_ADDRESSES_LIMIT)
          : []

        this.recentAddresses = sanitizedItems

        const shouldRewrite = !Array.isArray(items)
          || sanitizedItems.length !== items.length
          || JSON.stringify(items.slice(0, RECENT_ADDRESSES_LIMIT)) !== JSON.stringify(sanitizedItems)

        if (shouldRewrite) {
          window.localStorage.setItem(storageKey, JSON.stringify(sanitizedItems))
        }
      } catch (_) {
        this.recentAddresses = []
      }
    },

    persistRecentAddresses() {
      const storageKey = this.getRecentStorageKey()
      if (!canUseLocalStorage() || !storageKey) return

      const sanitizedItems = this.recentAddresses
        .map((item) => this.normalizeRecentItem(item))
        .filter(Boolean)
        .slice(0, RECENT_ADDRESSES_LIMIT)

      this.recentAddresses = sanitizedItems
      window.localStorage.setItem(storageKey, JSON.stringify(sanitizedItems))
    },

    rememberRecentAddress(item) {
      const normalized = this.normalizeRecentItem(item)
      if (!normalized) return

      const nextItems = [normalized, ...this.recentAddresses.filter((existing) => {
        const existingKey = [existing.street, existing.house, existing.city, existing.lat, existing.lng].map((part) => this.normalizeText(part).toLowerCase()).join('|')
        const nextKey = [normalized.street, normalized.house, normalized.city, normalized.lat, normalized.lng].map((part) => this.normalizeText(part).toLowerCase()).join('|')
        return existingKey !== nextKey
      })]

      this.recentAddresses = nextItems.slice(0, RECENT_ADDRESSES_LIMIT)
      this.persistRecentAddresses()
    },

    clearRecentAddresses() {
      this.recentAddresses = []

      if (canUseLocalStorage()) {
        window.localStorage.removeItem(this.getRecentStorageKey())
      }
    },

    shouldShowRecent() {
      return !this.isLoadingSuggestions && this.normalizeText(this.search) === '' && this.recentAddresses.length > 0
    },

    getMarkerCoordinates() {
      const markerCoords = window.POOF?.marker?.getLatLng?.()

      if (markerCoords && Number.isFinite(Number(markerCoords.lat)) && Number.isFinite(Number(markerCoords.lng))) {
        return {
          lat: Number(Number(markerCoords.lat).toFixed(6)),
          lng: Number(Number(markerCoords.lng).toFixed(6)),
        }
      }

      const markerLat = Number(window.POOF?.map?.lastLat)
      const markerLng = Number(window.POOF?.map?.lastLng)

      if (Number.isFinite(markerLat) && Number.isFinite(markerLng)) {
        return {
          lat: Number(markerLat.toFixed(6)),
          lng: Number(markerLng.toFixed(6)),
        }
      }

      return null
    },

    currentMapPointSelection() {
      const item = this.normalizeSuggestion({
        label: this.search,
        line1: this.search,
        line2: 'Обрати адресу з карти',
        street: this.street,
        house: this.house,
        city: this.city,
        region: this.region,
        lat: this.lat,
        lng: this.lng,
      })

      if (!item || !this.isValidRecentAddress(item)) {
        return null
      }

      const searchText = this.normalizeText(this.search).toLowerCase()
      const currentAddressLine = this.normalizeText(item.line1 || item.label).toLowerCase()

      if (!searchText || !currentAddressLine || searchText !== currentAddressLine) {
        return null
      }

      return item
    },

    shouldShowCurrentMapPointSelection() {
      return this.isAddressSearchOpen
        && !this.isLoadingSuggestions
        && !this.shouldShowRecent()
        && this.suggestions.length === 0
        && this.currentMapPointSelection() !== null
    },

    shouldShowCurrentLocationAction() {
      return !this.isLoadingSuggestions && !this.isResolvingUserLocation && this.normalizeText(this.search) === '' && this.hasBiasCoordinates()
    },

    shouldShowLocationBootstrapLoading() {
      return this.isAddressSearchOpen && this.normalizeText(this.search) === '' && this.isResolvingUserLocation && !this.hasBiasCoordinates()
    },

    isTypingSearch() {
      return this.activeSearchSession && this.normalizeText(this.search).length > 0
    },

    syncUserLocationBootstrap(detail = {}) {
      this.isResolvingUserLocation = Boolean(detail?.isResolving)
      this.hasResolvedUserLocation = Boolean(detail?.hasResolved)

      if (!this.hasBiasCoordinates()) {
        const location = detail?.location
        if (Number.isFinite(Number(location?.lat)) && Number.isFinite(Number(location?.lng))) {
          this.lat = Number(location.lat)
          this.lng = Number(location.lng)
        }
      }
    },

    hasBiasCoordinates() {
      if (this.getMarkerCoordinates()) {
        return true
      }

      const persisted = window.POOF?.getLastKnownUserLocation?.()
      if (Number.isFinite(Number(persisted?.lat)) && Number.isFinite(Number(persisted?.lng))) {
        return true
      }

      return Number.isFinite(Number(this.lat)) && Number.isFinite(Number(this.lng))
    },

    coordinatesDiffer(lat, lng) {
      const nextLat = Number(lat)
      const nextLng = Number(lng)
      const currentLat = Number(this.lat)
      const currentLng = Number(this.lng)

      if (!Number.isFinite(nextLat) || !Number.isFinite(nextLng)) {
        return false
      }

      if (!Number.isFinite(currentLat) || !Number.isFinite(currentLng)) {
        return true
      }

      const epsilon = 0.000001

      return Math.abs(currentLat - nextLat) > epsilon || Math.abs(currentLng - nextLng) > epsilon
    },

    syncMapPointCoordinates(lat, lng) {
      const nextLat = Number(lat)
      const nextLng = Number(lng)

      if (!Number.isFinite(nextLat) || !Number.isFinite(nextLng)) {
        return false
      }

      const hasChanged = this.coordinatesDiffer(nextLat, nextLng)

      this.lat = nextLat
      this.lng = nextLng

      if (hasChanged) {
        this.$wire.set('lat', nextLat)
        this.$wire.set('lng', nextLng)
      }

      return hasChanged
    },

    resetResolvedAddressForMapPoint() {
      this.search = ''
      this.street = null
      this.house = null
      this.city = null
      this.region = null

      this.$wire.set('search', '')
      this.$wire.set('street', null)
      this.$wire.set('house', null)
      this.$wire.set('city', null)
      this.$wire.set('region', null)
    },

    init() {
      this.search = this.$wire.entangle('search', true)
      this.lat = this.$wire.entangle('lat')
      this.lng = this.$wire.entangle('lng')
      this.street = this.$wire.entangle('street')
      this.house = this.$wire.entangle('house')
      this.city = this.$wire.entangle('city')
      this.region = this.$wire.entangle('region')
      this.isAddressSearchOpen = this.$wire.entangle('isAddressSearchOpen')
      this.suggestions = this.$wire.entangle('suggestions', true)
      this.suggestionsMessage = this.$wire.entangle('suggestionsMessage', true)
      this.loadRecentAddresses()

      this.syncUserLocationBootstrap({
        isResolving: Boolean(window.POOF?.map?.isResolvingUserLocation),
        hasResolved: Boolean(window.POOF?.map?.hasResolvedUserLocation),
        location: window.POOF?.getLastKnownUserLocation?.() || null,
      })

      this.geoActionState = 'idle'
      this.geoActionHint = ''

      this.openAddressSearch = () => {
        this.isAddressSearchOpen = true
        this.activeSearchSession = false
        this.loadRecentAddresses()

        this.$nextTick(() => {
          this.$refs.addressSearchInput?.focus?.()
          this.$refs.addressSearchInput?.select?.()
        })
      }

      this.closeAddressSearch = () => {
        this.isAddressSearchOpen = false
        this.activeSearchSession = false
      }

      this.clearSearch = () => {
        const markerCoords = this.getMarkerCoordinates()
        const lat = markerCoords?.lat ?? this.mapCenterLat ?? this.lat
        const lng = markerCoords?.lng ?? this.mapCenterLng ?? this.lng

        this.manualClearActive = true
        this.manualClearCoordinates = Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
          ? { lat: Number(lat), lng: Number(lng) }
          : null

        this.search = ''
        this.suggestions = []
        this.suggestionsMessage = null
        this.requestId += 1

        if (this.abortController) {
          this.abortController.abort()
          this.abortController = null
        }

        this.isLoadingSuggestions = false
        this.$wire.call('clearSearch')
        this.$nextTick(() => {
          this.$refs.addressSearchInput?.focus?.()
        })
      }

      this.selectCurrentLocation = async () => {
        const coords = this.manualClearCoordinates ?? this.getBiasCoordinates()
        const lat = Number(coords?.lat)
        const lng = Number(coords?.lng)

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return
        }

        this.manualClearActive = false
        this.manualClearCoordinates = null
        this.isLoadingSuggestions = true
        this.suggestions = []
        this.suggestionsMessage = null
        this.$wire.set('suggestions', [])
        this.$wire.set('suggestionsMessage', null)

        try {
          const response = await fetch(`${API_BASE || ''}/api/geocode?lat=${lat}&lng=${lng}`)

          if (!response.ok) {
            return
          }

          const data = await response.json()
          const item = Array.isArray(data) ? this.normalizeSuggestion(data[0]) : null

          if (!item) {
            return
          }

          this.selectSuggestion(item)
        } catch (error) {
          if (error?.name !== 'AbortError') {
            console.error('[POOF autocomplete] current-location reverse geocode failed', error)
          }
        } finally {
          this.isLoadingSuggestions = false
        }
      }

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

      const applyAddressItem = (item, options = {}) => {
        if (!item || typeof item !== 'object') return

        const normalizedItem = this.normalizeRecentItem(item)
        if (!normalizedItem) return

        this.manualClearActive = false
        this.manualClearCoordinates = null
        this.isApplyingSelection = true
        this.search = normalizedItem.label
        this.street = normalizedItem.street
        this.house = normalizedItem.house
        this.city = normalizedItem.city
        this.lat = normalizedItem.lat
        this.lng = normalizedItem.lng

        this.suggestions = []
        this.suggestionsMessage = null

        this.$wire.set('search', this.search)
        this.$wire.set('street', this.street)
        this.$wire.set('house', this.house)
        this.$wire.set('city', this.city)
        this.$wire.set('region', normalizedItem.region)
        this.$wire.set('lat', this.lat)
        this.$wire.set('lng', this.lng)
        this.$wire.set('suggestions', [])
        this.$wire.set('suggestionsMessage', null)

        if (options.remember !== false) {
          this.rememberRecentAddress(normalizedItem)
        }

        this.$nextTick(() => {
          this.isApplyingSelection = false
        })
      }

      window.addEventListener('address:reverse-geocoded', (e) => {
        if (this.addressLocked || this.manualClearActive || this.isTypingSearch()) return

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
        if (this.addressLocked || this.manualClearActive || this.isTypingSearch()) return

        const item = event.detail?.item ?? event.detail
        if (!item || typeof item !== 'object') {
          return
        }

        syncAddressInputs(item)
        applyAddressItem(item)
      })

      window.addEventListener('poof:map-center-changed', (e) => {
        const { lat, lng } = e.detail || {}

        this.mapCenterLat = Number.isFinite(Number(lat)) ? Number(lat) : null
        this.mapCenterLng = Number.isFinite(Number(lng)) ? Number(lng) : null

        if (this.manualClearActive || this.isTypingSearch()) {
          return
        }

        const mapPointChanged = this.syncMapPointCoordinates(lat, lng)

        if (!mapPointChanged) {
          return
        }

        if (this.addressLocked) {
          this.addressLocked = false
          window.dispatchEvent(new CustomEvent('address:unlock', {
            detail: { reason: 'map-move' },
          }))
        }

        this.resetResolvedAddressForMapPoint()
      })

      window.addEventListener('poof:user-location-bootstrap', (e) => {
        this.syncUserLocationBootstrap(e.detail || {})
      })

      window.addEventListener('poof:geo-action-state', (e) => {
        const detail = e.detail || {}
        this.geoActionState = typeof detail.status === 'string' ? detail.status : 'idle'
        this.geoActionHint = typeof detail.message === 'string' ? detail.message : ''

        if (this.geoActionHintTimer) {
          clearTimeout(this.geoActionHintTimer)
          this.geoActionHintTimer = null
        }

        if (this.geoActionState !== 'loading' && this.geoActionHint) {
          this.geoActionHintTimer = window.setTimeout(() => {
            this.geoActionState = 'idle'
            this.geoActionHint = ''
            this.geoActionHintTimer = null
          }, 2200)
        }
      })

      window.addEventListener('address:lock', () => {
        this.addressLocked = true
      })

      window.addEventListener('address:unlock', () => {
        this.addressLocked = false
      })
      let lastQuery = ''

      this.$watch('isAddressSearchOpen', (value) => {
        if (value) {
          this.loadRecentAddresses()

          this.$nextTick(() => {
            this.$refs.addressSearchInput?.focus?.()
            this.$refs.addressSearchInput?.select?.()
          })
        }
      })

      this.$watch('search', (value) => {
        if (typeof value === 'object' && value !== null) {
          this.search = safeString(value.label) || safeString(value.name) || safeString(value.street)
          return
        }

        const query = String(value ?? '').trim()

        this.activeSearchSession = this.isAddressSearchOpen && query.length > 0

        if (query === lastQuery) {
          return
        }

        if (!this.isApplyingSelection && this.addressLocked) {
          this.addressLocked = false
          window.dispatchEvent(new CustomEvent('address:unlock', {
            detail: { reason: 'manual-input' },
          }))
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
        this.suggestions = []
        this.suggestionsMessage = null
        this.$wire.set('suggestions', [])
        this.$wire.set('suggestionsMessage', null)
        return
      }

      const bias = this.getBiasCoordinates()
      const lat = Number(bias?.lat)
      const lng = Number(bias?.lng)
      const hasBias = Number.isFinite(lat) && Number.isFinite(lng)
      const locationKey = hasBias
        ? `${Number(lat).toFixed(2)}:${Number(lng).toFixed(2)}`
        : 'no-bias'
      const cacheKey = `${normalizedQuery.toLowerCase()}@${locationKey}`

      if (this.prefixCache[cacheKey]) {
        this.suggestions = this.prefixCache[cacheKey]
        this.suggestionsMessage = null
        this.$wire.set('suggestions', this.suggestions)
        this.$wire.set('suggestionsMessage', null)
        return
      }

      const currentRequestId = ++this.requestId

      if (this.abortController) {
        this.abortController.abort()
      }

      this.abortController = new AbortController()
      this.isLoadingSuggestions = true

      if (import.meta.env.DEV) {
        console.log('Geocode query:', normalizedQuery)
      }


      try {
        const params = new URLSearchParams({ q: normalizedQuery })

        if (hasBias) {
          params.set('lat', String(lat))
          params.set('lng', String(lng))
        }

        const response = await fetch(`${API_BASE || ''}/api/geocode?${params.toString()}`, {
          signal: this.abortController.signal,
        })

        if (currentRequestId !== this.requestId) {
          return
        }

        if (!response.ok) {
          this.suggestions = []
          this.suggestionsMessage = null
          this.$wire.set('suggestions', [])
          this.$wire.set('suggestionsMessage', null)
          return
        }

        const data = await response.json()
        console.log('[POOF autocomplete]', data)

        const seen = new Set()
        const suggestions = Array.isArray(data)
          ? data
            .map((item) => this.normalizeSuggestion(item))
            .filter(Boolean)
            .filter((item) => {
              const key = [item.street, item.house, item.city]
                .map((part) => this.normalizeText(part).toLowerCase())
                .join('-')

              if (seen.has(key)) return false
              seen.add(key)
              return true
            })
            .slice(0, 5)
          : []

        this.prefixCache[cacheKey] = suggestions

        this.suggestions = suggestions
        this.suggestionsMessage = null
        this.$wire.set('suggestions', suggestions)
        this.$wire.set('suggestionsMessage', null)
      } catch (error) {
        if (error?.name !== 'AbortError' && currentRequestId === this.requestId) {
          this.suggestions = []
          this.suggestionsMessage = null
          this.$wire.set('suggestions', [])
          this.$wire.set('suggestionsMessage', null)
        }
      } finally {
        if (currentRequestId === this.requestId) {
          this.isLoadingSuggestions = false
        }
      }
    },

    selectSuggestion(item) {
      if (!item || typeof item !== 'object') {
        return
      }

      const normalizedItem = this.normalizeRecentItem(item)
      if (!normalizedItem) return

      this.search = normalizedItem.label || safeString(item.street)
      this.manualClearActive = false
      this.manualClearCoordinates = null
      this.isApplyingSelection = true
      this.street = normalizedItem.street
      this.house = normalizedItem.house
      this.city = normalizedItem.city
      this.lat = normalizedItem.lat
      this.lng = normalizedItem.lng

      this.suggestions = []
      this.suggestionsMessage = null

      this.$wire.set('search', this.search)
      this.$wire.set('street', this.street)
      this.$wire.set('house', this.house)
      this.$wire.set('city', this.city)
      this.$wire.set('region', normalizedItem.region)
      this.$wire.set('lat', this.lat ?? null)
      this.$wire.set('lng', this.lng ?? null)
      this.$wire.set('suggestions', [])
      this.$wire.set('suggestionsMessage', null)
      this.$wire.set('activeSuggestionIndex', -1)
      this.isAddressSearchOpen = false

      this.rememberRecentAddress(normalizedItem)

      this.addressLocked = true
      window.dispatchEvent(new CustomEvent('address:lock', {
        detail: { reason: 'autocomplete' },
      }))

      this.$nextTick(() => {
        this.isApplyingSelection = false
      })

      if (Number.isFinite(this.lat) && Number.isFinite(this.lng)) {
        window.dispatchEvent(
          new CustomEvent('map:set-location', {
            detail: { lat: this.lat, lng: this.lng, source: 'autocomplete', zoom: 17 },
          }),
        )
      }
    },

    getBiasCoordinates() {
      const markerCoords = this.getMarkerCoordinates()

      if (markerCoords) {
        return markerCoords
      }

      const persisted = window.POOF?.getLastKnownUserLocation?.()

      if (Number.isFinite(Number(persisted?.lat)) && Number.isFinite(Number(persisted?.lng))) {
        return {
          lat: Number(Number(persisted.lat).toFixed(6)),
          lng: Number(Number(persisted.lng).toFixed(6)),
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

      return null
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

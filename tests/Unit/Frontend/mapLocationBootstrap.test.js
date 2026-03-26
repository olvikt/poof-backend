import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildCurrentLocationFallbackPlan,
  buildCourierRuntimeEvidenceView,
  normalizeRuntimeObservabilityReason,
  shouldIgnoreStaleAddressPickerSyncPoint,
  shouldApplyPersistedLocationOnBootstrap,
} from '../../../resources/js/poof/map.js'

test('address-picker bootstrap does not silently apply persisted location', () => {
  const result = shouldApplyPersistedLocationOnBootstrap({
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'user' },
    bootstrapApplied: false,
    hasActiveOrderBootstrap: false,
    isAddressPickerFlow: true,
  })

  assert.equal(result, false)
})

test('non-address bootstrap can still reuse persisted location when no stronger source exists', () => {
  const result = shouldApplyPersistedLocationOnBootstrap({
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'user' },
    bootstrapApplied: false,
    hasActiveOrderBootstrap: false,
    isAddressPickerFlow: false,
  })

  assert.equal(result, true)
})

test('current-location fallback stays explicit and does not close the address book', () => {
  const plan = buildCurrentLocationFallbackPlan({
    allowPersistedFallback: true,
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'geolocation' },
    closeAddressBook: true,
    source: 'event',
    zoom: 18,
  })

  assert.deepEqual(plan, {
    lat: 48.4671,
    lng: 35.0382,
    source: 'event',
    persistSource: 'geolocation',
    zoom: 18,
    closeAddressBook: false,
    message: 'Точну геолокацію не вдалося отримати, тому показали останню збережену точку. За потреби посуньте мапу вручну.',
    warning: 'Точну локацію не вдалося отримати — використали останню збережену точку.',
    requestedCloseAddressBook: true,
    usedPersistedFallback: true,
  })
})

test('current-location fallback is skipped unless explicitly allowed', () => {
  const plan = buildCurrentLocationFallbackPlan({
    allowPersistedFallback: false,
    persistedLocation: { lat: 48.4671, lng: 35.0382, source: 'geolocation' },
    closeAddressBook: true,
  })

  assert.equal(plan, null)
})

test('fresh geolocation point stays authoritative during address-picker open flow', () => {
  const now = Date.now()

  const result = shouldIgnoreStaleAddressPickerSyncPoint({
    isAddressPickerFlow: true,
    lat: 48.4240053,
    lng: 35.0588747,
    source: 'sync',
    authoritativePoint: {
      lat: 48.4671,
      lng: 35.0382,
      reason: 'geolocation',
      updatedAt: now,
    },
    preferredPoint: {
      lat: 48.4671,
      lng: 35.0382,
      reason: 'resolved-address',
      updatedAt: now,
    },
    now,
  })

  assert.equal(result, true)
})

test('matching sync point is still allowed when it agrees with fresh current-location state', () => {
  const now = Date.now()

  const result = shouldIgnoreStaleAddressPickerSyncPoint({
    isAddressPickerFlow: true,
    lat: 48.4671,
    lng: 35.0382,
    source: 'sync',
    authoritativePoint: {
      lat: 48.4671,
      lng: 35.0382,
      reason: 'user',
      updatedAt: now,
    },
    preferredPoint: {
      lat: 48.4240053,
      lng: 35.0588747,
      reason: 'stale-visible',
      updatedAt: now,
    },
    now,
  })

  assert.equal(result, false)
})

test('runtime observability reason normalizer keeps reason-coded payload compact', () => {
  assert.equal(normalizeRuntimeObservabilityReason(' lease_heartbeat_lost '), 'lease_heartbeat_lost')
  assert.equal(normalizeRuntimeObservabilityReason(''), 'unspecified')
  assert.equal(normalizeRuntimeObservabilityReason(null, 'fallback_reason'), 'fallback_reason')
})

test('runtime evidence view returns compact operator payload with top counters and recent signals', () => {
  const evidence = buildCourierRuntimeEvidenceView({
    counters: {
      'cross_tab_runtime_sync_repair_applied:courier_runtime_sync': 3,
      'cross_tab_runtime_sync_emit:courier_online_toggled': 9,
    },
    signals: [
      { event: 'cross_tab_runtime_sync_emit', reason: 'courier_online_toggled', level: 'info', ts: 50, meta: { source: 'toggle' } },
      { event: 'cross_tab_runtime_sync_repair_applied', reason: 'courier_runtime_sync', level: 'warn', ts: 70 },
    ],
    authSessionLost: false,
    crossTab: {
      tabId: 'tab-1',
      lastSignature: 'true|assigned|courier_online_toggled',
      lastEmittedAt: 120,
      channelEnabled: true,
    },
    geoLeadership: {
      desired: true,
      active: true,
      mode: 'lease',
    },
  })

  assert.equal(evidence.topCounters[0].key, 'cross_tab_runtime_sync_emit:courier_online_toggled')
  assert.equal(evidence.topCounters[0].count, 9)
  assert.equal(evidence.recentSignals[0].event, 'cross_tab_runtime_sync_repair_applied')
  assert.equal(evidence.recentSignals[0].level, 'warn')
  assert.equal(evidence.crossTab.tabId, 'tab-1')
  assert.equal(evidence.geoLeadership.mode, 'lease')
  assert.equal(evidence.serverRuntimeError, null)
})

import test from 'node:test'
import assert from 'node:assert/strict'

import { isHandledGeolocationDegradedSignal } from '../../../tests/e2e/helpers/runtime-guards.js'

test('runtime guard treats denied geolocation marker as handled degraded state', () => {
  assert.equal(
    isHandledGeolocationDegradedSignal('[POOF:courier-tracker][warn] geolocation_denied_or_error { code: 1 }'),
    true,
  )
})

test('runtime guard still treats unrelated console errors as fatal evidence', () => {
  assert.equal(isHandledGeolocationDegradedSignal('Unhandled promise rejection: boom'), false)
})

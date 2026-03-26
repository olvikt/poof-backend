import test from 'node:test'
import assert from 'node:assert/strict'

import {
  isSessionLossStatus,
  normalizeAuthSyncReason,
} from '../../../resources/js/poof/auth-session-sync.js'

test('auth sync reason normalizer keeps compact fallback-safe reason', () => {
  assert.equal(normalizeAuthSyncReason(' logout_submitted '), 'logout_submitted')
  assert.equal(normalizeAuthSyncReason(''), 'auth_state_changed')
  assert.equal(normalizeAuthSyncReason(null, 'fallback_reason'), 'fallback_reason')
})

test('auth sync status classifier treats 401/419 as session loss only', () => {
  assert.equal(isSessionLossStatus(401), true)
  assert.equal(isSessionLossStatus(419), true)
  assert.equal(isSessionLossStatus(403), false)
  assert.equal(isSessionLossStatus(500), false)
})

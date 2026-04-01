import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'

import {
  emitUiRuntimeMarker,
  evaluateLivewireRuntimeBoot,
  evaluateStandaloneAlpineBoot,
  POOF_BOOT_FLAGS,
  POOF_RUNTIME_MARKER_EVENT,
  registerSharedAlpineComponents,
  shouldBootStandaloneAlpine,
  shouldStartLivewireRuntime,
  shouldStartStandaloneAlpine,
} from '../../../resources/js/poof/runtime-bootstrap.js'

test('shared alpine registrations are idempotent and run exactly once per instance', () => {
  const calls = []

  const alpine = {
    data(name, definition) {
      calls.push({ name, isFunction: typeof definition === 'function' })
    },
  }

  const registeredFirst = registerSharedAlpineComponents(alpine, {
    poofTimeCarousel: () => ({}),
    addressAutocomplete: () => ({}),
  })
  const registeredSecond = registerSharedAlpineComponents(alpine, {
    poofTimeCarousel: () => ({}),
    addressAutocomplete: () => ({}),
  })

  assert.equal(registeredFirst, true)
  assert.equal(registeredSecond, false)
  assert.equal(alpine.__poofComponentsRegistered, true)
  assert.deepEqual(calls, [
    { name: 'poofTimeCarousel', isFunction: true },
    { name: 'addressAutocomplete', isFunction: true },
  ])
})

test('livewire startup guard blocks duplicate boot and only allows a single valid boot path', () => {
  const globals = {}
  const livewire = { start() {} }
  const alpine = {}

  assert.equal(shouldStartLivewireRuntime({ livewire, alpine, globals }), true)

  globals[POOF_BOOT_FLAGS.livewireStarted] = true

  assert.equal(shouldStartLivewireRuntime({ livewire, alpine, globals }), false)
  assert.deepEqual(evaluateLivewireRuntimeBoot({ livewire, alpine, globals }), {
    allowed: false,
    reason: 'duplicate_guarded',
  })
})

test('standalone alpine startup guard does not run when livewire config exists and prevents duplicate init', () => {
  assert.equal(shouldBootStandaloneAlpine({ hasLivewireConfig: true }), false)
  assert.equal(shouldBootStandaloneAlpine({ hasLivewireConfig: false }), true)

  const globals = {}
  const alpine = { start() {} }

  assert.equal(shouldStartStandaloneAlpine({ alpine, globals }), true)
  globals[POOF_BOOT_FLAGS.alpineStarted] = true
  assert.equal(shouldStartStandaloneAlpine({ alpine, globals }), false)
  assert.deepEqual(evaluateStandaloneAlpineBoot({ alpine, globals }), {
    allowed: false,
    reason: 'duplicate_guarded',
  })
})

test('ui runtime marker emits structured event payload without requiring diagnostics console noise', () => {
  let captured = null
  const globals = {
    dispatchEvent(event) {
      if (event.type === POOF_RUNTIME_MARKER_EVENT) {
        captured = event.detail
      }
    },
  }

  const detail = emitUiRuntimeMarker('ui_runtime_bootstrap_skipped', {
    source: 'livewire',
    reason: 'duplicate_guarded',
  }, { globals, diagnostics: false })

  assert.equal(detail.event, 'ui_runtime_bootstrap_skipped')
  assert.equal(detail.level, 'info')
  assert.equal(captured?.context?.reason, 'duplicate_guarded')
})


test('app entry isolates standalone alpine boot from livewire-bundled alpine path', () => {
  const appScript = fs.readFileSync('resources/js/app.js', 'utf8')

  assert.equal(appScript.includes('LivewireAlpine'), false)
  assert.equal(appScript.includes("if (!livewire && hasLivewireConfig) {"), true)
  assert.equal(appScript.includes("standaloneAlpinePromise = import('alpinejs')"), true)
  assert.equal(appScript.includes("if (shouldBootStandaloneAlpine({ hasLivewireConfig })) {"), true)
})

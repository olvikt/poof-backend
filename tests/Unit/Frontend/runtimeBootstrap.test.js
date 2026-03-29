import test from 'node:test'
import assert from 'node:assert/strict'

import {
  POOF_BOOT_FLAGS,
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
})

test('standalone alpine startup guard does not run when livewire config exists and prevents duplicate init', () => {
  assert.equal(shouldBootStandaloneAlpine({ hasLivewireConfig: true }), false)
  assert.equal(shouldBootStandaloneAlpine({ hasLivewireConfig: false }), true)

  const globals = {}
  const alpine = { start() {} }

  assert.equal(shouldStartStandaloneAlpine({ alpine, globals }), true)
  globals[POOF_BOOT_FLAGS.alpineStarted] = true
  assert.equal(shouldStartStandaloneAlpine({ alpine, globals }), false)
})

export const POOF_BOOT_FLAGS = Object.freeze({
  livewireStarted: '__poofLivewireStarted',
  alpineStarted: '__poofAlpineStarted',
})

export function registerSharedAlpineComponents(instance, components = {}) {
  if (!instance || instance.__poofComponentsRegistered) return false

  Object.entries(components).forEach(([name, component]) => {
    if (typeof name !== 'string' || name.trim() === '' || typeof component !== 'function') return
    instance.data(name, component)
  })

  instance.__poofComponentsRegistered = true

  return true
}

export function shouldBootStandaloneAlpine({ hasLivewireConfig = false } = {}) {
  return !hasLivewireConfig
}

export function shouldStartLivewireRuntime({ livewire = null, alpine = null, globals = globalThis } = {}) {
  if (!livewire || !alpine || typeof livewire.start !== 'function') return false

  return !Boolean(globals?.[POOF_BOOT_FLAGS.livewireStarted])
}

export function shouldStartStandaloneAlpine({ alpine = null, globals = globalThis } = {}) {
  if (!alpine || typeof alpine.start !== 'function') return false

  return !Boolean(globals?.[POOF_BOOT_FLAGS.alpineStarted])
}

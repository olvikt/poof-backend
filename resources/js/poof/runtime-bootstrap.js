export const POOF_BOOT_FLAGS = Object.freeze({
  livewireStarted: '__poofLivewireStarted',
  alpineStarted: '__poofAlpineStarted',
})

export const POOF_RUNTIME_MARKER_EVENT = 'poof:ui-runtime-marker'

export function emitUiRuntimeMarker(event, context = {}, { globals = globalThis, diagnostics = false } = {}) {
  const detail = {
    event: typeof event === 'string' && event.trim() !== '' ? event.trim() : 'ui_runtime_event',
    level: context?.level === 'error' || context?.level === 'warn' ? context.level : 'info',
    ts: Date.now(),
    context: context && typeof context === 'object' ? { ...context } : {},
  }

  try {
    globals?.dispatchEvent?.(new CustomEvent(POOF_RUNTIME_MARKER_EVENT, { detail }))
  } catch (_) {}

  if (!diagnostics) return detail

  const label = `[POOF:ui-runtime][${detail.level}] ${detail.event}`
  if (detail.level === 'error') {
    console.error(label, detail)
  } else if (detail.level === 'warn') {
    console.warn(label, detail)
  } else {
    console.info(label, detail)
  }

  return detail
}

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
  return evaluateLivewireRuntimeBoot({ livewire, alpine, globals }).allowed
}

export function shouldStartStandaloneAlpine({ alpine = null, globals = globalThis } = {}) {
  return evaluateStandaloneAlpineBoot({ alpine, globals }).allowed
}

export function evaluateLivewireRuntimeBoot({ livewire = null, alpine = null, globals = globalThis } = {}) {
  if (!livewire || !alpine || typeof livewire.start !== 'function') {
    return { allowed: false, reason: 'missing_runtime_dependencies' }
  }

  if (Boolean(globals?.[POOF_BOOT_FLAGS.livewireStarted])) {
    return { allowed: false, reason: 'duplicate_guarded' }
  }

  return { allowed: true, reason: 'ready' }
}

export function evaluateStandaloneAlpineBoot({ alpine = null, globals = globalThis } = {}) {
  if (!alpine || typeof alpine.start !== 'function') {
    return { allowed: false, reason: 'missing_alpine_runtime' }
  }

  if (Boolean(globals?.[POOF_BOOT_FLAGS.alpineStarted])) {
    return { allowed: false, reason: 'duplicate_guarded' }
  }

  return { allowed: true, reason: 'ready' }
}

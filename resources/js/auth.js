import './bootstrap'
import Alpine from 'alpinejs'
import {
  beginRuntimeBoot,
  endRuntimeBoot,
  evaluateStandaloneAlpineBoot,
  lockRuntimeMode,
  POOF_BOOT_FLAGS,
  POOF_RUNTIME_MODE,
} from './poof/runtime-bootstrap'

const runtimeBoot = beginRuntimeBoot({ globals: window })
if (runtimeBoot.allowed) {
  try {
    const runtimeMode = lockRuntimeMode(POOF_RUNTIME_MODE.standalone, { globals: window })
    if (runtimeMode.allowed) {
      window.Alpine = window.Alpine ?? Alpine

      const standaloneBoot = evaluateStandaloneAlpineBoot({ alpine: window.Alpine, globals: window })
      if (standaloneBoot.allowed) {
        window[POOF_BOOT_FLAGS.alpineStarting] = true
        try {
          window.Alpine.start()
          window[POOF_BOOT_FLAGS.alpineStarted] = true
        } finally {
          window[POOF_BOOT_FLAGS.alpineStarting] = false
        }
      }
    }
  } finally {
    endRuntimeBoot({ globals: window })
  }
}

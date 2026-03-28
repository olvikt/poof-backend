# release-20260328-client-runtime-hotfix-21

- Fix shared Livewire/Alpine runtime bootstrap on client pages
- Restore client order and profile interactions by binding Alpine components to the active Livewire runtime
- Remove duplicate order-create Vite script loading to avoid double-boot side effects

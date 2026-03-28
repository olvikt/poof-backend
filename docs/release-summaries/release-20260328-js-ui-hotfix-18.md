# release-20260328-js-ui-hotfix-18

- Restore consistent Livewire/Alpine runtime bootstrap across the app
- Recover JS-driven UI initialization for carousels, bottom sheets, and Alpine x-data components
- Harden carousel Livewire dispatch calls to avoid runtime failures during startup

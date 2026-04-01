<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class RuntimeBootstrapArchitectureSmokeTest extends TestCase
{
    public function test_runtime_bootstrap_entry_points_are_explicitly_pinned(): void
    {
        $appScript = file_get_contents(resource_path('js/app.js'));
        $orderCreateScript = file_get_contents(resource_path('js/poof/order-create.js'));
        $mapScript = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($appScript);
        $this->assertNotFalse($orderCreateScript);
        $this->assertNotFalse($mapScript);

        // Livewire / Alpine startup entrypoints.
        $this->assertStringContainsString('void bootReactiveRuntime()', $appScript);
        $this->assertStringContainsString("document.addEventListener('livewire:init', () => {", $appScript);
        $this->assertStringContainsString('const livewireBoot = evaluateLivewireRuntimeBoot({ livewire, alpine, globals: window })', $appScript);
        $this->assertStringContainsString('const standaloneBoot = evaluateStandaloneAlpineBoot({ alpine: window.Alpine, globals: window })', $appScript);

        // Shared registration boundary.
        $this->assertStringContainsString('registerSharedAlpineComponents(instance, sharedAlpineComponents)', $appScript);
        $this->assertStringContainsString('const sharedAlpineComponents = {', $appScript);
        $this->assertStringContainsString('poofTimeCarousel,', $appScript);
        $this->assertStringContainsString('addressAutocomplete,', $appScript);

        // Runtime bootstrap hooks (client/courier + navigation).
        $this->assertStringContainsString("document.addEventListener('livewire:navigated', boot)", $orderCreateScript);
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', boot)", $orderCreateScript);
        $this->assertStringContainsString("document.addEventListener('livewire:navigated', () => {", $mapScript);
        $this->assertStringContainsString("window.addEventListener('courier:runtime-sync', (e) => {", $mapScript);
        $this->assertStringContainsString("window.addEventListener('courier-online-toggled', (e) => {", $mapScript);
    }

    public function test_bootstrap_guards_prevent_duplicate_boot_and_hidden_dual_instance_drift(): void
    {
        $appScript = file_get_contents(resource_path('js/app.js'));
        $runtimeBootstrapScript = file_get_contents(resource_path('js/poof/runtime-bootstrap.js'));
        $mapScript = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($appScript);
        $this->assertNotFalse($runtimeBootstrapScript);
        $this->assertNotFalse($mapScript);

        // Single valid boot path + duplicate guards.
        $this->assertStringContainsString("livewireStarted: '__poofLivewireStarted'", $runtimeBootstrapScript);
        $this->assertStringContainsString("alpineStarted: '__poofAlpineStarted'", $runtimeBootstrapScript);
        $this->assertStringContainsString("livewireStarting: '__poofLivewireStarting'", $runtimeBootstrapScript);
        $this->assertStringContainsString("alpineStarting: '__poofAlpineStarting'", $runtimeBootstrapScript);
        $this->assertStringContainsString('if (!instance || instance.__poofComponentsRegistered) return false', $runtimeBootstrapScript);
        $this->assertStringContainsString('instance.__poofComponentsRegistered = true', $runtimeBootstrapScript);
        $this->assertStringContainsString('window[POOF_BOOT_FLAGS.livewireStarted] = true', $appScript);
        $this->assertStringContainsString('window[POOF_BOOT_FLAGS.alpineStarted] = true', $appScript);
        $this->assertStringContainsString('if (!livewire || !alpine) {', $appScript);
        $this->assertStringContainsString("livewireRuntimePromise = import('../../vendor/livewire/livewire/dist/livewire.esm')", $appScript);
        $this->assertStringContainsString("import Alpine from 'alpinejs'", $appScript);
        $this->assertStringContainsString('if (!hasLivewireConfig && shouldBootStandaloneAlpine({ hasLivewireConfig })) {', $appScript);
        $this->assertStringContainsString('window.Alpine = window.Alpine ?? Alpine', $appScript);
        $this->assertStringNotContainsString('LivewireAlpine', $appScript);
        $this->assertStringNotContainsString('window.Alpine ?? LivewireAlpine ?? Alpine', $appScript);

        // Single map runtime instance path (no hidden dual map instances per same element).
        $this->assertStringContainsString('if (state.instance && state.el === el) {', $mapScript);
        $this->assertStringContainsString('destroyIfDomChanged(el)', $mapScript);
        $this->assertStringContainsString('teardownMapInstance()', $mapScript);
    }

    public function test_navigation_reconnect_recovery_hooks_are_pinned_for_runtime_self_heal(): void
    {
        $mapScript = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($mapScript);

        $this->assertStringContainsString('function resetMapStateForNavigation()', $mapScript);
        $this->assertStringContainsString('state.pendingPoint = null', $mapScript);
        $this->assertStringContainsString("window.addEventListener('poof:auth-session-lost', (event) => {", $mapScript);
        $this->assertStringContainsString('stopCourierGeoWatchLeadership()', $mapScript);
        $this->assertStringContainsString('resetMapStateForNavigation()', $mapScript);
        $this->assertStringContainsString('mountAny()', $mapScript);
        $this->assertStringContainsString('applyBootstrapFromDom()', $mapScript);

        // Livewire morph re-init hooks (wire:navigate/tab-like remounts).
        $this->assertStringContainsString("window.Livewire.hook('morph.updated', () => {", $mapScript);
        $this->assertStringContainsString("window.Livewire.hook('morph.added', () => {", $mapScript);
    }
}

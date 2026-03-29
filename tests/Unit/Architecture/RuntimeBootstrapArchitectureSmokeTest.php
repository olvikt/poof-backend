<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class RuntimeBootstrapArchitectureSmokeTest extends TestCase
{
    public function test_app_runtime_boot_guards_prevent_duplicate_livewire_and_alpine_startup(): void
    {
        $appScript = file_get_contents(resource_path('js/app.js'));

        $this->assertNotFalse($appScript);
        $this->assertStringContainsString('function registerAlpineComponents(instance)', $appScript);
        $this->assertStringContainsString('instance.__poofComponentsRegistered', $appScript);
        $this->assertStringContainsString("instance.data('poofTimeCarousel', poofTimeCarousel)", $appScript);
        $this->assertStringContainsString("instance.data('addressAutocomplete', addressAutocomplete)", $appScript);
        $this->assertStringContainsString('if (!window.__poofLivewireStarted && typeof livewire.start === \'function\')', $appScript);
        $this->assertStringContainsString('window.__poofLivewireStarted = true', $appScript);
        $this->assertStringContainsString('if (!window.__poofAlpineStarted)', $appScript);
        $this->assertStringContainsString('window.__poofAlpineStarted = true', $appScript);
        $this->assertStringContainsString("document.addEventListener('livewire:init', bootReactiveRuntime)", $appScript);
    }

    public function test_map_runtime_bootstrap_contract_keeps_single_instance_and_navigation_recovery_hooks(): void
    {
        $mapScript = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($mapScript);
        $this->assertStringContainsString('function teardownMapInstance()', $mapScript);
        $this->assertStringContainsString('if (state.instance && state.el === el)', $mapScript);
        $this->assertStringContainsString("document.addEventListener('livewire:navigated', () => {", $mapScript);
        $this->assertStringContainsString('resetMapStateForNavigation()', $mapScript);
        $this->assertStringContainsString('mountAny()', $mapScript);
    }
}

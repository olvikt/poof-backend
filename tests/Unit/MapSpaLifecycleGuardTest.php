<?php

namespace Tests\Unit;

use Tests\TestCase;

class MapSpaLifecycleGuardTest extends TestCase
{
    public function test_map_script_resets_and_reinitializes_map_on_livewire_navigation(): void
    {
        $script = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($script);
        $this->assertStringContainsString("function resetMapStateForNavigation()", $script);
        $this->assertStringContainsString("function teardownMapInstance()", $script);
        $this->assertStringContainsString("function applyBootstrapFromDom()", $script);

        $this->assertStringContainsString("document.addEventListener('livewire:navigated'", $script);
        $this->assertStringContainsString("resetMapStateForNavigation()", $script);
        $this->assertStringContainsString("teardownMapInstance()", $script);
        $this->assertStringContainsString("mountAny()", $script);
    }
}

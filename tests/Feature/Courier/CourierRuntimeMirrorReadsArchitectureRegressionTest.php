<?php

namespace Tests\Feature\Courier;

use Tests\TestCase;

class CourierRuntimeMirrorReadsArchitectureRegressionTest extends TestCase
{
    /**
     * Canonical courier runtime surfaces must not read raw users mirrors as truth.
     * users.is_online / users.is_busy / users.session_state are compatibility-only projections.
     */
    public function test_canonical_runtime_surfaces_do_not_read_raw_users_runtime_mirrors(): void
    {
        $files = [
            'app/Support/CourierRuntimeStateResolver.php',
            'app/Support/CourierRuntimeSnapshot.php',
            'app/Http/Controllers/Api/CourierRuntimeController.php',
            'app/Livewire/Courier/AvailableOrders.php',
            'app/Livewire/Courier/MyOrders.php',
            'app/Livewire/Courier/OnlineToggle.php',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents(base_path($file));
            $this->assertIsString($contents);

            $this->assertStringNotContainsString('->is_online', $contents, $file.' should not read users.is_online as runtime truth.');
            $this->assertStringNotContainsString('->is_busy', $contents, $file.' should not read users.is_busy as runtime truth.');
            $this->assertStringNotContainsString('->session_state', $contents, $file.' should not read users.session_state as runtime truth.');
        }
    }
}

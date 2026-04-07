<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class GeolocationHotfixArchitectureTest extends TestCase
{
    public function test_courier_tracker_runtime_bootstrap_and_incident_markers_are_wired(): void
    {
        $trackerView = file_get_contents(resource_path('views/livewire/courier/location-tracker.blade.php'));

        $this->assertNotFalse($trackerView);
        $this->assertStringContainsString("window.addEventListener('courier:runtime-sync'", $trackerView);
        $this->assertStringContainsString("emitMarker('tracker_boot_attempted'", $trackerView);
        $this->assertStringContainsString("emitMarker('geolocation_watch_started'", $trackerView);
        $this->assertStringContainsString("emitMarker('first_geolocation_payload_received'", $trackerView);
        $this->assertStringContainsString("emitMarker('geolocation_denied_or_error'", $trackerView);
        $this->assertStringContainsString("emitMarker('courier_location_dispatched_to_livewire'", $trackerView);
        $this->assertStringContainsString("window.dispatchEvent(new CustomEvent('map:ui-error'", $trackerView);
    }

    public function test_client_current_location_action_is_explicitly_wired_to_window_event(): void
    {
        $orderCreateView = file_get_contents(resource_path('views/livewire/client/order-create.blade.php'));
        $mapScript = file_get_contents(resource_path('js/poof/map.js'));

        $this->assertNotFalse($orderCreateView);
        $this->assertNotFalse($mapScript);

        $this->assertStringContainsString("window.dispatchEvent(new CustomEvent('use-current-location'))", $orderCreateView);
        $this->assertStringContainsString("window.addEventListener('use-current-location'", $mapScript);
        $this->assertStringContainsString("emitCourierGeoMarker('client_use_current_location_triggered'", $mapScript);
        $this->assertStringContainsString("emitRuntimeSignal('map_default_city_used'", $mapScript);
    }
}

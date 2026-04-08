<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class CourierAdminNormalizationTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_courier_resource_exposes_user_profile_fields_in_edit_surface(): void
    {
        $courierResource = $this->normalizedFile('app/Filament/Resources/CourierResource.php');

        $this->assertStringContainsString("Section::make('Courier profile')", $courierResource);
        $this->assertStringContainsString("->relationship('user')", $courierResource);
        $this->assertStringContainsString("TextInput::make('name')", $courierResource);
        $this->assertStringContainsString("TextInput::make('email')", $courierResource);
        $this->assertStringContainsString("TextInput::make('phone')", $courierResource);
        $this->assertStringContainsString("TextInput::make('residence_address')", $courierResource);

        $this->assertStringContainsString("->maxLength(500)", $courierResource);
    }

    public function test_courier_resource_exposes_navigation_to_verification_resource_without_inline_review_controls(): void
    {
        $courierResource = $this->normalizedFile('app/Filament/Resources/CourierResource.php');
        $editPage = $this->normalizedFile('app/Filament/Resources/CourierResource/Pages/EditCourier.php');

        $this->assertStringContainsString("Action::make('verification_requests')", $courierResource);
        $this->assertStringContainsString('CourierVerificationRequestResource::getUrl', $courierResource);
        $this->assertStringContainsString("Action::make('verification_requests')", $editPage);
        $this->assertStringContainsString('CourierVerificationRequestResource::getUrl', $editPage);

        $this->assertStringNotContainsString("Action::make('approve')", $courierResource);
        $this->assertStringNotContainsString("Action::make('reject')", $courierResource);
        $this->assertStringNotContainsString("Toggle::make('is_verified')", $courierResource);
    }

    public function test_courier_admin_resources_are_grouped_under_courier_navigation_group(): void
    {
        $courierResource = $this->normalizedFile('app/Filament/Resources/CourierResource.php');
        $verificationResource = $this->normalizedFile('app/Filament/Resources/CourierVerificationRequestResource.php');

        $this->assertStringContainsString("protected static ?string $navigationGroup = 'Courier';", $courierResource);
        $this->assertStringContainsString("protected static ?string $navigationGroup = 'Courier';", $verificationResource);
    }
}

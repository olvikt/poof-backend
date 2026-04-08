<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class NamingResponsibilityBoundaryArchitectureTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    private function normalizedFile(string $relativePath): string
    {
        $contents = file_get_contents($this->repoRoot.'/'.$relativePath);
        $this->assertNotFalse($contents);

        return preg_replace('/\s+/', ' ', (string) $contents) ?? '';
    }

    public function test_client_form_writes_delegate_to_action_suffix_classes(): void
    {
        $addressConcern = $this->normalizedFile('app/Livewire/Client/AddressForm/Concerns/HandlesAddressPersistence.php');
        $profileForm = $this->normalizedFile('app/Livewire/Client/ProfileForm.php');
        $avatarForm = $this->normalizedFile('app/Livewire/Client/AvatarForm.php');
        $courierAvatarForm = $this->normalizedFile('app/Livewire/Courier/AvatarForm.php');

        $this->assertStringContainsString('app(PersistClientAddressAction::class)->execute(', $addressConcern);
        $this->assertStringContainsString('app(PersistClientProfileAction::class)->execute(', $profileForm);
        $this->assertStringContainsString('app(PersistClientAvatarAction::class)->execute(', $avatarForm);
        $this->assertStringContainsString('app(PersistCourierAvatarAction::class)->execute(', $courierAvatarForm);
    }

    public function test_actions_directory_uses_explicit_action_suffix_for_application_writes(): void
    {
        $addressAction = $this->normalizedFile('app/Actions/Address/PersistClientAddressAction.php');
        $profileAction = $this->normalizedFile('app/Actions/Profile/PersistClientProfileAction.php');
        $avatarAction = $this->normalizedFile('app/Actions/Avatar/PersistClientAvatarAction.php');

        $this->assertStringContainsString('class PersistClientAddressAction', $addressAction);
        $this->assertStringContainsString('class PersistClientProfileAction', $profileAction);
        $this->assertStringContainsString('class PersistClientAvatarAction', $avatarAction);
    }
}

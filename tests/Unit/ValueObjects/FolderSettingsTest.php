<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FolderSettings;
use App\Exceptions\InvalidFolderSettingException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FolderSettingsTest extends TestCase
{
    #[Test]
    public function default(): void
    {
        $settings = $this->make();

        $this->assertEquals(17, count(FolderSettings::default(), COUNT_RECURSIVE));
        $this->assertEquals(-1, $settings->maxCollaboratorsLimit);
        $this->assertFalse($settings->acceptInviteConstraints->inviterMustBeAnActiveCollaborator());
        $this->assertFalse($settings->acceptInviteConstraints->inviterMustHaveRequiredPermission());

        $this->assertTrue($settings->notificationsAreEnabled);
        $this->assertFalse($settings->notificationsAreDisabled);

        $this->assertTrue($settings->newCollaboratorNotificationIsEnabled);
        $this->assertFalse($settings->newCollaboratorNotificationIsDisabled);
        $this->assertTrue($settings->newCollaboratorNotificationMode->notifyOnAllActivity());
        $this->assertFalse($settings->newCollaboratorNotificationMode->notifyWhenCollaboratorWasInvitedByMe());

        $this->assertTrue($settings->folderUpdatedNotificationIsEnabled);
        $this->assertFalse($settings->folderUpdatedNotificationIsDisabled);

        $this->assertTrue($settings->newBookmarksNotificationIsEnabled);
        $this->assertFalse($settings->newBookmarksNotificationIsDisabled);

        $this->assertTrue($settings->bookmarksRemovedNotificationIsEnabled);
        $this->assertFalse($settings->bookmarksRemovedNotificationIsDisabled);

        $this->assertTrue($settings->collaboratorExitNotificationIsEnabled);
        $this->assertFalse($settings->collaboratorExitNotificationIsDisabled);
        $this->assertTrue($settings->collaboratorExitNotificationMode->notifyOnAllActivity());
        $this->assertFalse($settings->collaboratorExitNotificationMode->notifyWhenCollaboratorHasWritePermission());
    }

    private function make(array $settings = []): FolderSettings
    {
        return new FolderSettings($settings);
    }

    #[Test]
    public function testAcceptInviteConstraints(): void
    {
        $validName = 'InviterMustBeAnActiveCollaborator';

        $this->assertFalse($this->isValid(['acceptInviteConstraints' => $validName]));
        $this->assertFalse($this->isValid(['acceptInviteConstraints' => ['baz']]));
        $this->assertFalse($this->isValid(['acceptInviteConstraints' => null]));
        $this->assertFalse($this->isValid(['acceptInviteConstraints' => ['baz', $validName]]));
        $this->assertFalse($this->isValid(['acceptInviteConstraints' => [$validName, null]]));
        $this->assertFalse($this->isValid(['acceptInviteConstraints' => [$validName, $validName]])); //must be unique

        $this->assertTrue($this->isValid(['acceptInviteConstraints' => [$validName]]));
        $this->assertTrue($this->isValid(['acceptInviteConstraints' => []]));
    }

    #[Test]
    public function willThrowExceptionWhenKeyIsInvalid(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz'], $errorCode = 1778));
        $this->assertFalse($this->isValid(['notifications' => ['newCollaborator' => ['foo' => 'bar']]], $errorCode));
        $this->assertFalse($this->isValid(['notifications' => ['collaboratorExit' => ['foo' => 'bar']]], $errorCode));
    }

    #[Test]
    public function willThrowExceptionWhenValuesAreInvalid(): void
    {
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 1]]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'on']]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'off']]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 0]]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => null]]));
        $this->assertFalse($this->isValid(['version' => '3']));
        $this->assertFalse($this->isValid(['notifications' => ['newCollaborator' => ['mode' => 'bar']]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaboratorExit' => ['mode' => 'foo']]]));
    }

    #[Test]
    public function settings_can_be_empty(): void
    {
        $this->expectNotToPerformAssertions();

        $this->make([]);
    }

    #[Test]
    public function whenKeyIsSet(): void
    {
        $settings = $this->make(['notifications' => ['enabled' => false]]);
        $this->assertFalse($settings->notificationsAreEnabled);
        $this->assertTrue($settings->notificationsAreDisabled);
        $this->assertEquals(3, count($settings->toArray(), COUNT_RECURSIVE));

        $settings = $this->make(['notifications' => ['newCollaborator' => ['enabled' => false]]]);
        $this->assertFalse($settings->newCollaboratorNotificationIsEnabled);
        $this->assertTrue($settings->newCollaboratorNotificationIsDisabled);
    }

    private function isValid(array $data, int $errorCode = 1777): bool
    {
        try {
            $this->make($data);
            return true;
        } catch (InvalidFolderSettingException $e) {
            $this->assertEquals($errorCode, $e->getCode());
            return false;
        }
    }

    #[Test]
    public function toArray(): void
    {
        $settings = $this->make(['notifications' => ['enabled' => false]])->toArray();
        $this->assertEquals($settings, ['version' => '1.0.0', 'notifications' => ['enabled' => false]]);
        $this->assertEquals(3, count($settings, COUNT_RECURSIVE));
    }

    #[Test]
    public function toJson(): void
    {
        $settings = new FolderSettings(['notifications' => ['enabled' => false]]);
        $this->assertEquals($settings->toJson(), json_encode(['version' => '1.0.0', 'notifications' => ['enabled' => false]]));
    }
}

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
        $settings = new FolderSettings([]);

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

        new FolderSettings([]);
    }

    #[Test]
    public function whenKeyIsSet(): void
    {
        $settings = new FolderSettings(['notifications' => ['enabled' => false]]);
        $this->assertFalse($settings->notificationsAreEnabled);
        $this->assertTrue($settings->notificationsAreDisabled);

        $settings = new FolderSettings(['notifications' => ['newCollaborator' => ['enabled' => false]]]);
        $this->assertFalse($settings->newCollaboratorNotificationIsEnabled);
        $this->assertTrue($settings->newCollaboratorNotificationIsDisabled);
    }

    private function isValid(array $data, int $errorCode = 1777): bool
    {
        try {
            new FolderSettings($data);
            return true;
        } catch (InvalidFolderSettingException $e) {
            $this->assertEquals($errorCode, $e->getCode());
            return false;
        }
    }

    #[Test]
    public function toArray(): void
    {
        $settings = new FolderSettings(['notifications' => ['enabled' => false]]);
        $this->assertEquals($settings->toArray(), ['version' => '1.0.0', 'notifications' => ['enabled' => false]]);
    }

    #[Test]
    public function toJson(): void
    {
        $settings = new FolderSettings(['notifications' => ['enabled' => false]]);
        $this->assertEquals($settings->toJson(), json_encode(['version' => '1.0.0', 'notifications' => ['enabled' => false]]));
    }
}

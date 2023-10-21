<?php

namespace Tests\Unit;

use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\DataTransferObjects\FolderSettings;
use App\Enums\FolderSettingKey as Key;
use App\Exceptions\InvalidFolderSettingException;
use Tests\TestCase;

class FolderSettingsTest extends TestCase
{
    public function testWillThrowExceptionWhenSettingsIsInvalid(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz']));
    }

    public function test_attributes_must_have_valid_types(): void
    {
        $this->assertFalse($this->isValid([Key::ENABLE_NOTIFICATIONS->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION->value => 'baz']));
        $this->assertFalse($this->isValid([Key::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFy_ON_UPDATE->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_NEW_BOOKMARK->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_BOOKMARK_DELETED->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NEW_COLLABORATOR_NOTIFICATION->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT->value => 'baz']));
        $this->assertFalse($this->isValid([Key::NOTIFY_ON_COLLABORATOR_EXIT_ONLY_WHEN_HAS_WRITE_PERMISSION->value => 'baz']));
    }

    public function test_settings_can_be_empty(): void
    {
        $this->expectNotToPerformAssertions();

        new FolderSettings([]);
    }

    public function test_will_throw_exception_when_new_collaborator_notification_settings_is_inValid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->disableNewCollaboratorNotification()
            ->enableOnlyCollaboratorsInvitedByMeNotification()
            ->build();
    }

    public function test_will_throw_exception_when_new_collaborator_exit_notification_is_invalid(): void
    {
        $this->expectExceptionCode(1778);

        (new FolderSettingsBuilder())
            ->disableCollaboratorExitNotification()
            ->enableOnlyCollaboratorWithWritePermissionNotification()
            ->build();
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
}

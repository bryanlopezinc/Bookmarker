<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FolderSettings;
use App\Exceptions\InvalidFolderSettingException;
use Illuminate\Testing\AssertableJsonString;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FolderSettingsTest extends TestCase
{
    #[Test]
    public function default(): void
    {
        $settings = $this->make();

        $this->assertEquals(18, count(FolderSettings::default(), COUNT_RECURSIVE));
        $this->assertEquals(-1, $settings->maxCollaboratorsLimit);
        $this->assertEquals(-1, $settings->maxBookmarksLimit);
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
    public function willThrowExceptionWhenValuesAreInvalid(): void
    {
        $this->assertFalse($this->isValid(['foo' => 'baz']));

        $this->assertFalse($this->isValid(['version' => '3']));
        $this->assertFalse($this->isValid(['version' => 1]));

        $this->assertFalse($this->isValid(['max_collaborators_limit' => -2]));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => 1001]));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => '500'], 'The max_collaborators_limit is not an integer value.'));

        $this->assertFalse($this->isValid(['max_bookmarks_limit' => -2]));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => 201]));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => '150'], 'The max_bookmarks_limit is not an integer value.'));

        $this->assertFalse($this->isValid(['accept_invite_constraints' => $validName = 'InviterMustBeAnActiveCollaborator']));
        $this->assertFalse($this->isValid(['accept_invite_constraints' => ['baz']]));
        $this->assertFalse($this->isValid(['accept_invite_constraints' => ['baz', $validName]]));
        $this->assertFalse($this->isValid(['accept_invite_constraints' => [$validName, null]]));
        $this->assertFalse($this->isValid(['accept_invite_constraints' => [$validName, $validName]])); //must be unique

        $this->assertFalse($this->isValid(['notifications' => ['foo' => true]]));
        $this->assertFalse($this->isValid(['notifications' => true]));

        $this->assertFalse($this->isValid(['notifications' => ['enabled' => '1']]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => '0']]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 0]]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 1]]));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'true']]));

        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['foo' => true]]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => true]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['enabled' => '1']]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['enabled' => '0']]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['enabled' => 0]]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['enabled' => 1]]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['mode' => 2]]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_collaborator' => ['mode' => 'foo']]]));

        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['foo' => true]]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => true]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['enabled' => '1']]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['enabled' => '0']]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['enabled' => 0]]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['enabled' => 1]]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['mode' => 2]]]));
        $this->assertFalse($this->isValid(['notifications' => ['collaborator_exit' => ['mode' => 'foo']]]));

        $this->assertFalse($this->isValid(['notifications' => ['folder_updated' => ['foo' => true]]]));
        $this->assertFalse($this->isValid(['notifications' => ['folder_updated' => ['enabled' => '1']]]));
        $this->assertFalse($this->isValid(['notifications' => ['folder_updated' => ['enabled' => '0']]]));
        $this->assertFalse($this->isValid(['notifications' => ['folder_updated' => ['enabled' => 0]]]));
        $this->assertFalse($this->isValid(['notifications' => ['folder_updated' => ['enabled' => 1]]]));

        $this->assertFalse($this->isValid(['notifications' => ['new_bookmarks' => ['foo' => true]]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_bookmarks' => ['enabled' => '1']]]));
        $this->assertFalse($this->isValid(['notifications' => ['new_bookmarks' => ['enabled' => '0']]]));

        $this->assertFalse($this->isValid(['notifications' => ['bookmarks_removed' => ['foo' => true]]]));
        $this->assertFalse($this->isValid(['notifications' => ['bookmarks_removed' => ['enabled' => '1']]]));
        $this->assertFalse($this->isValid(['notifications' => ['bookmarks_removed' => ['enabled' => '0']]]));
    }

    #[Test]
    public function empty(): void
    {
        $settings = $this->make([])->toArray();
        $this->assertCount(0, $settings);
    }

    #[Test]
    public function whenKeyIsSet(): void
    {
        $settings = $this->make(['notifications' => ['enabled' => false]]);
        $this->assertFalse($settings->notificationsAreEnabled);
        $this->assertTrue($settings->notificationsAreDisabled);
        $this->assertEquals(3, count($settings->toArray(), COUNT_RECURSIVE));

        $settings = $this->make(['notifications' => ['new_collaborator' => ['enabled' => false]]]);
        $this->assertFalse($settings->newCollaboratorNotificationIsEnabled);
        $this->assertTrue($settings->newCollaboratorNotificationIsDisabled);
    }

    private function isValid(array $data, string $message = null): bool
    {
        try {
            $this->make($data);
            return true;
        } catch (InvalidFolderSettingException $e) {
            if ($message) {
                $this->assertContains($message, $e->errorMessages);
            }

            return false;
        }
    }

    #[Test]
    public function toArray(): void
    {
        $settings = $this->make(['notifications' => ['enabled' => false]])->toArray();
        $this->assertEquals($settings, ['version' => '1.0.0', 'notifications' => ['enabled' => false]]);
        $this->assertEquals(3, count($settings, COUNT_RECURSIVE));

        $settings = $this->make()->toArray();
        $this->assertEquals($settings, []);
    }

    #[Test]
    public function toJson(): void
    {
        $settings = new FolderSettings(['notifications' => ['enabled' => false]]);
        $json = new AssertableJsonString($settings->toJson());

        $json->assertExact(['version' => '1.0.0', 'notifications' => ['enabled' => false]]);
    }
}

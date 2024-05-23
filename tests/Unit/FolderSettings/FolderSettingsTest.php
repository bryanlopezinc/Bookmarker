<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Enums\FolderActivitiesVisibility;
use App\Exceptions\InvalidFolderSettingException;
use App\FolderSettings\FolderSettings;
use App\FolderSettings\Settings\MaxBookmarksLimit;
use App\FolderSettings\Settings\MaxCollaboratorsLimit;
use App\FolderSettings\Settings\Notifications\Notifications;
use Illuminate\Testing\AssertableJsonString;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FolderSettingsTest extends TestCase
{
    #[Test]
    public function fromKeys(): void
    {
        $settings = $this->make([
            'notifications' => ['enabled' => false],
            'max_collaborators_limit' => 120,
            'max_bookmarks_limit' => 140
        ]);

        $this->assertEquals($settings, FolderSettings::fromKeys([
            new MaxBookmarksLimit(140),
            new Notifications(false),
            new MaxCollaboratorsLimit(120)
        ]));
    }

    #[Test]
    public function activitiesVisibility(): void
    {
        $settings = $this->make();
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'public']]);
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::PUBLIC->value]]);
        $this->assertEquals(FolderActivitiesVisibility::PUBLIC, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'private']]);
        $this->assertEquals(FolderActivitiesVisibility::PRIVATE, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::PRIVATE->value]]);
        $this->assertEquals(FolderActivitiesVisibility::PRIVATE, $settings->activitiesVisibility()->value());

        $settings = $this->make(['activities' => ['visibility' => 'collaborators']]);
        $this->assertEquals(FolderActivitiesVisibility::COLLABORATORS, $settings->activitiesVisibility()->value());
        $settings = $this->make(['activities' => ['visibility' => FolderActivitiesVisibility::COLLABORATORS->value]]);
        $this->assertEquals(FolderActivitiesVisibility::COLLABORATORS, $settings->activitiesVisibility()->value());

        $this->assertFalse($this->isValid(['activities' => ['visibility' => 99]], 'The selected activities.visibility is invalid.'));
        $this->assertFalse($this->isValid(['activities' => ['visibility' => 'foo']], 'The selected activities.visibility is invalid.'));
    }

    #[Test]
    public function version(): void
    {
        $settings = $this->make();
        $this->assertEquals('1.0.0', $settings->version()->value());

        $this->assertFalse($this->isValid(['version' => 1], 'The version must be a string.'));
        $this->assertFalse($this->isValid(['version' => '1'], 'The selected version is invalid.'));
    }

    #[Test]
    public function maxCollaboratorsLimit(): void
    {
        $settings = $this->make();
        $this->assertEquals(-1, $settings->maxCollaboratorsLimit()->value());

        $settings = $this->make(['max_collaborators_limit' => '50']);
        $this->assertEquals(50, $settings->maxCollaboratorsLimit()->value());

        $this->assertFalse($this->isValid(['max_collaborators_limit' => 1001], 'The max_collaborators_limit must not be greater than 1000.'));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => 1.5], 'The max_collaborators_limit must be an integer.'));
        $this->assertFalse($this->isValid(['max_collaborators_limit' => -2], 'The max_collaborators_limit must be at least -1.'));
    }

    #[Test]
    public function maxBookmarksLimit(): void
    {
        $settings = $this->make();
        $this->assertEquals(-1, $settings->maxBookmarksLimit()->value());

        $settings = $this->make(['max_bookmarks_limit' => '50']);
        $this->assertEquals(50, $settings->maxBookmarksLimit()->value());

        $this->assertFalse($this->isValid(['max_bookmarks_limit' => 1001], 'The max_bookmarks_limit must not be greater than 200.'));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => 1.5], 'The max_bookmarks_limit must be an integer.'));
        $this->assertFalse($this->isValid(['max_bookmarks_limit' => -2], 'The max_bookmarks_limit must be at least -1.'));
    }

    #[Test]
    public function acceptInviteConstraints(): void
    {
        $settings = $this->make();
        $this->assertTrue($settings->acceptInviteConstraints()->value()->isEmpty());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustHaveRequiredPermission']]);
        $this->assertTrue($settings->acceptInviteConstraints()->value()->inviterMustHaveRequiredPermission());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator']]);
        $this->assertTrue($settings->acceptInviteConstraints()->value()->inviterMustBeAnActiveCollaborator());

        $settings = $this->make(['accept_invite_constraints' => ['InviterMustHaveRequiredPermission', 'InviterMustBeAnActiveCollaborator']]);
        $this->assertEquals($settings->toArray(), [
            'version' => '1.0.0',
            'accept_invite_constraints' => [
                'InviterMustHaveRequiredPermission',
                'InviterMustBeAnActiveCollaborator'
            ]
        ]);

        $this->assertFalse($this->isValid(
            ['accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator', 'InviterMustBeAnActiveCollaborator']],
            'The accept_invite_constraints field has a duplicate value.'
        ));

        $this->assertFalse($this->isValid(
            ['accept_invite_constraints' => 'InviterMustBeAnActiveCollaborator'],
            'The accept_invite_constraints must be an array.'
        ));
    }

    #[Test]
    public function notificationsIsEnabled(): void
    {
        $settings = $this->make();
        $this->assertFalse($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => false]]);
        $this->assertTrue($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => '0']]);
        $this->assertTrue($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => true]]);
        $this->assertFalse($settings->notifications()->isDisabled());

        $settings = $this->make(['notifications' => ['enabled' => '1']]);
        $this->assertFalse($settings->notifications()->isDisabled());

        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'true']], $error = 'The notifications.enabled field must be true or false.'));
        $this->assertFalse($this->isValid(['notifications' => ['enabled' => 'false']], $error));
    }

    private function make(array $settings = []): FolderSettings
    {
        return new FolderSettings($settings);
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
    public function empty(): void
    {
        $settings = $this->make([])->toArray();
        $this->assertCount(0, $settings);
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
        $json = new AssertableJsonString($settings->toJson());

        $json->assertExact(['version' => '1.0.0', 'notifications' => ['enabled' => false]]);
    }
}

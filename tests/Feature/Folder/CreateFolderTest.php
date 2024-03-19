<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder as Builder;
use App\ValueObjects\FolderSettings as FS;
use App\Models\Folder;
use App\ValueObjects\FolderSettings;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Closure;

class CreateFolderTest extends TestCase
{
    use WithFaker;
    use Concerns\TestsFolderSettings;

    private static array $folderSettingsThatHasNotBeenInteractedWith;

    protected function setUp(): void
    {
        parent::setUp();

        self::$folderSettingsThatHasNotBeenInteractedWith = Arr::except(
            Arr::dot(FolderSettings::default()),
            ['version']
        );
    }

    protected function createFolderResponse(array $data = []): TestResponse
    {
        return $this->postJson(route('createFolder'), $data);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'createFolder');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->createFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->createFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->createFolderResponse(['name' => str_repeat('f', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 50 characters.']);

        $this->createFolderResponse(['description' => str_repeat('f', 151)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'The description must not be greater than 150 characters.']);
    }

    public function testCreateFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name'        => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence,
        ])->assertCreated();

        /** @var Folder */
        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($name, $folder->name->value);
        $this->assertEquals($description, $folder->description);
        $this->assertTrue($folder->visibility->isPublic());
        $this->assertEmpty($folder->settings->toArray());
    }

    public function testCreateFolderWithoutDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word])->assertCreated();

        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertNull($folder->description);
    }

    public function testCreatePublicFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'public'])->assertCreated();

        $folder = Folder::where('user_id', $user->id)->first();

        $this->assertTrue($folder->visibility->isPublic());
    }

    #[Test]
    public function createCollaboratorOnlyFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'collaborators'])->assertCreated();

        $folder = Folder::where('user_id', $user->id)->first();

        $this->assertTrue($folder->visibility->isVisibleToCollaboratorsOnly());
    }

    public function testCreatePrivateFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'private'])
            ->assertCreated();

        $folder = Folder::where('user_id', $user->id)->first();

        $this->assertTrue($folder->visibility->isPrivate());
    }

    #[Test]
    public function createPasswordProtectedFolder(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'password_protected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_password' => 'The folder password field is required.']);

        $this->createFolderResponse([
            'name' => $this->faker->word,
            'visibility' => 'password_protected',
            'folder_password' => 'password',
        ])->assertCreated();

        $folder = Folder::where('user_id', $user->id)->sole();

        $this->assertTrue($folder->visibility->isPasswordProtected());
        $this->assertTrue(Hash::check('password', $folder->password));
    }

    public function testCreateFolderWithSettings(): void
    {
        $this->assertCreateWithSettings(['max_collaborators_limit' => '500'], function (FS $s) {
            $this->assertEquals(Builder::new()->setMaxCollaboratorsLimit(500)->build(), $s);
        });

        $this->assertCreateWithSettings(['max_bookmarks_limit' => '110'], function (FS $s) {
            $this->assertEquals(Builder::new()->setMaxBookmarksLimit(110)->build(), $s);
        });

        $this->assertCreateWithSettings(['accept_invite_constraints' => ['InviterMustHaveRequiredPermission']], function (FS $s) {
            $this->assertEquals(Builder::new()->enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNotifications()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.enabled' => 0], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNotifications()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.enabled' => '0'], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNotifications()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.enabled' => true], function (FS $s) {
            return $s->notificationsAreEnabled;
        });

        $this->assertCreateWithSettings(['notifications.enabled' => 1], function (FS $s) {
            return $s->notificationsAreEnabled;
        });

        $this->assertCreateWithSettings(['notifications.enabled' => '1'], function (FS $s) {
            return $s->notificationsAreEnabled;
        });

        $this->assertCreateWithSettings(['notifications.new_collaborator.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNewCollaboratorNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.new_collaborator.enabled' => true], function (FS $s) {
            return $s->newCollaboratorNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.folder_updated.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableFolderUpdatedNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.folder_updated.enabled' => true], function (FS $s) {
            return $s->folderUpdatedNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.new_bookmarks.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNewBookmarksNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.new_bookmarks.enabled' => true], function (FS $s) {
            return $s->newBookmarksNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.bookmarks_removed.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableBookmarksRemovedNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.bookmarks_removed.enabled' => true], function (FS $s) {
            return $s->bookmarksRemovedNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.collaborator_exit.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableCollaboratorExitNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.collaborator_exit.enabled' => true], function (FS $s) {
            return $s->collaboratorExitNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.new_collaborator.mode' => '*'], function (FS $s) {
            return ! $s->newCollaboratorNotificationMode->notifyWhenCollaboratorWasInvitedByMe();
        });

        $this->assertCreateWithSettings(['notifications.new_collaborator.mode' => 'invitedByMe'], function (FS $s) {
            $this->assertEquals(Builder::new()->enableOnlyCollaboratorsInvitedByMeNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.collaborator_exit.mode' => '*'], function (FS $s) {
            return ! $s->collaboratorExitNotificationMode->notifyWhenCollaboratorHasWritePermission();
        });

        $this->assertCreateWithSettings(['notifications.collaborator_exit.mode' => 'hasWritePermission'], function (FS $s) {
            $this->assertEquals(Builder::new()->enableOnlyCollaboratorWithWritePermissionNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(
            [
                'notifications.new_collaborator.enabled'  => false,
                'notifications.folder_updated.enabled'    => false,
                'notifications.collaborator_exit.enabled' => false
            ],
            function (FS $s) {
                return $s->newCollaboratorNotificationIsDisabled &&
                    $s->folderUpdatedNotificationIsDisabled &&
                    $s->collaboratorExitNotificationIsDisabled;
            }
        );

        $this->assertEquals(self::$folderSettingsThatHasNotBeenInteractedWith, []);
    }

    private function assertCreateWithSettings(array $settings, Closure $assertion): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'settings' => Arr::undot($settings)])->assertCreated();

        $folderSettings = Folder::query()->where('user_id', $user->id)->sole()->settings;

        if ( ! is_null($condition = $assertion($folderSettings))) {
            $this->assertTrue($condition);
        }

        foreach (array_keys($settings) as $key) {
            Arr::forget(self::$folderSettingsThatHasNotBeenInteractedWith, $key);
        }
    }

    public function testWillReturnUnprocessableWhenFolderSettingsIsInValid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->assertWillReturnUnprocessableWhenFolderSettingsIsInValid(
            ['name' => $this->faker->name],
            function (array $parameters) {
                return $this->createFolderResponse($parameters);
            }
        );
    }
}

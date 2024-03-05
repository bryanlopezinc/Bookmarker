<?php

namespace Tests\Feature\Folder;

use App\DataTransferObjects\Builders\FolderSettingsBuilder as Builder;
use App\ValueObjects\FolderSettings as FS;
use App\Models\Folder;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateFolderTest extends TestCase
{
    use WithFaker;
    use Concerns\TestsFolderSettings;

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
        Passport::actingAs(UserFactory::new()->create());

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
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name'        => $name = $this->faker->word,
            'description' => $description = $this->faker->sentence,
        ])->assertCreated();

        /** @var Folder */
        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($name, $folder->name->value);
        $this->assertEquals($description, $folder->description);
        $this->assertTrue($folder->visibility->isPublic());
        $this->assertTrue($folder->created_at->isSameMinute());
        $this->assertTrue($folder->updated_at->isSameMinute());
    }

    public function testCreateFolderWithoutDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word])->assertCreated();

        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertNull($folder->description);
    }

    public function testCreatePublicFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

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
        Passport::actingAs($user = UserFactory::new()->create());

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
        $this->assertCreateWithSettings(['maxCollaboratorsLimit' => '500'], function (FS $s) {
            $this->assertEquals(Builder::new()->setMaxCollaboratorsLimit(500)->build(), $s);
        });

        $this->assertCreateWithSettings(['acceptInviteConstraints' => ['InviterMustHaveRequiredPermission']], function (FS $s) {
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

        $this->assertCreateWithSettings(['notifications.newCollaborator.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNewCollaboratorNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.newCollaborator.enabled' => true], function (FS $s) {
            return $s->newCollaboratorNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.folderUpdated.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableFolderUpdatedNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.folderUpdated.enabled' => true], function (FS $s) {
            return $s->folderUpdatedNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.newBookmarks.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableNewBookmarksNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.newBookmarks.enabled' => true], function (FS $s) {
            return $s->newBookmarksNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.bookmarksRemoved.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableBookmarksRemovedNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.bookmarksRemoved.enabled' => true], function (FS $s) {
            return $s->bookmarksRemovedNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.collaboratorExit.enabled' => false], function (FS $s) {
            $this->assertEquals(Builder::new()->disableCollaboratorExitNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.collaboratorExit.enabled' => true], function (FS $s) {
            return $s->collaboratorExitNotificationIsEnabled;
        });

        $this->assertCreateWithSettings(['notifications.newCollaborator.mode' => '*'], function (FS $s) {
            return !$s->newCollaboratorNotificationMode->notifyWhenCollaboratorWasInvitedByMe();
        });

        $this->assertCreateWithSettings(['notifications.newCollaborator.mode' => 'invitedByMe'], function (FS $s) {
            $this->assertEquals(Builder::new()->enableOnlyCollaboratorsInvitedByMeNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(['notifications.collaboratorExit.mode' => '*'], function (FS $s) {
            return !$s->collaboratorExitNotificationMode->notifyWhenCollaboratorHasWritePermission();
        });

        $this->assertCreateWithSettings(['notifications.collaboratorExit.mode' => 'hasWritePermission'], function (FS $s) {
            $this->assertEquals(Builder::new()->enableOnlyCollaboratorWithWritePermissionNotification()->build(), $s);
        });

        $this->assertCreateWithSettings(
            [
                'notifications.newCollaborator.enabled'  => false,
                'notifications.folderUpdated.enabled'    => false,
                'notifications.collaboratorExit.enabled' => false
            ],
            function (FS $s) {
                return $s->newCollaboratorNotificationIsDisabled &&
                    $s->folderUpdatedNotificationIsDisabled &&
                    $s->collaboratorExitNotificationIsDisabled;
            }
        );
    }

    private function assertCreateWithSettings(array $settings, \Closure $assertion): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name'     => $this->faker->word,
            'settings' => Arr::undot($settings)
        ])->assertCreated();

        $settings = Folder::onlyAttributes()->where('user_id', $user->id)->first()->settings;

        if (!is_null($expectation = $assertion($settings))) {
            $this->assertTrue($expectation);
        }
    }

    public function testWillReturnUnprocessableWhenFolderSettingsIsInValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertWillReturnUnprocessableWhenFolderSettingsIsInValid(
            ['name' => $this->faker->name],
            function (array $parameters) {
                return $this->createFolderResponse($parameters);
            }
        );
    }
}

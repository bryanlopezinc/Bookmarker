<?php

namespace Tests\Feature\Folder;

use App\ValueObjects\FolderSettings;
use App\ValueObjects\FolderSettings as FS;
use App\Models\Folder;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreateFolderTest extends TestCase
{
    use WithFaker;

    protected function createFolderResponse(array $data = []): TestResponse
    {
        if (array_key_exists('settings', $data)) {
            $data['settings'] = json_encode($data['settings']);
        }

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
            'description' => $description = $this->faker->sentence
        ])->assertCreated();

        $folder = Folder::query()->where('user_id', $user->id)->sole();

        $this->assertEquals($name, $folder->name);
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

    public function testCreatePrivateFolder(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse(['name' => $this->faker->word, 'visibility' => 'private'])->assertCreated();

        $folder = Folder::where('user_id', $user->id)->first();

        $this->assertTrue($folder->visibility->isPrivate());
    }

    public function testCreateFolderWithNotifications(): void
    {
        $this->assertCreateWithNotifications(['enable_notifications' => false], function (FS $s) {
            return $s->notificationsAreDisabled();
        });

        $this->assertCreateWithNotifications(['enable_notifications' => true], function (FS $s) {
            return $s->notificationsAreEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_collaborator' => false], function (FS $s) {
            return $s->newCollaboratorNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_collaborator' => true], function (FS $s) {
            return $s->newCollaboratorNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_update' => false], function (FS $s) {
            return $s->folderUpdatedNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_update' => true], function (FS $s) {
            return $s->folderUpdatedNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_bookmark' => false], function (FS $s) {
            return $s->newBookmarksNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_bookmark' => true], function (FS $s) {
            return $s->newBookmarksNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_bookmark_delete' => false], function (FS $s) {
            return $s->bookmarksRemovedNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_bookmark_delete' => true], function (FS $s) {
            return $s->bookmarksRemovedNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_collaborator_exit' => false], function (FS $s) {
            return $s->collaboratorExitNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_collaborator_exit' => true], function (FS $s) {
            return $s->collaboratorExitNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_collaborator_by_user' => false], function (FS $s) {
            return $s->onlyCollaboratorsInvitedByMeNotificationIsDisabled();
        });

        $this->assertCreateWithNotifications(['notify_on_new_collaborator_by_user' => true], function (FS $s) {
            return $s->onlyCollaboratorsInvitedByMeNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_collaborator_exit_with_write' => false], function (FS $s) {
            return !$s->onlyCollaboratorWithWritePermissionNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(['notify_on_collaborator_exit_with_write' => true], function (FS $s) {
            return $s->onlyCollaboratorWithWritePermissionNotificationIsEnabled();
        });

        $this->assertCreateWithNotifications(
            [
                'notify_on_new_collaborator'  => false,
                'notify_on_update'            => false,
                'notify_on_collaborator_exit' => false
            ],
            function (FS $s) {
                return $s->newCollaboratorNotificationIsDisabled() &&
                    $s->folderUpdatedNotificationIsDisabled() &&
                    $s->collaboratorExitNotificationIsDisabled();
            }
        );
    }

    private function assertCreateWithNotifications(array $settings, \Closure $assertion): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $this->createFolderResponse([
            'name'     => $this->faker->word,
            'settings' => $settings
        ])->assertCreated();

        $settings = $this->fetchUserFolderSettings($user->id);

        $this->assertTrue(
            $assertion($settings),
        );
    }

    private function fetchUserFolderSettings(int $userId): FolderSettings
    {
        return Folder::onlyAttributes()
            ->where('user_id', $userId)
            ->first()
            ->settings;
    }

    public function testWillReturnUnprocessableWhenFolderSettingsIsInValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->createFolderResponse(['settings' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);

        //Assert settings cannot be empty
        $this->createFolderResponse(['settings' => '{}'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);

        //Assert settings must have a valid value and settings.N-enable must be a boolean
        $this->createFolderResponse([
            'settings' => ['N-enable' => 'foo']
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);;

        //Assert all values must be a boolean.
        $this->createFolderResponse([
            'settings' => [
                'notify_on_new_collaborator'               => 'foo',
                'notify_on_new_collaborator_by_user'       => 'foo',
                'notify_on_update'                         => 'foo',
                'notify_on_new_bookmark'                   => 'foo',
                'notify_on_bookmark_delete'                => 'foo',
                'notify_on_collaborator_exit'              => 'foo',
                'N-notify_on_collaborator_exit_with_write' => 'foo'
            ]
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }
}

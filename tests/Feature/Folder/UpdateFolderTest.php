<?php

namespace Tests\Feature\Folder;

use App\Enums\FolderSettingKey;
use App\Enums\FolderVisibility;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\FolderSetting;
use App\Services\Folder\ToggleFolderCollaborationRestriction;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Illuminate\Testing\Assert as PHPUnit;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;

class UpdateFolderTest extends TestCase
{
    use WithFaker, CreatesCollaboration;

    protected function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('updateFolder'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders', 'updateFolder');
    }

    public function testWillReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateFolderResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'folder']);

        $this->updateFolderResponse(['folder' => 33])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->updateFolderResponse(['name' => str_repeat('f', 51)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 50 characters.']);

        $this->updateFolderResponse(['description' => str_repeat('f', 151)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'The description must not be greater than 150 characters.']);
    }

    public function testWillReturnUnprocessableWhenVisibilityIsPublicAndPasswordIsMissing(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->updateFolderResponse(['folder' => 33, 'visibility' => 'public'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function testUpdateName(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $name = $this->faker->word;
        $this->updateFolderResponse(['name' => $name, 'folder' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['name' => $name]);
    }

    private function assertUpdated(Folder $original, array $attributes): void
    {
        $folder = Folder::query()->find($original->id);

        $folder->offsetUnset('updated_at');
        $original->offsetUnset('updated_at');

        $this->assertEquals(
            array_diff_assoc($folder->toArray(), $original->toArray()),
            $attributes
        );
    }

    public function testUpdateDescription(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $description = $this->faker->sentence;
        $this->updateFolderResponse(['description' => $description, 'folder' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['description' => $description]);

        $this->updateFolderResponse(['description' => null, 'folder' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['description' => null]);
    }

    public function testUpdatePrivacyToBePublic(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder'     => $folder->id,
            'password'   => 'password'
        ])->assertOk();

        $this->assertUpdated($folder, ['visibility' => FolderVisibility::PUBLIC->value]);
    }

    public function testUpdatePrivacyToBePrivate(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse(['visibility' => 'private', 'folder' => $folder->id])->assertOk();

        $this->assertUpdated($folder, ['visibility' => FolderVisibility::PRIVATE->value]);
    }

    public function testWillReturnConflictWhenMakingPublicFolderPublic(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder'     => $folder->id,
            'name'       => $this->faker->word,
            'password'   => 'password'
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => 'DuplicateVisibilityState']);
    }

    public function testWillReturnConflictWhenMakingPrivateFolderPrivate(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->updateFolderResponse([
            'visibility' => 'private',
            'folder'     => $folder->id,
            'name'       => $this->faker->word,
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => 'DuplicateVisibilityState']);
    }

    public function testWillReturnForbiddenWhenPasswordDoesNotMatch(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->private()->create();

        $this->updateFolderResponse([
            'visibility'  => 'public',
            'folder'     => $folder->id,
            'name'       => $this->faker->word,
            'password'   => 'I forgot my password please let me in'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'InvalidPassword']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse([
            'name'   => $this->faker->word,
            'folder' => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse([
            'name'   => $this->faker->word,
            'folder' => $folder->id + 1
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testCollaboratorCanUpdateFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder'      => $folder->id
        ])->assertOk();
    }

    public function testWillReturnForbiddenWenCollaboratorDoesNotHaveUpdateFolderPermission(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::UPDATE_FOLDER->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder' => $folder->id
        ])->assertForbidden()
            ->assertExactJson(['message' => 'NoUpdatePermission']);
    }

    public function testWillReturnForbiddenWhenCollaboratorIsUpdatingPrivacy(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->private()->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder'      => $folder->id,
            'visibility'  => 'public',
            'password'    => 'password'
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'NoUpdatePrivacyPermission']);

        //with folder owner password
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder'      => $folder->id,
            'visibility'  => 'public',
            'password'    => 'password'
        ])->assertForbidden()
            ->assertExactJson($error);
    }

    public function testWillReturnNotFoundWhenFolderOwnerHasDeletedAccount(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder'      => $folder->id
        ])->assertNotFound()
            ->assertExactJson(['message' => "FolderNotFound"]);
    }

    public function testWillNotifyFolderOwnerWhenCollaboratorUpdatesFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name'        => $newName = $this->faker->word,
            'description' => $newDescription = $this->faker->sentence,
            'folder'      => $folder->id,
        ])->assertOk();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folder->user_id)->sole(['data'])->data;

        PHPUnit::assertArraySubset([
            'folder_updated' => $folder->id,
            'updated_by' => $collaborator->id,
            'changes' => [
                'name' => [
                    'from' => $folder->name,
                    'to' => $newName
                ],
                'description' => [
                    'from' => $folder->description,
                    'to' => $newDescription
                ],
            ]
        ], $notificationData);
    }

    public function testWillNotSendNotificationWhenUpdateWasPerformedByFolderOwner(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Notification::fake();

        Passport::actingAs($user);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder'      => $folder->id
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ENABLE_NOTIFICATIONS->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder' => $folder->id,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenFolderUpdatedNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NOTIFy_ON_UPDATE->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Notification::fake();

        Passport::actingAs($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder' => $folder->id,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willReturnCorrectResponseWhenUpdateFolderActionsIsDisabled(): void
    {
        /** @var ToggleFolderCollaborationRestriction */
        $updateCollaboratorActionService = app(ToggleFolderCollaborationRestriction::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        //Assert collaborator can update when disabled action is not remove bookmark action
        $updateCollaboratorActionService->update($folder->id, Permission::INVITE_USER, false);
        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder' => $folder->id])->assertOk();

        $updateCollaboratorActionService->update($folder->id, Permission::UPDATE_FOLDER, false);

        $this->updateFolderResponse(['name' => $this->faker->word, 'folder' => $folder->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'UpdateFolderActionDisabled']);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder' => $folder->id])->assertNotFound();

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder' => $folder->id])->assertOk();
    }
}

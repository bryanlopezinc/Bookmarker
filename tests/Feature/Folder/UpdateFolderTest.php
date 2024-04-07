<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\FolderVisibility;
use App\Enums\Permission;
use App\Models\Folder;
use App\UAC;
use App\ValueObjects\FolderName;
use App\ValueObjects\FolderSettings;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class UpdateFolderTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use Concerns\TestsFolderSettings;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;

    protected function updateFolderResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(
            route('updateFolder', Arr::only($parameters, ['folder_id'])),
            $parameters
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}', 'updateFolder');
    }

    public function testWillReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse(['folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->updateFolderResponse(['folder_id' => 'foo'])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->updateFolderResponse(['folder_id' => 44])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->updateFolderResponse(['folder_id' => 33])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->updateFolderResponse(['name' => str_repeat('f', 51), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 50 characters.']);

        $this->updateFolderResponse(['description' => str_repeat('f', 151), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'The description must not be greater than 150 characters.']);
    }

    public function testUpdateName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $name = $this->faker->word;

        $this->updateFolderResponse($query = ['name' => $name, 'folder_id' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['name' => new FolderName($name)]);
        $this->updateFolderResponse($query)->assertNoContent();

        $this->assertNoMetricsRecorded($user->id, $folder->id, CollaboratorMetricType::UPDATES);
    }

    private function assertUpdated(Folder $original, array $attributes): void
    {
        $updated = Folder::query()->find($original->id);

        $updated->offsetUnset('updated_at');
        $original->offsetUnset('updated_at');

        $originalToArray = $original->toArray();
        $updatedToArray = $updated->toArray();

        $this->assertEquals(
            Arr::only($updatedToArray, $difference = array_keys($attributes)),
            $attributes
        );

        Arr::forget($updatedToArray, $difference);
        Arr::forget($originalToArray, $difference);

        $this->assertEquals($originalToArray, $updatedToArray);
    }

    public function testUpdateDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $description = $this->faker->sentence;
        $this->updateFolderResponse($query = ['description' => $description, 'folder_id' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['description' => $description]);
        $this->updateFolderResponse($query)->assertNoContent();

        $this->updateFolderResponse(['description' => null, 'folder_id' => $folder->id])->assertOk();
        $this->assertUpdated($folder, ['description' => null]);
    }

    #[Test]
    public function updateVisibilityToPublic(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'     => $publicFolder->id,
        ])->assertNoContent();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::PUBLIC->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->id, 'visibility' => 'public'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The Password field is required for this action.']);

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse(['folder_id' => $privateFolder->id, 'visibility' => 'public'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The Password field is required for this action.']);

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $privateFolder->id,
            'password'   => 'I forgot my password please let me in'
        ])->assertUnauthorized()->assertJsonFragment(['message' => 'InvalidPasswordForFolderUpdate']);

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $privateFolder->id,
            'password'   => 'password'
        ])->assertOk();

        $this->updateFolderResponse([
            'visibility' => 'public',
            'folder_id'  => $passwordProtectedFolder->id,
            'password'   => 'password'
        ])->assertOk();

        $this->assertUpdated($privateFolder, ['visibility' => FolderVisibility::PUBLIC->value]);
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::PUBLIC->value, 'password' => null]);
    }

    #[Test]
    public function updateVisibilityToPrivate(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse([
            'visibility' => 'private',
            'folder_id'  => $privateFolder->id,
        ])->assertNoContent();
        $this->assertUpdated($privateFolder, ['visibility' => FolderVisibility::PRIVATE->value]);

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'private',
            'folder_id'  => $publicFolder->id,
        ])->assertOk();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::PRIVATE->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->id, 'visibility' => 'private'])
            ->assertOk();
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::PRIVATE->value, 'password' => null]);

        $folderThatHasCollaborators = FolderFactory::new()->for($user)->create();
        $this->CreateCollaborationRecord(UserFactory::new()->create(), $folderThatHasCollaborators);
        $this->updateFolderResponse(['visibility' => 'private', 'folder_id' => $folderThatHasCollaborators->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
    }

    #[Test]
    public function updateVisibilityToCollaboratorsOnly(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folderVisibleToCollaboratorsOnly = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();
        $this->updateFolderResponse([
            'visibility' => 'collaborators',
            'folder_id'  => $folderVisibleToCollaboratorsOnly->id,
        ])->assertNoContent();
        $this->assertUpdated($folderVisibleToCollaboratorsOnly, ['visibility' => FolderVisibility::COLLABORATORS->value]);

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse([
            'visibility' => 'collaborators',
            'folder_id'  => $publicFolder->id,
        ])->assertOk();
        $this->assertUpdated($publicFolder, ['visibility' => FolderVisibility::COLLABORATORS->value]);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->id, 'visibility' => 'collaborators'])
            ->assertOk();
        $this->assertUpdated($passwordProtectedFolder, ['visibility' => FolderVisibility::COLLABORATORS->value, 'password' => null]);
    }

    #[Test]
    public function updateVisibilityToPasswordProtected(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $query = ['visibility' => 'password_protected', 'folder_password' => 'password'];

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(['folder_id' => $passwordProtectedFolder->id, ...$query])->assertOk();
        $passwordProtectedFolder->refresh();
        $this->assertEquals($passwordProtectedFolder->visibility, FolderVisibility::PASSWORD_PROTECTED);
        $this->assertTrue(Hash::check('password', $passwordProtectedFolder->password));

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse(['folder_id' => $publicFolder->id, ...$query])->assertOk();
        $publicFolder->refresh();
        $this->assertEquals($publicFolder->visibility, FolderVisibility::PASSWORD_PROTECTED);
        $this->assertTrue(Hash::check('password', $publicFolder->password));

        $folderThatHasCollaborators = FolderFactory::new()->for($user)->create();
        $this->CreateCollaborationRecord(UserFactory::new()->create(), $folderThatHasCollaborators);
        $this->updateFolderResponse(['folder_id' => $folderThatHasCollaborators->id, ...$query])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotMakeFolderWithCollaboratorsPrivate']);
    }

    #[Test]
    public function updateFolderPassword(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse($query = [
            'folder_password' => 'new_password',
            'folder_id'       => $folder->id,
        ])->assertBadRequest()->assertJsonFragment($expectation = ['message' => 'FolderNotPasswordProtected']);

        $folder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse($query)->assertBadRequest()->assertJsonFragment($expectation);

        $folder = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();
        $this->updateFolderResponse($query)->assertBadRequest()->assertJsonFragment($expectation);

        $folder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(array_replace($query, ['folder_id' => $folder->id]))->assertOk();
        $this->assertTrue(Hash::check('new_password', Folder::find($folder->id)->password));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse([
            'name'   => $this->faker->word,
            'folder_id' => $folder->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals($folder, $folder->refresh());
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->updateFolderResponse([
            'name'   => $this->faker->word,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    public function testCollaboratorCanUpdateFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $this->loginUser($collaborator);

        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id'   => $folder->id
        ])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::UPDATES);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    public function collaboratorWithRoleCanUpdateFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, Permission::UPDATE_FOLDER]));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id'   => $folder->id
        ])->assertOk();
    }

    public function testWillReturnForbiddenWenCollaboratorDoesNotHaveUpdateFolderPermissionOrRole(): void
    {
        [$collaborator, $folderOwner, $collaboratorWithoutAnyPermission] = UserFactory::times(3)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $permissions = UAC::all()
            ->toCollection()
            ->reject(Permission::UPDATE_FOLDER->value)
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);
        $this->CreateCollaborationRecord($collaboratorWithoutAnyPermission, $folder);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::UPDATE_FOLDER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->for($folderOwner)->create(), permissions: Permission::UPDATE_FOLDER));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder_id' => $folder->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->loginUser($collaboratorWithoutAnyPermission);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder_id' => $folder->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals($folder, $folder->refresh());
    }

    public function willReturnForbiddenWenCollaboratorRoleNoLongerExists(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();
        [$folder, $folderOwnerSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $otherFolder = FolderFactory::new()->create(), permissions: Permission::UPDATE_FOLDER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folderOwnerSecondFolder, permissions: Permission::UPDATE_FOLDER));

        $role->delete();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder_id' => $folder->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder_id' => $folderOwnerSecondFolder->id
        ])->assertOk();

        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'folder_id' => $otherFolder->id
        ])->assertOk();

        $this->assertEquals($folder, $folder->refresh());
    }

    public function testWillReturnForbiddenWhenCollaboratorIsUpdatingRestrictedAttribute(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folderVisibleToCollaboratorsOnly = FolderFactory::new()->for($folderOwner)->visibleToCollaboratorsOnly()->create();
        $publicFolder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folderVisibleToCollaboratorsOnly, Permission::UPDATE_FOLDER);
        $this->CreateCollaborationRecord($collaborator, $publicFolder, Permission::UPDATE_FOLDER);

        $this->loginUser($collaborator);

        $this->updateFolderResponse($query = [
            'folder_id'   => $folderVisibleToCollaboratorsOnly->id,
            'visibility'  => 'public',
            'password'    => 'password'
        ])->assertForbidden()->assertJsonFragment($error = ['message' => 'CannotUpdateFolderAttribute']);

        $this->updateFolderResponse(array_replace($query, ['visibility' => 'password_protected', 'folder_password' => 'password']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'private']))->assertForbidden()->assertJsonFragment($error);
        $this->updateFolderResponse(array_replace($query, ['visibility' => 'collaborators']))->assertForbidden()->assertJsonFragment($error);

        $this->updateFolderResponse(['folder_id' => $publicFolder->id, 'visibility'  => 'private'])
            ->assertForbidden()
            ->assertJsonFragment($error);

        $this->updateFolderResponse(['folder_id' => $publicFolder->id, 'visibility'  => 'collaborators'])
            ->assertForbidden()
            ->assertJsonFragment($error);

        $this->updateFolderResponse(['folder_id' => $folderVisibleToCollaboratorsOnly->id, 'settings'  => ['max_collaborators_limit' => 450]])
            ->assertForbidden()
            ->assertJsonFragment($error);

        $this->assertEquals($folderVisibleToCollaboratorsOnly, $folderVisibleToCollaboratorsOnly->refresh());
        $this->assertEquals($publicFolder, $publicFolder->refresh());
    }

    public function testWillReturnNotFoundWhenFolderOwnerHasDeletedAccount(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id'  => $folder->id
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);

        $this->assertEquals($folder, $folder->refresh());
    }

    #[Test]
    public function willReturnUnprocessableWhenFolderSettingsIsInValid(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->assertWillReturnUnprocessableWhenFolderSettingsIsInValid(
            ['folder_id' => $folder->id],
            function (array $parameters) {
                return $this->updateFolderResponse($parameters);
            }
        );
    }

    #[Test]
    public function updateSettings(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()
            ->for($user)
            ->settings(FolderSettingsBuilder::new()->setMaxCollaboratorsLimit(450))
            ->create();

        $this->updateFolderResponse([
            'folder_id' => $folder->id,
            'settings' => ['notifications' => ['new_collaborator' => ['enabled' => 0]]]
        ])->assertOk();

        /** @var FolderSettings */
        $updatedFolderSettings = Folder::query()->whereKey($folder->id)->sole(['settings'])->settings;

        $this->assertEquals(450, $updatedFolderSettings->maxCollaboratorsLimit);
        $this->assertTrue($updatedFolderSettings->newCollaboratorNotificationIsDisabled);
    }

    public function testWillNotifyFolderOwnerWhenCollaboratorUpdatesFolder(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => $newName = $this->faker->word,
            'description' => $newDescription = $this->faker->sentence,
            'folder_id'   => $folder->id,
        ])->assertOk();

        $folder->refresh();
        $notificationData = $folderOwner->notifications()->get(['data', 'type']);

        $this->assertEqualsCanonicalizing(['FolderUpdated', 'FolderUpdated'], $notificationData->pluck('type')->all());
        $expected = [
            'N-type'          => 'FolderUpdated',
            'version'         => '1.0.0',
            'collaborator_id' => $collaborator->id,
            'folder_id'       => $folder->id,
            'collaborator_full_name' => $collaborator->full_name->value,
            'folder_name' => $folder->name->value,
        ];

        $this->assertCount(2, $notificationData);

        $this->assertEquals(
            $notificationData->pluck('data')->where('modified', 'description')->sole(),
            [
                ...$expected,
                'modified'  => 'description',
                'changes' => [
                    'from' => $folder->description,
                    'to' => $newDescription,
                ],
            ]
        );

        $this->assertEquals(
            $notificationData->pluck('data')->where('modified', 'name')->sole(),
            [
                ...$expected,
                'modified' => 'name',
                'changes' => [
                    'from' => $folder->name->value,
                    'to' => $newName,
                ],
            ]
        );
    }

    public function testWillNotSendNotificationWhenUpdateWasPerformedByFolderOwner(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Notification::fake();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id'   => $folder->id
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id' => $folder->id,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function testWillNotSendNotificationsWhenFolderUpdatedNotificationsIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->disableFolderUpdatedNotification())
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        Notification::fake();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name' => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id' => $folder->id,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function whenUpdateFolderFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        //Assert collaborator can update when disabled action is not update folder action
        $updateCollaboratorActionService->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER);

        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($errorMessage = ['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($errorMessage);

        //when user is not a collaborator
        $this->loginUser(UserFactory::new()->create());
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertNotFound();

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => 'Docker Problems', 'folder_id' => $folder->id])->assertOk();
        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
    }

    #[Test]
    public function whenUpdateFolderNameFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER_NAME);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
    }

    #[Test]
    public function whenUpdateFolderDescriptionFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER);

        $updateCollaboratorActionService->disable($folder->id, Feature::UPDATE_FOLDER_DESCRIPTION);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();

        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($errorMessage = ['message' => 'FolderFeatureDisAbled']);

        $this->updateFolderResponse(['description' => null, 'folder_id' => $folder->id])->assertForbidden()->assertJsonFragment($errorMessage);

        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['name' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
        $this->updateFolderResponse(['description' => $this->faker->word, 'folder_id' => $folder->id])->assertOk();
    }
}

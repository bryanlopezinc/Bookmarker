<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\UAC;
use App\Enums\Permission;
use Tests\Traits\CreatesRole;
use App\ValueObjects\FolderName;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use App\Enums\CollaboratorMetricType;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\GeneratesId;

class UpdateFolderTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use GeneratesId;

    public function testWillReturnAuthorizedWhenUserIsNotSignedIn(): void
    {
        $this->updateFolderResponse(['folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser();

        $this->updateFolderResponse(['folder_id' => 44, 'name' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->updateFolderResponse(['folder_id' => $id = $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->updateFolderResponse(['name' => str_repeat('f', 51), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 50 characters.']);

        $this->updateFolderResponse(['description' => str_repeat('f', 151), 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description' => 'The description must not be greater than 150 characters.']);
    }

    public function testUpdateName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $name = $this->faker->word;

        $this->updateFolderResponse($query = ['name' => $name, 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->assertUpdated($folder, ['name' => new FolderName($name)]);
        $this->updateFolderResponse($query)->assertNoContent();

        $this->assertNoMetricsRecorded($user->id, $folder->id, CollaboratorMetricType::UPDATES);
    }

    public function testUpdateDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $description = $this->faker->sentence;
        $this->updateFolderResponse($query = ['description' => $description, 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->assertUpdated($folder, ['description' => $description]);
        $this->updateFolderResponse($query)->assertNoContent();

        $this->updateFolderResponse(['description' => null, 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->assertUpdated($folder, ['description' => null]);
    }

    #[Test]
    public function updateFolderPassword(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $privateFolder = FolderFactory::new()->for($user)->private()->create();
        $this->updateFolderResponse($query = [
            'folder_password' => 'new_password',
            'folder_id'       => $privateFolder->public_id->present(),
        ])->assertBadRequest()->assertJsonFragment($expectation = ['message' => 'FolderNotPasswordProtected']);

        $publicFolder = FolderFactory::new()->for($user)->create();
        $this->updateFolderResponse(array_replace($query, ['folder_id' => $publicFolder->public_id->present()]))->assertBadRequest()->assertJsonFragment($expectation);

        $folderVisibleToCollaboratorsOnly = FolderFactory::new()->for($user)->visibleToCollaboratorsOnly()->create();
        $this->updateFolderResponse(array_replace($query, ['folder_id' => $folderVisibleToCollaboratorsOnly->public_id->present()]))
            ->assertBadRequest()
            ->assertJsonFragment($expectation);

        $passwordProtectedFolder = FolderFactory::new()->for($user)->passwordProtected()->create();
        $this->updateFolderResponse(array_replace($query, ['folder_id' => $passwordProtectedFolder->public_id->present()]))->assertOk();
        $this->assertTrue(Hash::check('new_password', $passwordProtectedFolder->refresh()->password));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals($folder, $folder->refresh());
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser();

        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $this->generateFolderId()->present()
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);
    }

    #[Test]
    #[DataProvider('collaboratorWithAdequatePermissionCanUpdateFolderData')]
    public function collaboratorWithAdequatePermissionCanUpdateFolder(array $permissions, array $query): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            ...$query
        ])->assertOk();
    }

    public static function collaboratorWithAdequatePermissionCanUpdateFolderData(): array
    {
        $file = UploadedFile::fake()->image('folderIcon.jpg')->size(2000);

        return  [
            'Update folder name'            => [[Permission::UPDATE_FOLDER_NAME], ['name' => 'foo']],
            'Update folder description'     => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['description' => 'bar']],
            'Update folder thumbnail'       => [[Permission::UPDATE_FOLDER_THUMBNAIL], ['thumbnail' => $file]],
            'AllUpdateTypes -> name'        => [Permission::updateFolderTypes(), ['name' => 'foo']],
            'AllUpdateTypes -> description' => [Permission::updateFolderTypes(), ['description' => 'foo']],
            'AllUpdateTypes -> thumbnail'   => [Permission::updateFolderTypes(), ['thumbnail' => $file]],
            'AllUpdateTypes -> all'         => [Permission::updateFolderTypes(), ['thumbnail' => $file, 'name' => 'foo', 'description' => 'baz']],
        ];
    }

    #[Test]
    public function willIncrementCollaboratorMetrics(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create(['name' => 'foo']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_NAME);

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['name' => 'bar', 'folder_id' => $folder->public_id->present()])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::UPDATES);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->updateFolderResponse(['name' => 'baz', 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    #[DataProvider('collaboratorWithAdequatePermissionCanUpdateFolderData')]
    public function collaboratorWithRoleThatHasAdequatePermissionCanUpdateFolder(array $permissions, array $query): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: [Permission::INVITE_USER, ...$permissions]));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            ...$query,
        ])->assertOk();
    }

    #[Test]
    #[DataProvider('willReturnForbiddenWenCollaboratorDoesNotHaveUpdateFolderPermissionOrRoleData')]
    public function willReturnForbiddenWhenCollaboratorDoesNotHaveUpdateFolderPermissionOrRoleWithAdequatePermission(array $permissions, array $query): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions);

        //has role with invite user permission in current folder
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));

        //has role with all update folder permissions in another folder
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->create(), permissions: Permission::updateFolderTypes()));

        //has role with all update folder permissions in another folder owner by folder owner
        $this->attachRoleToUser($collaborator, $this->createRole(folder: FolderFactory::new()->for($folderOwner)->create(), permissions: Permission::updateFolderTypes()));

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id' => $folder->public_id->present(),
            ...$query
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals($folder, $folder->refresh());
    }

    public static function willReturnForbiddenWenCollaboratorDoesNotHaveUpdateFolderPermissionOrRoleData(): array
    {
        $file = UploadedFile::fake()->image('folderIcon.jpg')->size(2000);

        $all = ['name' => 'foo', 'description' => 'bar', 'thumbnail' => $file];

        $allPermissionsExceptUpdateFolderPermissions = UAC::all()
            ->toCollection()
            ->reject(fn (string $permission) => in_array($permission, Arr::pluck(Permission::updateFolderTypes(), 'value'), true))
            ->all();

        return  [
            'All permission except update -> name'        => [$allPermissionsExceptUpdateFolderPermissions, ['name' => 'foo']],
            'All permission except update -> description' => [$allPermissionsExceptUpdateFolderPermissions, ['description' => 'foo']],
            'All permission except update -> Thumbnail'   => [$allPermissionsExceptUpdateFolderPermissions, ['thumbnail' => $file]],
            'All permission except update -> all'         => [$allPermissionsExceptUpdateFolderPermissions, $all],

            'Only Update folder description -> name'      => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['name' => 'bar']],
            'Only Update folder description -> thumbnail' => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['thumbnail' => $file]],
            'Only Update folder description -> all'       => [[Permission::UPDATE_FOLDER_DESCRIPTION], $all],

            'Only Update folder thumbnail -> name'        => [[Permission::UPDATE_FOLDER_THUMBNAIL], ['name' => 'foo']],
            'Only Update folder thumbnail -> description' => [[Permission::UPDATE_FOLDER_THUMBNAIL], ['description' => 'foo']],
            'Only Update folder thumbnail -> all'         => [[Permission::UPDATE_FOLDER_THUMBNAIL], $all],

            'Only Update folder name -> description'      => [[Permission::UPDATE_FOLDER_NAME], ['description' => 'bar']],
            'Only Update folder name -> thumbnail'        => [[Permission::UPDATE_FOLDER_NAME], ['thumbnail' => $file]],
            'Only Update folder name -> all'              => [[Permission::UPDATE_FOLDER_NAME], $all],
        ];
    }

    public function willReturnForbiddenWhenCollaboratorRoleNoLongerExists(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();
        [$folder, $folderOwnerSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $otherFolder = FolderFactory::new()->create(), permissions: Permission::UPDATE_FOLDER_NAME));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folderOwnerSecondFolder, permissions: Permission::UPDATE_FOLDER_NAME));

        $role->delete();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $folder->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $folderOwnerSecondFolder->public_id->present()
        ])->assertOk();

        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $otherFolder->public_id->present()
        ])->assertOk();

        $this->assertEquals($folder, $folder->refresh());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsUpdatingFolderPassword(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'folder_id'       => $folder->public_id->present(),
            'folder_password' => 'password'
        ])->assertForbidden()->assertJsonFragment(['message' => 'CannotUpdateFolderAttribute']);
    }

    public function testWillReturnNotFoundWhenFolderOwnerHasDeletedAccount(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::UPDATE_FOLDER_NAME);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->updateFolderResponse([
            'name'        => $this->faker->word,
            'description' => $this->faker->sentence,
            'folder_id'   => $folder->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => "FolderNotFound"]);

        $this->assertEquals($folder, $folder->refresh());
    }
}

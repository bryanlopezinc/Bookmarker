<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Updating;

use App\Enums\ActivityType;
use App\UAC;
use App\Enums\Permission;
use Tests\Traits\CreatesRole;
use App\ValueObjects\FolderName;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use App\Enums\CollaboratorMetricType;
use App\DataTransferObjects\Activities\DescriptionChangedActivityLogData;
use App\DataTransferObjects\Activities\FolderNameChangedActivityLogData;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\ClearFoldersIconsStorage;
use Tests\Traits\GeneratesId;

class UpdateFolderTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;
    use GeneratesId;
    use ClearFoldersIconsStorage;

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

        $folder = FolderFactory::new()->for($user)->create(['name' => 'foo']);

        $name = 'baz';

        $this->updateFolderResponse($query = ['name' => $name, 'folder_id' => $folder->public_id->present()])->assertOk();
        $this->assertUpdated($folder, ['name' => new FolderName($name)]);

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertNoMetricsRecorded($user->id, $folder->id, CollaboratorMetricType::UPDATES);
        $this->assertEquals($activity->type, ActivityType::NAME_CHANGED);
        $this->assertEquals($activity->data, (new FolderNameChangedActivityLogData($user, 'foo', $name))->toArray());

        //assert will return no content when no attribute was changed
        $this->refreshApplication();
        $this->loginUser($user);
        $this->updateFolderResponse($query)->assertNoContent();

        $this->assertCount(1, $folder->activities);
    }

    public function testUpdateDescription(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $description = $this->faker->sentence;
        $this->updateFolderResponse($query = ['description' => $description, 'folder_id' => $folder->public_id->present()])->assertOk();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertUpdated($folder, ['description' => $description]);
        $this->assertEquals($activity->type, ActivityType::DESCRIPTION_CHANGED);
        $this->assertEquals($activity->data, (new DescriptionChangedActivityLogData($user, $folder->description, $description))->toArray());

        //assert will return no content when no attribute was changed
        $this->updateFolderResponse($query)->assertNoContent();

        //assert can set description to be empty.
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
        $this->assertTrue($passwordProtectedFolder->activities->isEmpty());
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
        $this->assertTrue($folder->activities->isEmpty());
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

        $this->assertTrue($folder->activities->isNotEmpty());
    }

    public static function collaboratorWithAdequatePermissionCanUpdateFolderData(): array
    {
        $file = UploadedFile::fake()->image('folderIcon.jpg')->size(2000);

        return  [
            'Update folder name'            => [[Permission::UPDATE_FOLDER_NAME], ['name' => 'foo']],
            'Update folder description'     => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['description' => 'bar']],
            'Update folder icon'            => [[Permission::UPDATE_FOLDER_ICON], ['icon' => $file]],
            'AllUpdateTypes -> name'        => [Permission::updateFolderTypes(), ['name' => 'foo']],
            'AllUpdateTypes -> description' => [Permission::updateFolderTypes(), ['description' => 'foo']],
            'AllUpdateTypes -> icon'        => [Permission::updateFolderTypes(), ['icon' => $file]],
            'AllUpdateTypes -> all'         => [Permission::updateFolderTypes(), ['icon' => $file, 'name' => 'foo', 'description' => 'baz']],
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

        $this->assertTrue($folder->activities->isNotEmpty());
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
        $this->assertTrue($folder->activities->isEmpty());
    }

    public static function willReturnForbiddenWenCollaboratorDoesNotHaveUpdateFolderPermissionOrRoleData(): array
    {
        $file = UploadedFile::fake()->image('folderIcon.jpg')->size(2000);

        $all = ['name' => 'foo', 'description' => 'bar', 'icon' => $file];

        $allPermissionsExceptUpdateFolderPermissions = UAC::all()->except(Permission::updateFolderTypes())->toArray();

        return  [
            'All permission except update -> name'        => [$allPermissionsExceptUpdateFolderPermissions, ['name' => 'foo']],
            'All permission except update -> description' => [$allPermissionsExceptUpdateFolderPermissions, ['description' => 'foo']],
            'All permission except update -> Icon'        => [$allPermissionsExceptUpdateFolderPermissions, ['icon' => $file]],
            'All permission except update -> all'         => [$allPermissionsExceptUpdateFolderPermissions, $all],

            'Only Update folder description -> name'      => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['name' => 'bar']],
            'Only Update folder description -> icon'      => [[Permission::UPDATE_FOLDER_DESCRIPTION], ['icon' => $file]],
            'Only Update folder description -> all'       => [[Permission::UPDATE_FOLDER_DESCRIPTION], $all],

            'Only Update folder icon -> name'              => [[Permission::UPDATE_FOLDER_ICON], ['name' => 'foo']],
            'Only Update folder icon -> description'       => [[Permission::UPDATE_FOLDER_ICON], ['description' => 'foo']],
            'Only Update folder icon -> all'               => [[Permission::UPDATE_FOLDER_ICON], $all],

            'Only Update folder name -> description'      => [[Permission::UPDATE_FOLDER_NAME], ['description' => 'bar']],
            'Only Update folder name -> icon'             => [[Permission::UPDATE_FOLDER_NAME], ['icon' => $file]],
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
        $this->assertTrue($folder->activities->isEmpty());
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

        $this->assertTrue($folder->activities->isEmpty());
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
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willNotLogActivityWhenFolderIsPrivateFolder(): void
    {
        $user = UserFactory::new()->create();
        $factory = FolderFactory::new()->for($user);

        $passwordProtectedFolder = $factory->passwordProtected()->create();
        $privateFolder = $factory->private()->create();

        $this->loginUser($user);
        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $passwordProtectedFolder->public_id->present(),
        ])->assertOk();

        $this->updateFolderResponse([
            'name'      => $this->faker->word,
            'folder_id' => $privateFolder->public_id->present(),
        ])->assertOk();

        $this->assertTrue($privateFolder->activities->isEmpty());
        $this->assertTrue($passwordProtectedFolder->activities->isEmpty());
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableActivities(false))
            ->create(['name' => 'foo', 'description' => 'bar']);

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::updateFolderTypes());

        $this->loginUser($collaborator);
        $this->updateFolderResponse(['folder_id' => $folder->public_id->present(), 'name' => 'bar'])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaborator);
        $this->updateFolderResponse(['folder_id' => $folder->public_id->present(), 'description' => 'foo bar'])->assertOk();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['folder_id' => $folder->public_id->present(), 'name' => 'bar foo bars'])->assertOk();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->updateFolderResponse(['folder_id' => $folder->public_id->present(), 'description' => 'this is cinema'])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}

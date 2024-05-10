<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Suspension;

use App\Enums\Permission;
use App\Filesystem\ProfileImageFileSystem;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
use Tests\TestCase;
use App\Models\User;
use App\Models\Folder;
use Tests\Traits\GeneratesId;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Tests\Feature\AssertValidPaginationData;
use Tests\Traits\CreatesRole;

class FetchSuspendedCollaboratorsTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;
    use AssertValidPaginationData;
    use CreatesRole;

    private function fetchSuspendCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(
            route('fetchSuspendCollaborators', $parameters),
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/suspend', 'fetchSuspendCollaborators');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchSuspendCollaboratorResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $folderId = $this->generateFolderId()->present();

        $this->loginUser();
        $this->fetchSuspendCollaboratorResponse(['folder_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folderId, 'name' => str_repeat('r', 11)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.']);

        $this->assertValidPaginationData($this, 'fetchSuspendCollaborators', ['folder_id' => $folderId]);
    }

    #[Test]
    public function fetch(): void
    {
        /** @var User */
        [$folderOwner, $suspendedCollaborator] = UserFactory::times(2)->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $filesystem = new ProfileImageFileSystem();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        $record = SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(8, 'data.0.attributes')
            ->assertJsonCount(3, 'data.0.attributes.suspended_by')
            ->assertJsonPath('data.0.type', 'SuspendedCollaborator')
            ->assertJsonPath('data.0.attributes.id', $suspendedCollaborator->public_id->present())
            ->assertJsonPath('data.0.attributes.profile_image_url', $filesystem->publicUrl($suspendedCollaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.name', $suspendedCollaborator->full_name->present())
            ->assertJsonPath('data.0.attributes.suspended_at', $record->suspended_at->toDateTimeString())
            ->assertJsonMissingPath('data.0.attributes.suspended_until')
            ->assertJsonPath('data.0.attributes.is_suspended_indefinitely', true)
            ->assertJsonPath('data.0.attributes.suspension_period_is_past', false)
            ->assertJsonPath('data.0.attributes.was_suspended_by_auth_user', true)
            ->assertJsonPath('data.0.attributes.suspended_by.id', $folderOwner->public_id->present())
            ->assertJsonPath('data.0.attributes.suspended_by.profile_image_url', $filesystem->publicUrl($folderOwner->profile_image_path))
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'name',
                            'profile_image_url',
                            'suspended_at',
                            'is_suspended_indefinitely',
                            'suspension_period_is_past',
                            'was_suspended_by_auth_user',
                            'suspended_by' => [
                                'id',
                                'name',
                                'profile_image_url'
                            ]
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function collaboratorWithPermissionCanViewSuspendedCollaborators(): void
    {
        [$collaboratorWithSuspendUserPermission, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])->assertOk();
    }

    #[Test]
    public function collaboratorWithRoleCanViewSuspendedCollaborators(): void
    {
        [$collaboratorWithSuspendUserRole, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserRole, $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        $this->attachRoleToUser($collaboratorWithSuspendUserRole, $this->createRole('admin', $folder, Permission::SUSPEND_USER));

        $this->loginUser($collaboratorWithSuspendUserRole);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])->assertOk();
    }

    #[Test]
    public function whenCollaboratorWasNotSuspendedByAuthUser(): void
    {
        [$collaboratorWithSuspendUserPermission, $suspendedCollaborator, $folderOwner] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder, suspendedBy: $collaboratorWithSuspendUserPermission);

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.0.attributes.was_suspended_by_auth_user', false);
    }

    #[Test]
    public function whenCollaboratorIsSuspendedForACertainPeriod(): void
    {
        /** @var User */
        [$folderOwner, $suspendedCollaborator] = UserFactory::times(2)->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        $record = SuspendCollaborator::suspend($suspendedCollaborator, $folder, suspensionDurationInHours: 1);

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.suspended_until', $record->suspended_until->toDateTimeString())
            ->assertJsonPath('data.0.attributes.is_suspended_indefinitely', false)
            ->assertJsonPath('data.0.attributes.suspension_period_is_past', false);
    }

    #[Test]
    public function willSortByLatestByDefault(): void
    {
        /** @var User */
        [$folderOwner, $suspendedCollaborator, $otherSuspendedCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($otherSuspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);
        $this->travel(1)->minute(fn () => SuspendCollaborator::suspend($otherSuspendedCollaborator, $folder));

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.id', $otherSuspendedCollaborator->public_id->present())
            ->assertJsonPath('data.1.attributes.id', $suspendedCollaborator->public_id->present());
    }

    #[Test]
    public function whenCollaboratorSuspensionPeriodIsPast(): void
    {
        /** @var User */
        [$folderOwner, $suspendedCollaborator, $otherSuspendedCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($otherSuspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);
        SuspendCollaborator::suspend($otherSuspendedCollaborator, $folder, suspensionDurationInHours: 1);

        $this->loginUser($folderOwner);
        $this->travel(61)->minutes(function () use ($folder, $otherSuspendedCollaborator) {
            $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.attributes.id', $otherSuspendedCollaborator->public_id->present())
                ->assertJsonPath('data.0.attributes.is_suspended_indefinitely', false)
                ->assertJsonPath('data.0.attributes.suspension_period_is_past', true);
        });
    }

    #[Test]
    public function willReturnOnlySuspendedCollaboratorsWithExistingAccount(): void
    {
        /** @var User */
        [$folderOwner, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $suspendedCollaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function filterByName(): void
    {
        /** @var User */
        [$currentUser, $suspendedCollaborator, $otherSuspendedCollaborator] = UserFactory::times(3)->create(['first_name' => 'Bryan']);

        /** @var Folder */
        [$folder, $currentUserOtherFolder] = FolderFactory::times(2)->for($currentUser)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($otherSuspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);
        SuspendCollaborator::suspend($otherSuspendedCollaborator, $currentUserOtherFolder);

        $this->loginUser($currentUser);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $suspendedCollaborator->public_id->present());

        $this->fetchSuspendCollaboratorResponse(['folder_id' => $currentUserOtherFolder->public_id->present(), 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $otherSuspendedCollaborator->public_id->present());
    }

    #[Test]
    public function whenFolderHasNoSuspendedCollaborators(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $currentUser = UserFactory::new()->create();

        $this->loginUser($currentUser);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorDoesNotHaveSuspendUserPermission(): void
    {
        $collaborator = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $allPermissions = array_column(Permission::cases(), 'value');

        $allPermissionsExceptSuspendUserPermission = collect($allPermissions)
            ->reject(Permission::SUSPEND_USER->value)
            ->tap(fn ($permissions) => $this->assertCount(count($allPermissions) - 1, $permissions))
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $allPermissionsExceptSuspendUserPermission);

        $folder->load('collaborators');

        $this->loginUser($collaborator);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    #[Test]
    public function collaboratorWithPermissionCannotViewSuspendedCollaboratorsWhenFolderOwnerHasDeletedAccount(): void
    {
        $collaboratorWithSuspendUserPermission = UserFactory::new()->create();
        $folder = FolderFactory::new()->create();

        $folder->user->delete();

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->fetchSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

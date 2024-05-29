<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use Tests\TestCase;
use App\Enums\Permission;
use Tests\Traits\CreatesRole;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\AssertValidPaginationData;
use Tests\Traits\GeneratesId;

class FetchFolderRolesTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertValidPaginationData;
    use GeneratesId;

    protected function fetchFolderRolesResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('permissions', $parameters)) {
            $parameters['permissions'] = implode(',', $parameters['permissions']);
        }

        return $this->getJson(route('fetchFolderRoles', $parameters));
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles', 'fetchFolderRoles');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->fetchFolderRolesResponse(['folder_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $this->fetchFolderRolesResponse(['folder_id' => 'baz'])
            ->assertJsonFragment(['message' => 'FolderNotFound'])
            ->assertNotFound();

        $this->fetchFolderRolesResponse(['folder_id' => $id = $this->generateFolderId()->present(), 'name' => str_repeat('F', 65)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->fetchFolderRolesResponse(['permissions' => ['addBookmarks', 'addBookmarks'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0' => 'The permissions.0 field has a duplicate value.']);

        $this->fetchFolderRolesResponse(['permissions' => ['*'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'The selected permissions is invalid.']);

        $this->fetchFolderRolesResponse(['permissions' => ['addBookmarks', 'foo'], 'folder_id' => $id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'The selected permissions is invalid.']);

        $this->assertValidPaginationData($this, 'fetchFolderRoles', ['folder_id' => $id]);
    }

    #[Test]
    public function fetchRoles(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $this->createRole(folder: $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]);
        $role->setAttribute('created_at', $createdAt = now()->subDay())->save();

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonCount(1, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $role->public_id->present())
            ->assertJsonPath('data.0.attributes.name', $role->name)
            ->assertJsonPath('data.0.attributes.created_at', $createdAt->toDateTimeString())
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks'])
            ->assertJsonPath('data.0.attributes.collaborators_count', 0)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'name',
                            'created_at',
                            'permissions',
                            'collaborators_count'
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function whenRoleIsAttachedToCollaborators(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->attachRoleToUser(UserFactory::new()->create(), $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]));

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonPath('data.0.attributes.collaborators_count', 1);
    }

    #[Test]
    public function whenCollaboratorAccountNoLongerExists(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->attachRoleToUser($user = UserFactory::new()->create(), $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]));
        $this->attachRoleToUser(UserFactory::new()->create(), $role);

        $user->delete();

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function filterByName(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $otherUserFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]);
        $this->createRole($role->name, permissions: [Permission::ADD_BOOKMARKS]);
        $this->createRole($role->name, $otherUserFolder, permissions: [Permission::ADD_BOOKMARKS]);

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present(), 'name' => $role->name])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $role->public_id->present());
    }

    #[Test]
    public function filterByPermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder, $userThirdFolder] = FolderFactory::times(3)->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS, Permission::INVITE_USER]);
        $roleWithOnlyAddBookmarksPermission = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]);

        $this->createRole($role->name, permissions: [Permission::ADD_BOOKMARKS]);
        $this->createRole($role->name, $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS]);
        $this->createRole($role->name, $userThirdFolder, permissions: [Permission::INVITE_USER]);

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present(), 'permissions' => ['addBookmarks']])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(1, 'data.0.attributes.permissions')
            ->assertJsonCount(2, 'data.1.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $roleWithOnlyAddBookmarksPermission->public_id->present())
            ->assertJsonPath('data.1.attributes.id', $role->public_id->present());

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present(), 'permissions' => ['inviteUsers']])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $role->public_id->present());

        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present(), 'permissions' => ['addBookmarks', 'inviteUsers']])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $role->public_id->present());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();

        $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->loginUser($user);
        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsFetchRoles(): void
    {
        $collaborator = UserFactory::new()->create();

        $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->fetchFolderRolesResponse(['folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->fetchFolderRolesResponse(['folder_id'  => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

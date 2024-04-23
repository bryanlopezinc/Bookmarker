<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use Tests\TestCase;
use App\Enums\Permission;
use Illuminate\Support\Arr;
use Tests\Traits\CreatesRole;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\GeneratesId;

class RemoveRolePermissionTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use GeneratesId;
    //use InteractsWithValues;

    protected function deleteRolePermissionResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('RemoveRolePermission', Arr::only($parameters, $routeParameters = ['folder_id', 'role_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles/{role_id}/permissions', 'RemoveRolePermission');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteRolePermissionResponse(['folder_id' => 5, 'role_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $routeParameters = [
            'folder_id' => $this->generateFolderId()->present(),
            'role_id'   => $this->generateRoleId()->present()
        ];

        $this->deleteRolePermissionResponse(['folder_id' => 'baz', 'role_id' => 5, 'permission' => 'addBookmarks'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->deleteRolePermissionResponse(['folder_id' => $routeParameters['folder_id'], 'role_id' => 'foo', 'permission' => 'addBookmarks'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->deleteRolePermissionResponse($routeParameters)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The permission field is required.']);

        $this->deleteRolePermissionResponse(['permission' => 'foo', ...$routeParameters])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The selected permission is invalid.']);
    }

    #[Test]
    public function deleteRolePermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $userSecondFolderRole = $this->createRole(folder: $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $this->deleteRolePermissionResponse($query = [
            'permission' => 'removeBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertOk();

        $this->assertEquals(
            $role->refresh()->permissions->pluck('name')->sole(),
            Permission::ADD_BOOKMARKS->value
        );

        $this->assertEqualsCanonicalizing(
            $userSecondFolderRole->refresh()->permissions->pluck('name')->all(),
            [Permission::ADD_BOOKMARKS->value, Permission::DELETE_BOOKMARKS->value]
        );
    }

    #[Test]
    public function roleMustAlwaysHaveAtLeastOnePermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: [Permission::DELETE_BOOKMARKS]);

        $this->deleteRolePermissionResponse([
            'permission' => 'removeBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertBadRequest()->assertJsonFragment(['message' => 'CannotRemoveAllRolePermissions']);

        $this->assertEqualsCanonicalizing(
            $role->refresh()->permissions->pluck('name')->sole(),
            Permission::DELETE_BOOKMARKS->value
        );
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExists(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $role->delete();

        $this->deleteRolePermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleIsNotAttachedToFolderOrDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();

        $userFolderRole = $this->createRole(folder: $folder = FolderFactory::new()->for($user)->create(), permissions: Permission::INVITE_USER);

        $anotherUserFolderRole = $this->createRole(permissions: Permission::INVITE_USER);

        $this->loginUser($user);
        $this->deleteRolePermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => FolderFactory::new()->for($user)->create()->public_id->present(),
            'role_id'    => $userFolderRole->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->deleteRolePermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $anotherUserFolderRole->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->assertEquals(
            $userFolderRole->permissions->sole()->name,
            Permission::INVITE_USER->value
        );

        $this->assertEquals(
            $anotherUserFolderRole->permissions->sole()->name,
            Permission::INVITE_USER->value
        );
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->loginUser($user);
        $this->deleteRolePermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id' => $folder->public_id->present(),
            'role_id'   => $role->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals(
            $role->permissions->sole()->name,
            Permission::INVITE_USER->value
        );
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsAddingRole(): void
    {
        $collaborator = UserFactory::new()->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->deleteRolePermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id' => $folder->public_id->present(),
            'role_id'   => $role->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals(
            $role->permissions->sole()->name,
            Permission::INVITE_USER->value
        );
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $role = $this->createRole();

        $role->delete();

        $this->loginUser(UserFactory::new()->create());
        $this->deleteRolePermissionResponse([
            'role_id'    => $role->public_id->present(),
            'permission' => 'addBookmarks',
            'folder_id'  => $this->generateFolderId()->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

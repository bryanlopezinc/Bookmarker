<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use App\Enums\Permission;
use App\UAC;
use Closure;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class AddPermissionToRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use InteractsWithValues;
    use GeneratesId;

    protected function shouldBeInteractedWith(): mixed
    {
        return UAC::validExternalIdentifiers();
    }

    protected function addPermissionResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('AddPermissionToRole', Arr::only($parameters, $routeParameters = ['folder_id', 'role_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles/{role_id}/permissions', 'AddPermissionToRole');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->addPermissionResponse(['folder_id' => 5, 'role_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $routeParameters = ['folder_id' => $this->generateFolderId()->present(), 'role_id' => $this->generateRoleId()->present()];

        $this->addPermissionResponse([
            'folder_id' => 'baz',
            'role_id' => 5,
            'permission' => 'removeBookmarks',
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->addPermissionResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'role_id'   => 5,
            'permission' => 'removeBookmarks',
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->addPermissionResponse($routeParameters)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The permission field is required.']);

        $this->addPermissionResponse(['permission' => 'foo', ...$routeParameters])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The selected permission is invalid.']);
    }

    #[Test]
    public function rolesCanHaveSamePermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $this->createRole(folder: $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->addPermissionResponse([
            'permission' => 'removeBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertCreated();

        $permissions = $role->refresh()->accessControls();

        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->has(Permission::ADD_BOOKMARKS));
        $this->assertTrue($permissions->has(Permission::DELETE_BOOKMARKS));
    }

    #[Test]
    #[DataProvider('addPermissionsData')]
    public function addPermissions(string $permissions, Closure $expectation = null): void
    {
        $expectation = $expectation ??= fn () => null;

        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder);

        $this->addPermissionResponse([
            'permission' => $permissions,
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(
            $role->refresh()->permissions->pluck('name')->all(),
            UAC::fromRequest(explode(',', $permissions))->toArray()
        );
    }

    public static function addPermissionsData(): array
    {
        return  [
            'Add bookmarks'             => ['addBookmarks'],
            'Remove bookmarks'          => ['removeBookmarks'],
            'Invite users'              => ['inviteUsers'],
            'Remove Collaborator'       => ['removeUser'],
            'Update folder name'        => ['updateFolderName'],
            'Update folder description' => ['updateFolderDescription'],
            'Update folder icon'        => ['updateFolderIcon'],
            'Suspend User'              => ['suspendUser']
        ];
    }

    #[Test]
    public function willReturnConflictWhenRoleAlreadyHasPermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertConflict()->assertJsonFragment(['message' => 'PermissionAlreadyAttachedToRole']);

        $this->assertCount(1, $role->permissions);

        $this->assertEquals(
            $role->permissions->pluck('name')->sole(),
            Permission::ADD_BOOKMARKS->value
        );
    }

    #[Test]
    public function willReturnConflictWhenARoleWithSamePermissionsExistsForSameFolder(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS, Permission::INVITE_USER]);

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS]);

        $this->addPermissionResponse([
            'permission' => 'inviteUsers',
            'folder_id'  => $folder->public_id->present(),
            'role_id'    => $role->public_id->present()
        ])->assertConflict()->assertJsonFragment(['message' => 'DuplicateRole']);

        $this->assertCount(1, $role->permissions);

        $this->assertEquals(
            $role->permissions->pluck('name')->sole(),
            Permission::ADD_BOOKMARKS->value
        );
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExists(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $role->delete();

        $this->addPermissionResponse([
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
        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => FolderFactory::new()->for($user)->create()->public_id->present(),
            'role_id'    => $userFolderRole->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->addPermissionResponse([
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
        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id' => $folder->public_id->present(),
            'role_id'   => $role->public_id->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals(
            $role->permissions->sole()->name,
            Permission::INVITE_USER->name
        );
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsAddingRole(): void
    {
        $collaborator = UserFactory::new()->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->addPermissionResponse([
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
        $this->loginUser(UserFactory::new()->create());
        $this->addPermissionResponse([
            'role_id'    => $this->createRole()->public_id->present(),
            'permission' => 'addBookmarks',
            'folder_id'  => $this->generateFolderId()->present()
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

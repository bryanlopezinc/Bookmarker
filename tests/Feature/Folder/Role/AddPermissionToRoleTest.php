<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use App\Enums\Permission;
use App\Repositories\Folder\PermissionRepository;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class AddPermissionToRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;

    private PermissionRepository $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = new PermissionRepository();
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

        $routeParameters = ['folder_id' => $this->faker->randomDigitNotZero(), 'role_id' => $this->faker->randomDigitNotZero()];

        $this->addPermissionResponse(['folder_id' => 'baz', 'role_id' => 5])->assertNotFound();
        $this->addPermissionResponse(['folder_id' => 9, 'role_id' => 'foo'])->assertNotFound();

        $this->addPermissionResponse($routeParameters)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The permission field is required.']);

        $this->addPermissionResponse(['permission' => 'foo', ...$routeParameters])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission' => 'The selected permission is invalid.']);
    }

    #[Test]
    public function addPermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $this->createRole(folder: $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        //assert different folders can have exact role permissions
        $this->addPermissionResponse([
            'permission' => 'removeBookmarks',
            'folder_id'  => $folder->id,
            'role_id'    => $role->id
        ])->assertCreated();

        $this->assertCount(2, $role->refresh()->permissions);

        $this->assertEqualsCanonicalizing(
            $role->permissions->pluck('permission_id')->all(),
            $this->permissions->findManyByName([Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS])->pluck('id')->all()
        );
    }

    #[Test]
    public function willReturnConflictWhenRoleAlreadyHasPermission(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->id,
            'role_id'    => $role->id
        ])->assertConflict()->assertJsonFragment(['message' => 'PermissionAlreadyAttachedToRole']);

        $this->assertCount(1, $role->permissions);

        $this->assertEquals(
            $role->permissions->pluck('permission_id')->sole(),
            $this->permissions->findByName(Permission::ADD_BOOKMARKS)->id
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
            'folder_id'  => $folder->id,
            'role_id'    => $role->id
        ])->assertConflict()->assertJsonFragment(['message' => 'DuplicateRole']);

        $this->assertCount(1, $role->permissions);

        $this->assertEquals(
            $role->permissions->pluck('permission_id')->sole(),
            $this->permissions->findByName(Permission::ADD_BOOKMARKS)->id
        );
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExists(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->id,
            'role_id'    => $role->id + 1
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
            'folder_id'  => FolderFactory::new()->for($user)->create()->id,
            'role_id'    => $userFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->addPermissionResponse([
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->id,
            'role_id'    => $anotherUserFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->assertEquals(
            $userFolderRole->permissions->sole()->permission_id,
            $this->permissions->findByName(Permission::INVITE_USER)->id
        );

        $this->assertEquals(
            $anotherUserFolderRole->permissions->sole()->permission_id,
            $this->permissions->findByName(Permission::INVITE_USER)->id
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
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals(
            $role->permissions->sole()->permission_id,
            $this->permissions->findByName(Permission::INVITE_USER)->id
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
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals(
            $role->permissions->sole()->permission_id,
            $this->permissions->findByName(Permission::INVITE_USER)->id
        );
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->addPermissionResponse([
            'role_id'    => 3,
            'permission' => 'addBookmarks',
            'folder_id'  => $folder->id + 211
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

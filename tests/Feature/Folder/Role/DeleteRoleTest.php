<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use App\Enums\Permission;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class DeleteRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;

    protected function deleteRoleResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('deleteFolderRole', Arr::only($parameters, $routeParameters = ['folder_id', 'role_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/roles/{role_id}', 'deleteFolderRole');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->deleteRoleResponse(['folder_id' => 5, 'role_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function whenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $this->deleteRoleResponse(['folder_id' => 'baz', 'role_id' => 5])->assertNotFound();
        $this->deleteRoleResponse(['folder_id' => 9, 'role_id' => 'foo'])->assertNotFound();
    }

    #[Test]
    public function deleteRole(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        [$folder, $userSecondFolder] = FolderFactory::times(2)->for($folderOwner)->create();

        $userSecondFolderRole = $this->createRole(folder: $userSecondFolder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $role = $this->createRole(folder: $folder, permissions: [Permission::ADD_BOOKMARKS, Permission::DELETE_BOOKMARKS]);

        $this->deleteRoleResponse([
            'folder_id'  => $folder->id,
            'role_id'    => $role->id
        ])->assertOk();

        $this->assertDatabaseMissing(FolderRole::class, ['id' => $role->id]);
        $this->assertDatabaseMissing(FolderRolePermission::class, ['role_id' => $role->id]);
        $this->assertTrue($userSecondFolderRole->refresh()->exists);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExists(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->deleteRoleResponse([
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
        $this->deleteRoleResponse([
            'folder_id'  => FolderFactory::new()->for($user)->create()->id,
            'role_id'    => $userFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->deleteRoleResponse([
            'folder_id'  => $folder->id,
            'role_id'    => $anotherUserFolderRole->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->assertTrue($userFolderRole->refresh()->exists);
        $this->assertTrue($anotherUserFolderRole->refresh()->exists);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $user = UserFactory::new()->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->loginUser($user);
        $this->deleteRoleResponse([
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($role->refresh()->exists);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsDeletingRole(): void
    {
        $collaborator = UserFactory::new()->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create(), permissions: Permission::INVITE_USER);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaborator);
        $this->deleteRoleResponse([
            'folder_id' => $folder->id,
            'role_id'   => $role->id
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertTrue($role->refresh()->exists);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $folder = FolderFactory::new()->create();

        $this->loginUser(UserFactory::new()->create());
        $this->deleteRoleResponse([
            'role_id'    => 3,
            'folder_id'  => $folder->id + 211
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

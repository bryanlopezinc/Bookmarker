<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Role;

use Tests\TestCase;
use App\Models\User;
use App\Enums\Permission;
use Illuminate\Support\Arr;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use Illuminate\Foundation\Testing\WithFaker;

class RevokeCollaboratorRoleTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use GeneratesId;

    protected function revokeCollaboratorRoleResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('revokeCollaboratorRole', Arr::only($parameters, $routeParameters = ['folder_id', 'collaborator_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function path(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/roles', 'revokeCollaboratorRole');
    }

    #[Test]
    public function unAuthorizedUserCannotAccessRoute(): void
    {
        $this->revokeCollaboratorRoleResponse(['folder_id' => 5, 'collaborator_id' => 4])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->make(['id' => 55]));

        $folderId = $this->generateFolderId()->present();
        $userId = $this->generateUserId()->present();
        $roleIds = $this->generateRoleIds(11)->present();

        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => 'baz',
            'roles'           => $this->generateRoleId()->present(),
            'collaborator_id' => $userId,
        ])->assertNotFound()->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folderId,
            'roles'           => $roleIds->take(2)->add(3)->implode(','),
            'collaborator_id' => $this->generateUserId()->present(),
        ])->assertNotFound()->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folderId,
            'roles'           => $roleIds->first(),
            'collaborator_id' => 44,
        ])->assertNotFound()->assertJsonFragment(['message' => 'UserNotFound']);

        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $this->generateFolderId()->present(),
            'roles'           => $roleIds->take(5)->add($roleIds->first())->implode(','),
            'collaborator_id' => $this->generateUserId()->present(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['roles.0' => 'The roles.0 field has a duplicate value.']);

        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $this->generateFolderId()->present(),
            'roles'           => $roleIds->implode(','),
            'collaborator_id' => $this->generateUserId()->present(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['roles' => 'The roles must not have more than 10 items.']);
    }

    #[Test]
    public function revoke(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder);
        $otherRole = $this->createRole(folder: $folder);

        $this->attachRoleToUser($collaborator, $role);
        $this->attachRoleToUser($collaborator, $otherRole);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertOk();

        $collaboratorRoles = $collaborator->roles;

        $this->assertCount(1, $collaborator->roles);
        $this->assertEquals($collaboratorRoles->sole()->only(['folder_id', 'id']), $otherRole->only(['folder_id', 'id']));
    }

    #[Test]
    public function revokeMultiple(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $roles = [
            $this->createRole(folder: $folder, ),
            $this->createRole(folder: $folder)
        ];

        $this->attachRoleToUser($collaborator, $roles[0]);
        $this->attachRoleToUser($collaborator, $roles[1]);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => implode(',', [$roles[0]->public_id->present(), $roles[1]->public_id->present()]),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertOk();

        $this->assertCount(0, $collaborator->roles);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotHaveRole(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'CollaboratorHasNoSuchRole']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorIsNotACollaboratorInFolder(): void
    {
        /** @var User */
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $user->public_id->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExist(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $role = $this->createRole(folder: $folder, permissions: Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $this->generateUserId()->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotExist(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $this->generateRoleId()->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'RoleNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenRoleDoesNotBelongToUser(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->attachRoleToUser($collaborator, $role = $this->createRole());

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'RoleNotFound']);

        $this->assertCount(1, $collaborator->roles);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        /** @var User */
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create());

        $this->attachRoleToUser($collaborator, $role);

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->revokeCollaboratorRoleResponse([
            'folder_id'       => $folder->public_id->present(),
            'roles'           => $role->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertCount(1, $collaborator->roles);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsRevokingRole(): void
    {
        $collaborators = UserFactory::times(2)->create();

        $role = $this->createRole(folder: $folder = FolderFactory::new()->create());

        $this->CreateCollaborationRecord($collaborators[0], $folder);
        $this->CreateCollaborationRecord($collaborators[1], $folder);

        $this->attachRoleToUser($collaborators[1], $role);

        $this->loginUser($collaborators[0]);
        $this->revokeCollaboratorRoleResponse([
            'collaborator_id' => $collaborators[1]->public_id->present(),
            'folder_id'      => $folder->public_id->present(),
            'roles'          => $role->public_id->present()
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertCount(1, $collaborators[1]->roles);
    }
}

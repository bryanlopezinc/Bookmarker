<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\Models\FolderCollaboratorPermission;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class RevokeCollaboratorPermissionsTest extends TestCase
{
    use CreatesCollaboration;

    private function revokePermissionsResponse(array $parameters = []): TestResponse
    {
        $routeParameters = Arr::except($parameters, 'permissions');

        return $this->deleteJson(
            route('revokePermissions', $routeParameters),
            Arr::only($parameters, ['permissions'])
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/permissions', 'revokePermissions');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->revokePermissionsResponse(['collaborator_id' => 4, 'folder_id' => 4])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->revokePermissionsResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])->assertNotFound();
        $this->revokePermissionsResponse(['folder_id' => 'foo', 'collaborator_id' => 44])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse(['folder_id' => '44', 'collaborator_id' => 44])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The permissions field is required']
            ]);

        $this->revokePermissionsResponse([
            'collaborator_id' => '33',
            'folder_id'   => '33',
            'permissions' => 'hello'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The selected permissions is invalid.']
            ]);

        $this->revokePermissionsResponse(['permissions' => 'addBookmarks,addBookmarks', 'folder_id' => '44', 'collaborator_id' => 44])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => ["The permissions.0 field has a duplicate value."],
                "permissions.1" => ["The permissions.1 field has a duplicate value."]
            ]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id'   =>  FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id'   => FolderFactory::new()->create()->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWhenUserIsACollaboratorButDoesNotOwnFolder(): void
    {
        [$collaborator, $otherCollaborator, $userThatIsNotACollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($otherCollaborator, $folder, Permission::ADD_BOOKMARKS);

        Passport::actingAs($collaborator);
        $this->revokePermissionsResponse([
            'collaborator_id' => $otherCollaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'NoRevokePermissionPermission']);

        $this->revokePermissionsResponse([
            'collaborator_id' => $userThatIsNotACollaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($error);

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'folder_id' => $folder->id,
            'user_id'   => $otherCollaborator->id,
        ]);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id + 1,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnForbiddenWhenPerformingActionOnSelf(): void
    {
        $user = UserFactory::new()->create();

        Passport::actingAs($user);

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => $user->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotRemoveSelf']);
    }

    public function testWillReturnNotFoundWhenCollaboratorDoesNotHavePermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->revokePermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'removeBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserHasNoSuchPermissions']);
    }

    #[Test]
    public function revokeFolderPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $permissionsRepository = new CollaboratorPermissionsRepository();
        $folder = FolderFactory::new()->for($folderOwner)->create();
        $query = ['collaborator_id' => $collaborator->id, 'folder_id' => $folder->id];

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->toArray());
        $this->loginUser($folderOwner);

        $this->revokePermissionsResponse(['permissions' => 'updateFolder', ...$query])->assertOk();
        $permissions = $permissionsRepository->all($collaborator->id, $folder->id);
        $this->assertCount(3, $permissions);
        $this->assertFalse($permissions->canUpdateFolder());

        $this->revokePermissionsResponse(['permissions' => 'addBookmarks', ...$query])->assertOk();
        $permissions = $permissionsRepository->all($collaborator->id, $folder->id);
        $this->assertCount(2, $permissions);
        $this->assertFalse($permissions->canAddBookmarks());

        $this->revokePermissionsResponse(['permissions' => 'removeBookmarks', ...$query])->assertOk();
        $permissions = $permissionsRepository->all($collaborator->id, $folder->id);
        $this->assertCount(1, $permissions);
        $this->assertFalse($permissions->canRemoveBookmarks());

        $this->revokePermissionsResponse(['permissions' => 'inviteUsers', ...$query])->assertOk();
        $permissions = $permissionsRepository->all($collaborator->id, $folder->id);
        $this->assertCount(0, $permissions);
        $this->assertFalse($permissions->canInviteUser());
    }

    public function testRevokeMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, [Permission::ADD_BOOKMARKS, Permission::INVITE_USER]);

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUsers,addBookmarks'
        ])->assertOk();

        $permissions = (new CollaboratorPermissionsRepository())->all($collaborator->id, $folder->id);

        $this->assertFalse($permissions->canAddBookmarks());
        $this->assertFalse($permissions->canInviteUser());
    }

    public function testWillNotAffectOtherCollaboratorsPermissions(): void
    {
        $users = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($users[0])->create();

        $this->CreateCollaborationRecord($users[1], $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($users[2], $folder, Permission::ADD_BOOKMARKS);

        Passport::actingAs($users[0]);
        $this->revokePermissionsResponse([
            'collaborator_id' => $users[1]->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $permissions = (new CollaboratorPermissionsRepository())->all($users[2]->id, $folder->id);

        $this->assertTrue($permissions->isNotEmpty());
    }
}

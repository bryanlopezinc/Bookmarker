<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RevokeCollaboratorPermissionsTest extends TestCase
{
    private function revokePermissionsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('revokePermissions'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/revoke_permissions', 'revokePermissions');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->revokePermissionsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id'     => ['The user id field is required'],
                'folder_id'   => ['The folder id field is required'],
                'permissions' => ['The permissions field is required']
            ]);

        $this->revokePermissionsResponse([
            'user_id'     => 'foo',
            'folder_id'   => 'bar',
            'permissions' => 'hello'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
                'folder_id',
                'permissions' => ['The selected permissions is invalid.']
            ]);

        $this->revokePermissionsResponse(['permissions' => 'addBookmarks,addBookmarks'])
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
            'user_id'     => UserFactory::new()->create()->id,
            'folder_id'   =>  FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'user_id'     => UserFactory::new()->create()->id,
            'folder_id'   => FolderFactory::new()->create()->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWhenUserIsACollaboratorButDoesNotOwnFolder(): void
    {
        $users = UserFactory::times(3)->create();

        $folderID = FolderFactory::new()->create()->id;

        FolderCollaboratorPermissionFactory::new()->user($users[1]->id)->folder($folderID)->create();
        FolderCollaboratorPermissionFactory::new()->user($users[2]->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($users[1]);
        $this->revokePermissionsResponse([
            'user_id'     => $users[2]->id,
            'folder_id'   => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'NoRevokePermissionPermission']);

        $this->revokePermissionsResponse([
            'user_id'     => $users[0]->id,
            'folder_id'   => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($error);

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'folder_id' => $folderID,
            'user_id'   => $users[2]->id,
        ]);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'user_id' => UserFactory::new()->create()->id,
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
            'user_id'     => UserFactory::new()->create()->id + 1,
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
            'user_id'     => $user->id,
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
        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id)->addBookmarksPermission()->create();

        $this->revokePermissionsResponse([
            'user_id'     => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'removeBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserHasNoSuchPermissions']);
    }

    public function testRevokeAddBookmarksPermission(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folderID = FolderFactory::new()->for($user)->create()->id;

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folderID)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($user);
        $this->revokePermissionsResponse([
            'user_id'     => $collaborator->id,
            'folder_id'   => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'folder_id' => $folderID,
            'user_id'   => $collaborator->id,
        ]);
    }

    public function testRevokeRemoveBookmarksPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID)->removeBookmarksPermission()->create();

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id'    => $collaborator->id,
            'folder_id'  => $folderID,
            'permissions' => 'removeBookmarks'
        ])->assertOk();
    }

    public function testRevokeInviteUserPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID)->inviteUser()->create();

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'inviteUser'
        ])->assertOk();
    }

    public function testRevokeMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folderID = FolderFactory::new()->for($folderOwner)->create()->id;

        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID)->inviteUser()->create();
        FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($folderOwner);
        $this->revokePermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folderID,
            'permissions' => 'inviteUser,addBookmarks'
        ])->assertOk();
    }

    public function testWillNotRevokeCollaboratorsOtherPermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $folderID = FolderFactory::new()->for($user)->create()->id;

        $factory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folderID);

        $factory->addBookmarksPermission()->create();
        $factory->inviteUser()->create();
        $factory->removeBookmarksPermission()->create();

        Passport::actingAs($user);
        $this->revokePermissionsResponse([
            'user_id'     => $collaborator->id,
            'folder_id'   => $folderID,
            'permissions' => 'addBookmarks,inviteUser'
        ])->assertOk();

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'folder_id' => $folderID,
            'user_id' => $collaborator->id,
            'permission_id' => FolderPermission::query()->where('name', FolderPermission::DELETE_BOOKMARKS)->sole()->id
        ]);
    }

    public function testWillNotAffectOtherCollaboratorsPermissions(): void
    {
        $users = UserFactory::times(3)->create();

        $folderID = FolderFactory::new()->for($users[0])->create()->id;

        FolderCollaboratorPermissionFactory::new()->user($users[1]->id)->folder($folderID)->addBookmarksPermission()->create();
        FolderCollaboratorPermissionFactory::new()->user($users[2]->id)->folder($folderID)->addBookmarksPermission()->create();

        Passport::actingAs($users[0]);
        $this->revokePermissionsResponse([
            'user_id'     => $users[1]->id,
            'folder_id'   => $folderID,
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $this->assertDatabaseHas(FolderCollaboratorPermission::class, [
            'folder_id' => $folderID,
            'user_id' => $users[2]->id,
        ]);
    }
}

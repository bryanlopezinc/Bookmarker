<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\Repositories\Folder\CollaboratorPermissionsRepository as Repository;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class GrantFolderPermissionToCollaboratorTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;

    protected function grantPermissionsResponse(array $parameters = []): TestResponse
    {
        $routeParameters = Arr::except($parameters, 'permissions');

        return $this->patchJson(
            route('grantPermission', $routeParameters),
            Arr::only($parameters, ['permissions'])
        );
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/permissions', 'grantPermission');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->grantPermissionsResponse(['collaborator_id' => 4, 'folder_id' => 4])->assertUnauthorized();
    }


    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->grantPermissionsResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])->assertNotFound();
        $this->grantPermissionsResponse(['folder_id' => 'foo', 'collaborator_id' => 44])->assertNotFound();
    }


    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse(['folder_id' => 44, 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The permissions field is required.']
            ]);

        $this->grantPermissionsResponse(['permissions' => 'foo,bar', 'folder_id' => 44, 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The selected permissions is invalid.']]);

        $this->grantPermissionsResponse(['permissions' => 'addBookmarks,addBookmarks,inviteUsers','folder_id' => 44, 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => [
                    "The permissions.0 field has a duplicate value."
                ],
                "permissions.1" => [
                    "The permissions.1 field has a duplicate value."
                ]
            ]);
    }

    public function testGrantPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'inviteUsers'
        ])->assertOk();

        $collaboratorPermissions = (new Repository())->all($collaborator->id, $folder->id);

        $this->assertTrue($collaboratorPermissions->canInviteUser());

        $this->assertEquals($collaboratorPermissions->count(), 1);
    }

    public function testGrantMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'inviteUsers,addBookmarks'
        ])->assertOk();

        $collaboratorPermissions = (new Repository())->all($collaborator->id, $folder->id);

        $this->assertTrue($collaboratorPermissions->canInviteUser());
        $this->assertTrue($collaboratorPermissions->canAddBookmarks());
        $this->assertEquals($collaboratorPermissions->count(), 2);
    }

    public function testWillReturnForbiddenWhenGrantingPermissionToSelf(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->grantPermissionsResponse([
            'collaborator_id' => $user->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUsers'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotGrantPermissionsToSelf']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id'   => FolderFactory::new()->create()->id,
            'permissions' => 'inviteUsers'
        ])->assertNotFound();
    }

    public function testWillReturnConflictWhenCollaboratorAlreadyHasPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson(['message' => 'DuplicatePermissions']);
    }

    public function testWillReturnNotFoundWhenUserIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExist(): void
    {
        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->id + 1,
            'folder_id'   => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id'   => FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }
}

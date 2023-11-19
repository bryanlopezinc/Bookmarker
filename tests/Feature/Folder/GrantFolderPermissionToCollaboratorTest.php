<?php

namespace Tests\Feature\Folder;

use App\Repositories\Folder\FolderPermissionsRepository as Repository;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GrantFolderPermissionToCollaboratorTest extends TestCase
{
    use WithFaker;

    protected function grantPermissionsResponse(array $parameters = []): TestResponse
    {
        return $this->patchJson(route('grantPermission', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/permissions', 'grantPermission');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->grantPermissionsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'folder_id' => ['The folder id field is required.'],
                'user_id' => ['The user id field is required.'],
                'permissions' => ['The permissions field is required.']
            ]);

        $this->grantPermissionsResponse(['permissions' => 'foo,bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The selected permissions is invalid.']]);

        $this->grantPermissionsResponse(['permissions' => 'addBookmarks,addBookmarks,inviteUser'])
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

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->viewBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id'     => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'inviteUser'
        ])->assertOk();

        $collaboratorPermissions = (new Repository)->getUserAccessControls($collaborator->id, $folder->id);

        $this->assertTrue($collaboratorPermissions->canInviteUser());

        $this->assertEquals($collaboratorPermissions->count(), 2);
    }

    public function testGrantMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->viewBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id'     => $collaborator->id,
            'folder_id'   => $folder->id,
            'permissions' => 'inviteUser,addBookmarks'
        ])->assertOk();

        $collaboratorPermissions = (new Repository)->getUserAccessControls($collaborator->id, $folder->id);

        $this->assertTrue($collaboratorPermissions->canInviteUser());
        $this->assertTrue($collaboratorPermissions->canAddBookmarks());
        $this->assertEquals($collaboratorPermissions->count(), 3);
    }

    public function testWillReturnForbiddenWhenGrantingPermissionToSelf(): void
    {
        $user = UserFactory::new()->create();
        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->grantPermissionsResponse([
            'user_id' => $user->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUser'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotGrantPermissionsToSelf']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse([
            'user_id'     => UserFactory::new()->create()->id,
            'folder_id'   => FolderFactory::new()->create()->id,
            'permissions' => 'inviteUser'
        ])->assertNotFound();
    }

    public function testWillReturnConflictWhenCollaboratorAlreadyHasPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id'     => $collaborator->id,
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
            'user_id'     => $collaborator->id,
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
            'user_id'     => UserFactory::new()->create()->id + 1,
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
            'user_id'     => $collaborator->id,
            'folder_id'   => FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }
}

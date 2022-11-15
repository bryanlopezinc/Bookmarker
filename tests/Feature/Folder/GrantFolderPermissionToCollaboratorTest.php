<?php

namespace Tests\Feature\Folder;

use App\Repositories\Folder\FolderPermissionsRepository as Repository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Database\Factories\FolderAccessFactory;
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
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators/grant', 'grantPermission');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->grantPermissionsResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'folder_id' => ['The folder id field is required.'],
                'user_id' => ['The user id field is required.'],
                'permissions' => ['The permissions field is required.']
            ]);
    }

    public function testPermissionsAttributesMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse(['permissions' => 'foo,bar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The selected permissions is invalid.']
            ]);
    }

    public function testPermissionsAttributesMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

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

    public function testWillGrantPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        //assert cannot send invite
        Passport::actingAs($collaborator);
        $this->postJson(route('sendFolderCollaborationInvite'), $sendInviteRequest = [
            'email' => UserFactory::new()->create()->email,
            'folder_id' => $folder->id,
        ])->assertForbidden();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUser'
        ])->assertOk();

        //can now perform action.
        Passport::actingAs($collaborator);
        $this->postJson(route('sendFolderCollaborationInvite'), $sendInviteRequest)->assertOk();

        $collaboratorPermissions = (new Repository)->getUserAccessControls(
            new UserID($collaborator->id),
            new ResourceID($folder->id)
        );

        $this->assertTrue($collaboratorPermissions->canInviteUser());
        $this->assertTrue($collaboratorPermissions->canAddBookmarks());
        $this->assertFalse($collaboratorPermissions->canRemoveBookmarks());
    }

    public function testCanGrantMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->viewBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUser,addBookmarks'
        ])->assertOk();

        $collaboratorPermissions = (new Repository)->getUserAccessControls(
            new UserID($collaborator->id),
            new ResourceID($folder->id)
        );

        $this->assertTrue($collaboratorPermissions->canInviteUser());
        $this->assertTrue($collaboratorPermissions->canAddBookmarks());
        $this->assertFalse($collaboratorPermissions->canRemoveBookmarks());
    }

    public function testCannotPerformActionOnSelf(): void
    {
        $self = UserFactory::new()->create();
        $folder = FolderFactory::new()->create(['user_id' => $self->id]);

        Passport::actingAs($self);
        $this->grantPermissionsResponse([
            'user_id' => $self->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUser'
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Cannot grant permissions to self'
            ]);
    }

    public function testFolderMustBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->grantPermissionsResponse([
            'user_id' => UserFactory::new()->create()->id,
            'folder_id' => FolderFactory::new()->create()->id,
            'permissions' => 'inviteUser'
        ])->assertForbidden();
    }

    public function testWhenCollaboratorAlreadyHasPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'user already has permissions'
            ]);

        //when collaborator has at least one permission in the given permissions
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'inviteUser,addBookmarks'
        ])->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson([
                'message' => 'user already has permissions'
            ]);
    }

    public function testWhenUserIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenCollaboratorIsNotARegisteredUser(): void
    {
        $folderOwner = UserFactory::new()->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => UserFactory::new()->create()->id + 1,
            'folder_id' => $folder->id,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'User not a collaborator'
            ]);
    }

    public function testWhenFolderDoesNotExist(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => FolderFactory::new()->create()->id + 1,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'The folder does not exists'
            ]);
    }

    public function testCanGrantUpdateFolderPermission(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        Passport::actingAs($folderOwner);
        $this->grantPermissionsResponse([
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id,
            'permissions' => 'updateFolder'
        ])->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\UAC;
use Tests\TestCase;
use App\Enums\Permission;
use Illuminate\Support\Arr;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;
use App\Models\FolderCollaboratorPermission;
use Tests\Feature\Folder\Concerns\InteractsWithValues;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Traits\GeneratesId;

class RevokeCollaboratorPermissionsTest extends TestCase
{
    use CreatesCollaboration;
    use InteractsWithValues;
    use GeneratesId;

    protected function shouldBeInteractedWith(): array
    {
        return UAC::validExternalIdentifiers();
    }

    private function revokePermissionsResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('revokePermissions', Arr::except($parameters, 'permissions')),
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
        $this->loginUser(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'folder_id' => 44,
            'collaborator_id' => $this->generateUserId()->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->revokePermissionsResponse([
            'folder_id' =>  $this->generateFolderId()->present(),
            'collaborator_id' => 44,
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotFound']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->revokePermissionsResponse($query = [
            'folder_id'       => $this->generateFolderId()->present(),
            'collaborator_id' => $this->generateBookmarkId()->present()
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => ['The permissions field is required']]);

        $this->revokePermissionsResponse(['permissions' => 'hello', ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions' => ['The selected permissions is invalid.']
            ]);

        $this->revokePermissionsResponse(['permissions' => 'addBookmarks,addBookmarks', ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => ["The permissions.0 field has a duplicate value."],
                "permissions.1" => ["The permissions.1 field has a duplicate value."]
            ]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'   =>  $this->generateFolderId()->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'   => FolderFactory::new()->create()->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnForbiddenWhenUserIsACollaboratorButDoesNotOwnFolder(): void
    {
        [$collaborator, $otherCollaborator, $userThatIsNotACollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($otherCollaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($collaborator);
        $this->revokePermissionsResponse([
            'collaborator_id' => $otherCollaborator->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson($error = ['message' => 'NoRevokePermissionPermission']);

        $this->revokePermissionsResponse([
            'collaborator_id' => $userThatIsNotACollaborator->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
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
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id' => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => $this->generateUserId()->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnForbiddenWhenPerformingActionOnSelf(): void
    {
        $user = UserFactory::new()->create();

        $this->loginUser($user);

        $folder = FolderFactory::new()->for($user)->create();

        $this->revokePermissionsResponse([
            'collaborator_id' => $user->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertForbidden()
            ->assertExactJson(['message' => 'CannotRemoveSelf']);
    }

    public function testWillReturnNotFoundWhenCollaboratorDoesNotHavePermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->revokePermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'removeBookmarks'
        ])->assertNotFound()
            ->assertExactJson(['message' => 'UserHasNoSuchPermissions']);
    }

    #[Test]
    #[DataProvider('revokeFolderPermissionData')]
    public function revokeFolderPermission(string $permission): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->toArray());

        $this->loginUser($folderOwner);
        $this->revokePermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
            'permissions'     => $permission
        ])->assertOk();

        $collaboratorPermissions = (new CollaboratorPermissionsRepository())->all($collaborator->id, $folder->id);

        $this->assertFalse(
            $collaboratorPermissions->has(UAC::fromRequest($permission)->toCollection()->sole())
        );

        $this->assertCount(7, $collaboratorPermissions);
    }

    public static function revokeFolderPermissionData(): array
    {
        return  [
            'Add bookmarks'             => ['addBookmarks'],
            'Remove bookmarks'          => ['removeBookmarks'],
            'Remove Collaborator'       => ['removeUser'],
            'Invite users'              => ['inviteUsers'],
            'Update folder name'        => ['updateFolderName'],
            'Update folder description' => ['updateFolderDescription'],
            'Update folder icon'        => ['updateFolderIcon'],
            'Suspend User'              => ['suspendUser']
        ];
    }

    public function testRevokeMultiplePermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, [Permission::ADD_BOOKMARKS, Permission::INVITE_USER]);

        $this->loginUser($folderOwner);
        $this->revokePermissionsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id' => $folder->public_id->present(),
            'permissions' => 'inviteUsers,addBookmarks'
        ])->assertOk();

        $permissions = (new CollaboratorPermissionsRepository())->all($collaborator->id, $folder->id);

        $this->assertFalse($permissions->has(Permission::ADD_BOOKMARKS));
        $this->assertFalse($permissions->has(Permission::INVITE_USER));
    }

    public function testWillNotAffectOtherCollaboratorsPermissions(): void
    {
        $users = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($users[0])->create();

        $this->CreateCollaborationRecord($users[1], $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($users[2], $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($users[0]);
        $this->revokePermissionsResponse([
            'collaborator_id' => $users[1]->public_id->present(),
            'folder_id'   => $folder->public_id->present(),
            'permissions' => 'addBookmarks'
        ])->assertOk();

        $permissions = (new CollaboratorPermissionsRepository())->all($users[2]->id, $folder->id);

        $this->assertTrue($permissions->isNotEmpty());
    }
}

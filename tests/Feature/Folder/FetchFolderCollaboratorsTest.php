<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\Filesystem\ProfileImageFileSystem;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse as Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class FetchFolderCollaboratorsTest extends TestCase
{
    use AssertValidPaginationData, CreatesCollaboration;

    protected function fetchCollaboratorsResponse(array $parameters = []): Response
    {
        return $this->getJson(route('fetchFolderCollaborators', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators', 'fetchFolderCollaborators');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchCollaboratorsResponse(['folder_id' => 44])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        $this->fetchCollaboratorsResponse(['folder_id' => 'foo'])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchFolderCollaborators', ['folder_id' => 44]);

        $this->fetchCollaboratorsResponse(['permissions' => 'foo', 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->fetchCollaboratorsResponse(['permissions' => 'addBookmarks,addBookmarks', 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => [
                    "The permissions.0 field has a duplicate value."
                ],
                "permissions.1" => [
                    "The permissions.1 field has a duplicate value."
                ]
            ]);

        $this->fetchCollaboratorsResponse(['folder_id' => 4, 'permissions' => 'readOnly,addBookmarks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'Cannot request collaborator with only view permissions with any other permission']);

        $this->fetchCollaboratorsResponse(['name' => str_repeat('A', 11), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.']);
    }

    #[Test]
    public function whenCollaboratorWasAddedByAuthUser(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();
        $collaborator = UserFactory::new()->hasProfileImage()->create();

        $this->CreateCollaborationRecord($collaborator, $userFolder, UAC::all()->toArray());

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(5, 'data.0.attributes')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonCount(2, 'data.0.attributes.added_by')
            ->assertJsonPath('data.0.attributes.profile_image_url', (new ProfileImageFileSystem)->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.added_by.exists', true)
            ->assertJsonPath('data.0.attributes.added_by.is_auth_user', true)
            ->assertJsonPath('data.0.type', 'folderCollaborator')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id)
            ->assertJsonPath('data.0.attributes.name', $collaborator->first_name . ' ' . $collaborator->last_name)
            ->assertJsonPath('data.0.attributes.permissions', UAC::all()->toJsonResponse())
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'name',
                            'permissions',
                            'profile_image_url',
                            'added_by' => [
                                'exists',
                                'is_auth_user',
                            ]
                        ]
                    ],
                ]
            ]);
    }

    #[Test]
    public function whenCollaboratorWasNotAddedByAuthUser(): void
    {
        [$folderOwner, $invitee] = UserFactory::times(2)->create();

        $collaborator = UserFactory::new()->hasProfileImage()->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($invitee, $folder, inviter: $collaborator->id);

        $this->loginUser($folderOwner);
        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.attributes.added_by')
            ->assertJsonCount(3, 'data.0.attributes.added_by.user')
            ->assertJsonPath('data.0.attributes.added_by.user.profile_image_url', (new ProfileImageFileSystem)->publicUrl($collaborator->profile_image_path))
            ->assertJsonPath('data.0.attributes.added_by.exists', true)
            ->assertJsonPath('data.0.attributes.added_by.is_auth_user', false)
            ->assertJsonPath('data.0.attributes.added_by.user.id', $collaborator->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'attributes' => [
                            'added_by' => [
                                'exists',
                                'is_auth_user',
                                'user' => [
                                    'id',
                                    'name',
                                    'profile_image_url'
                                ]
                            ]
                        ]
                    ],
                ]
            ]);
    }

    #[Test]
    public function whenInviterNoLongerExists(): void
    {
        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($invitee, $folder, [], $collaborator->id);

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes.added_by')
            ->assertJsonPath('data.0.attributes.added_by.exists', false)
            ->assertJsonPath('data.0.attributes.added_by.is_auth_user', false)
            ->assertJsonCount(2, 'data.0.attributes.added_by');
    }

    #[Test]
    public function willOrderResultByLatest(): void
    {
        $this->loginUser($folderOwner = UserFactory::new()->create());

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $collaborators = UserFactory::times(3)->create();

        $this->CreateCollaborationRecord($collaborators[0], $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[1], $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[2], $folder, Permission::ADD_BOOKMARKS);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[2]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[1]->id)
            ->assertJsonPath('data.2.attributes.id', $collaborators[0]->id);
    }

    public function testWillReturnOnlyCollaboratorsWithSpecifiedName(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(3)
            ->sequence(
                ['first_name' => 'Bryan'],
                ['first_name' => 'Bryan'],
                ['first_name' => 'Jack']
            )
            ->create();

        $folder = FolderFactory::new()->for($user)->create();
        $otherFolder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborators[0], $folder, Permission::INVITE_USER);
        $this->CreateCollaborationRecord($collaborators[1], $otherFolder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[2], $folder, Permission::INVITE_USER);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id);
    }

    public function testWillReturnOnlyCollaboratorsWithSpecifiedPermissions(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        [
            $hasAllPermissions,
            $hasOnlyAddBookmarksPermission,
            $hasOnlyReadPermission,
            $hasOnlyInviteUserPermission,
            $hasOnlyDeletePermission,
            $hasOnlyUpdatePermission,
            $hasInviteAndAddBookmarksPermission
        ] = UserFactory::times(7)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($hasAllPermissions, $folder, UAC::all()->toArray());
        $this->CreateCollaborationRecord($hasOnlyAddBookmarksPermission, $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($hasOnlyReadPermission, $folder);
        $this->CreateCollaborationRecord($hasOnlyInviteUserPermission, $folder, Permission::INVITE_USER);
        $this->CreateCollaborationRecord($hasOnlyDeletePermission, $folder, Permission::DELETE_BOOKMARKS);
        $this->CreateCollaborationRecord($hasOnlyUpdatePermission, $folder, Permission::UPDATE_FOLDER);
        $this->CreateCollaborationRecord($hasInviteAndAddBookmarksPermission, $folder, [Permission::INVITE_USER, Permission::ADD_BOOKMARKS]);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'addBookmarks,inviteUser'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $hasInviteAndAddBookmarksPermission->id)
            ->assertJsonPath('data.1.attributes.id', $hasAllPermissions->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'addBookmarks'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $hasInviteAndAddBookmarksPermission->id)
            ->assertJsonPath('data.1.attributes.id', $hasOnlyAddBookmarksPermission->id)
            ->assertJsonPath('data.2.attributes.id', $hasAllPermissions->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'inviteUser'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $hasInviteAndAddBookmarksPermission->id)
            ->assertJsonPath('data.1.attributes.id', $hasOnlyInviteUserPermission->id)
            ->assertJsonPath('data.2.attributes.id', $hasAllPermissions->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'updateFolder'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $hasOnlyUpdatePermission->id)
            ->assertJsonPath('data.1.attributes.id', $hasAllPermissions->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'removeBookmarks'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $hasOnlyDeletePermission->id)
            ->assertJsonPath('data.1.attributes.id', $hasAllPermissions->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'readOnly'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $hasOnlyReadPermission->id);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchCollaboratorsResponse(['folder_id' => FolderFactory::new()->create()->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id + 1])->assertNotFound();
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoCollaborators(): void
    {
        $this->loginUser($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUserAccountsInResponse(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $collaborator->delete();

        $this->loginUser($user);
        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnOnlyCollaboratorPermissionsForFolder(): void
    {
        $users = UserFactory::new()->count(3)->create();

        $firstFolder = FolderFactory::new()->for($users[0])->create();
        $secondFolder = FolderFactory::new()->for($users[1])->create();

        $this->CreateCollaborationRecord($users[2], $firstFolder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($users[2], $secondFolder, Permission::ADD_BOOKMARKS);

        $this->loginUser($users[0]);
        $this->fetchCollaboratorsResponse(['folder_id' => $firstFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $users[2]->id);
    }
}

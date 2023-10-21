<?php

namespace Tests\Feature\Folder;

use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use App\Models\User;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse as Response;
use Laravel\Passport\Passport;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;

class FetchFolderCollaboratorsTest extends TestCase
{
    use AssertValidPaginationData;

    protected function fetchCollaboratorsResponse(array $parameters = []): Response
    {
        return $this->getJson(route('fetchFolderCollaborators', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/collaborators', 'fetchFolderCollaborators');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchCollaboratorsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchFolderCollaborators');

        $this->fetchCollaboratorsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);

        $this->fetchCollaboratorsResponse(['permissions' => 'foo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);

        $this->fetchCollaboratorsResponse(['permissions' => 'addBookmarks,addBookmarks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "permissions.0" => [
                    "The permissions.0 field has a duplicate value."
                ],
                "permissions.1" => [
                    "The permissions.1 field has a duplicate value."
                ]
            ]);

        //Assert cannot request collaborators with view_only permissions and any other permission
        $this->fetchCollaboratorsResponse([
            'folder_id' => 4,
            'permissions' => 'readOnly,addBookmarks'
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'Cannot request collaborator with only view permissions with any other permission']);
    }

    public function testFetchCollaborators(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();
        $collaborator = UserFactory::new()->create();

        $this->createUserFolderAccess($collaborator, $userFolder->id, FolderPermission::all(['name'])->pluck('name')->all());

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.attributes')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.type', 'folderCollaborator')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'first_name',
                            'last_name',
                            'permissions'
                        ]
                    ],
                ]
            ]);
    }

    public function testWillReturnOnlyCollaboratorsWithSpecifiedPermissions(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(7)->create();
        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->createUserFolderAccess($collaborators[0], $folderID, FolderPermission::all(['name'])->pluck('name')->all());
        $this->createUserFolderAccess($collaborators[1], $folderID, FolderPermission::ADD_BOOKMARKS);
        $this->createUserFolderAccess($collaborators[2], $folderID, FolderPermission::VIEW_BOOKMARKS);
        $this->createUserFolderAccess($collaborators[3], $folderID, FolderPermission::INVITE);
        $this->createUserFolderAccess($collaborators[4], $folderID, FolderPermission::DELETE_BOOKMARKS);
        $this->createUserFolderAccess($collaborators[5], $folderID, FolderPermission::UPDATE_FOLDER);
        $this->createUserFolderAccess($collaborators[6], $folderID, [FolderPermission::INVITE, FolderPermission::ADD_BOOKMARKS]);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'addBookmarks,inviteUser'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[6]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'addBookmarks'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[1]->id)
            ->assertJsonPath('data.2.attributes.id', $collaborators[6]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'inviteUser'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[3]->id)
            ->assertJsonPath('data.2.attributes.id', $collaborators[6]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'updateFolder'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[5]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'removeBookmarks'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[4]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folderID, 'permissions' => 'readOnly'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[2]->id);
    }

    private function createUserFolderAccess(User $collaborator, int $folderID, string|array $permission): void
    {
        $repository = new FolderPermissionsRepository;

        $repository->create($collaborator->id, $folderID, new UAC((array)$permission));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchCollaboratorsResponse(['folder_id' => FolderFactory::new()->create()->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id + 1])->assertNotFound();
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoCollaborators(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUserAccountsInResponse(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->createUserFolderAccess($collaborator, $folder->id, FolderPermission::ADD_BOOKMARKS);

        $collaborator->delete();

        Passport::actingAs($user);
        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnOnlyCollaboratorPermissionsForFolder(): void
    {
        $users = UserFactory::new()->count(3)->create();

        $firstFolder = FolderFactory::new()->for($users[0])->create()->id;
        $secondFolder = FolderFactory::new()->for($users[1])->create()->id;

        $this->createUserFolderAccess($users[2], $firstFolder, FolderPermission::ADD_BOOKMARKS);
        $this->createUserFolderAccess($users[2], $secondFolder, FolderPermission::ADD_BOOKMARKS);

        Passport::actingAs($users[0]);
        $this->fetchCollaboratorsResponse(['folder_id' => $firstFolder])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $users[2]->id);
    }
}

<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse as Response;
use Laravel\Passport\Passport;
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
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'Cannot request collaborator with only view permissions with any other permission']);

        $this->fetchCollaboratorsResponse(['name' => str_repeat('A', 11), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.']);
    }

    public function testFetchCollaborators(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $userFolder = FolderFactory::new()->for($user)->create();
        $collaborator = UserFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $userFolder, UAC::all()->toArray());

        $this->fetchCollaboratorsResponse(['folder_id' => $userFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
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
                            'permissions'
                        ]
                    ],
                ]
            ]);
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
        Passport::actingAs($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(3)
            ->sequence(
                ['first_name' => 'Bryan'],
                ['first_name' => 'Bryan'],
                ['first_name' => 'Jack']
            )
            ->create();

        $folder = FolderFactory::new()->for($user)->create();
        $otherFolder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborators[0], $folder, Permission::VIEW_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[1], $otherFolder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[2], $folder, Permission::VIEW_BOOKMARKS);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id);
    }

    public function testWillReturnOnlyCollaboratorsWithSpecifiedPermissions(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(7)->create();
        $folder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($collaborators[0], $folder, UAC::all()->toArray());
        $this->CreateCollaborationRecord($collaborators[1], $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[2], $folder, Permission::VIEW_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[3], $folder, Permission::INVITE_USER);
        $this->CreateCollaborationRecord($collaborators[4], $folder, Permission::DELETE_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborators[5], $folder, Permission::UPDATE_FOLDER);
        $this->CreateCollaborationRecord($collaborators[6], $folder, [Permission::INVITE_USER, Permission::ADD_BOOKMARKS]);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'addBookmarks,inviteUser'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[6]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[0]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'addBookmarks'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[6]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[1]->id)
            ->assertJsonPath('data.2.attributes.id', $collaborators[0]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'inviteUser'])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[6]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[3]->id)
            ->assertJsonPath('data.2.attributes.id', $collaborators[0]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'updateFolder'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[5]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[0]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'removeBookmarks'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(4, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.id', $collaborators[4]->id)
            ->assertJsonPath('data.1.attributes.id', $collaborators[0]->id);

        $this->fetchCollaboratorsResponse(['folder_id' => $folder->id, 'permissions' => 'readOnly'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[2]->id);
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

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $collaborator->delete();

        Passport::actingAs($user);
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

        Passport::actingAs($users[0]);
        $this->fetchCollaboratorsResponse(['folder_id' => $firstFolder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $users[2]->id);
    }
}

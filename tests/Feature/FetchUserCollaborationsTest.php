<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Folder;
use App\Models\User;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class FetchUserCollaborationsTest extends TestCase
{
    use AssertValidPaginationData;
    use CreatesCollaboration;

    protected function userCollaborationsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserCollaborations', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations', 'fetchUserCollaborations');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->userCollaborationsResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserCollaborations');

        $this->userCollaborationsResponse(['fields' => 'id,name,foo,1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->userCollaborationsResponse(['fields' => '1,2,3,4'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->userCollaborationsResponse(['fields' => 'id,name,description,description'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);
    }

    public function testFetchFolders(): void
    {
        $user = UserFactory::new()->create();

        /** @var Folder */
        $folder = FolderFactory::new()->create();

        $this->createCollaboration($user, $folder, UAC::all()->toArray());

        $this->loginUser($user);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $folder->public_id->present())
            ->assertJsonPath('data.0.type', 'userCollaboration')
            ->assertJsonPath('data.0.attributes.permissions', UAC::all()->toExternalIdentifiers())
            ->collect('data')
            ->each(function (array $data) {
                (new AssertableJsonString($data))
                    ->assertCount(7, 'attributes.permissions')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "description",
                            "has_description",
                            "date_created",
                            "last_updated",
                            "visibility",
                            'permissions',
                            'storage' => [
                                'items_count',
                                'capacity',
                                'is_full',
                                'available',
                                'percentage_used'
                            ],
                        ]
                    ]);
            });
    }

    private function createCollaboration(
        User $collaborator,
        Folder $folder,
        Permission|array $permissions = [],
        int $inviter = null
    ): void {

        $this->CreateCollaborationRecord($collaborator, $folder, $permissions, $inviter);
    }

    public function testWillReturnEmptyResponseWhenUserIsNotACollaboratorInAnyFolder(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyResponseWhenFolderIsDeleted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createCollaboration($collaborator, $folder);

        $folder->delete();

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnCorrectPermissions(): void
    {
        $collaborator = UserFactory::new()->create();
        $folders = FolderFactory::new()->count(2)->create();

        $this->createCollaboration($collaborator, $folders->first(), Permission::ADD_BOOKMARKS);
        $this->createCollaboration($collaborator, $folders->last(), Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks'])
            ->assertJsonPath('data.1.attributes.permissions', ['inviteUsers']);
    }

    public function testWillReturnOnlyUserRecords(): void
    {
        [$collaborator, $otherCollaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        $this->createCollaboration($collaborator, $folder, Permission::ADD_BOOKMARKS);
        $this->createCollaboration($otherCollaborator, $folder);

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks']);
    }

    public function testWillNotShowDuplicateRecords(): void
    {
        $collaborator = UserFactory::new()->create();
        $folder = FolderFactory::new()->create();

        $this->createCollaboration($collaborator, $folder, [Permission::INVITE_USER, Permission::ADD_BOOKMARKS]);

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks', 'inviteUsers']);
    }

    public function testWhenFolderOwnerHasDeletedAccount(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createCollaboration($collaborator, $folder);

        $folderOwner->delete();

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testRequestPartialResource(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->createCollaboration($collaborator, $folder, Permission::INVITE_USER);

        $this->loginUser($collaborator);
        $this->userCollaborationsResponse(['fields' => 'id,name,storage.items_count,permissions'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) {
                (new AssertableJsonString($data))
                    ->assertCount(4, 'attributes')
                    ->assertCount(1, 'attributes.storage')
                    ->assertCount(1, 'attributes.permissions')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "storage" => ['items_count'],
                            'permissions'
                        ]
                    ]);
            });
    }
}

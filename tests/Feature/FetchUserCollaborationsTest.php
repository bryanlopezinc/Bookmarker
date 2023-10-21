<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\FolderPermission;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserCollaborationsTest extends TestCase
{
    use AssertValidPaginationData;

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
        Passport::actingAs(UserFactory::new()->create());

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
                    'The fields.3 field has a duplicate value.'
                ]
            ]);
    }

    public function testFetchFolders(): void
    {
        $user = UserFactory::new()->create();

        /** @var Folder */
        $folder = FolderFactory::new()->create();

        $permissions = FolderPermission::all(['id'])
            ->pluck(['id'])
            ->map(fn (int $permissionId) => [
                'folder_id'     => $folder->id,
                'user_id'       => $user->id,
                'permission_id' => $permissionId,
                'created_at'    => now()
            ]);

        FolderCollaboratorPermission::insert($permissions->all());

        Passport::actingAs($user);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $folder->id)
            ->assertJsonPath('data.0.type', 'userCollaboration')
            ->assertJsonPath('data.0.attributes.permissions', function (array $permissions) {
                $this->assertContains('inviteUsers', $permissions);
                $this->assertContains('addBookmarks', $permissions);
                $this->assertContains('removeBookmarks', $permissions);
                $this->assertContains('updateFolder', $permissions);
                return true;
            })
            ->collect('data')
            ->each(function (array $data) {
                (new AssertableJsonString($data))
                    ->assertCount(4, 'attributes.permissions')
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

    public function testWillReturnEmptyResponseWhenUserIsNotACollaboratorInAnyFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyResponseWhenFolderIsDeletedByOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $folder->delete();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillReturnCorrectPermissions(): void
    {
        $collaborator = UserFactory::new()->create();
        $folders = FolderFactory::new()->count(2)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folders->first()->id)
            ->addBookmarksPermission()
            ->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folders->last()->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks'])
            ->assertJsonPath('data.1.attributes.permissions', ['inviteUsers']);
    }

    public function testWillReturnOnlyUserRecords(): void
    {
        [$collaborator, $anotherCollaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($anotherCollaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks']);
    }

    public function testWillNotShowDuplicateRecords(): void
    {
        $collaborator = UserFactory::new()->create();
        $folder = FolderFactory::new()->create();

        $factory = FolderCollaboratorPermissionFactory::new()->user($collaborator->id)->folder($folder->id);
        $factory->addBookmarksPermission()->create();
        $factory->inviteUser()->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.permissions', ['addBookmarks', 'inviteUsers']);
    }

    public function testWhenFolderOwnerHasDeletedAccount(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $folderOwner->delete();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testRequestPartialResource(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
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

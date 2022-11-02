<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderAccess;
use App\Models\FolderPermission;
use App\Models\User;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Collection;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserCollaborationsTest extends TestCase
{
    protected function userCollaborationsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserCollaborations', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborations', 'fetchUserCollaborations');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->userCollaborationsResponse()->assertUnauthorized();
    }

    public function testWillReturnValidationErrorsWhenPaginationDataIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userCollaborationsResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);

        $this->userCollaborationsResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->userCollaborationsResponse(['per_page' => 14])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);;

        $this->userCollaborationsResponse(['per_page' => 40])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);
    }

    public function testWillFetchFolders(): void
    {
        /** @var array<Folder> */
        $foldersWhereUserIsACollaborator = [];
        $collaborator = UserFactory::new()->create();

        UserFactory::new()
            ->count(3)
            ->create()
            ->tap(function (Collection $folderOwners) use ($collaborator, &$foldersWhereUserIsACollaborator) {
                $permissionIDs = FolderPermission::all(['id'])->pluck(['id']);

                $records = $folderOwners->map(function (User $folderOwner) use ($collaborator, $permissionIDs, &$foldersWhereUserIsACollaborator) {
                    $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);
                    $foldersWhereUserIsACollaborator[] = $folder;

                    return [
                        'folder_id' => $folder->id,
                        'user_id' => $collaborator->id,
                        'permission_id' => $permissionIDs->random(),
                        'created_at' => now()
                    ];
                })->all();

                FolderAccess::insert($records);
            });

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson(function (AssertableJson $json) use ($foldersWhereUserIsACollaborator) {
                $json->etc()
                    ->fromArray($json->toArray()['data'])
                    ->each(function (AssertableJson $json) use ($foldersWhereUserIsACollaborator) {
                        $json->etc();
                        $json->where('type', 'userCollaboration');
                        $json->where('attributes.id', function (int $folderID) use ($foldersWhereUserIsACollaborator) {
                            return collect($foldersWhereUserIsACollaborator)->pluck('id')->containsStrict($folderID);
                        });

                        (new AssertableJsonString($json->toArray()))
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
                                    "is_public",
                                    'tags',
                                    'has_tags',
                                    'tags_count',
                                    'storage' => [
                                        'items_count',
                                        'capacity',
                                        'is_full',
                                        'available',
                                        'percentage_used'
                                    ],
                                    'permissions' => [
                                        'canInviteUsers',
                                        'canAddBookmarks',
                                        'canRemoveBookmarks',
                                        'canUpdateFolder'
                                    ]
                                ]
                            ]);
                    });
            });
    }

    public function testWhenUserIsRemovedAsCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteFolderCollaborator', [
            'user_id' => $collaborator->id,
            'folder_id' => $folder->id
        ]))->assertOk();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWhenUserIsNotACollaboratorInAnyFolder(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWhenFolderIsDeletedByOwner(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteFolder'), ['folder' => $folder->id])->assertOk();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillShowCorrectPermissions(): void
    {
        $collaborator = UserFactory::new()->create();
        $folders = FolderFactory::new()->count(2)->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folders->first()->id)
            ->addBookmarksPermission()
            ->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folders->last()->id)
            ->inviteUser()
            ->create();


        Passport::actingAs($collaborator);
        $response = $this->userCollaborationsResponse()->assertOk();

        $this->assertTrue($response->json('data.0.attributes.permissions.canAddBookmarks'));
        $this->assertFalse($response->json('data.0.attributes.permissions.canInviteUsers'));
        $this->assertFalse($response->json('data.0.attributes.permissions.canRemoveBookmarks'));

        $this->assertTrue($response->json('data.1.attributes.permissions.canInviteUsers'));
        $this->assertFalse($response->json('data.1.attributes.permissions.canAddBookmarks'));
        $this->assertFalse($response->json('data.1.attributes.permissions.canRemoveBookmarks'));
    }

    public function testWillReturnOnlyUserRecords(): void
    {
        [$collaborator, $anotherCollaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->addBookmarksPermission()
            ->create();

        FolderAccessFactory::new()
            ->user($anotherCollaborator->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'canInviteUsers' => false,
                'canAddBookmarks' => true,
                'canRemoveBookmarks' => false
            ]);
    }

    public function testWillNotShowDuplicateRecords(): void
    {
        $collaborator = UserFactory::new()->create();
        $folder = FolderFactory::new()->create();

        $factory = FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id);
        $factory->addBookmarksPermission()->create();
        $factory->inviteUser()->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'canInviteUsers' => true,
                'canAddBookmarks' => true,
                'canRemoveBookmarks' => false
            ]);
    }

    public function testWhenFolderOwnerHasDeletedAccount(): void
    {
        [$folderOwner, $collaborator] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create(['user_id' => $folderOwner->id]);

        FolderAccessFactory::new()
            ->user($collaborator->id)
            ->folder($folder->id)
            ->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Passport::actingAs($folderOwner);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUsersFolders(): void
    {
        [$mark, $jeff, $collaborator] = UserFactory::new()->count(3)->create();

        $marksFolder = FolderFactory::new()->create(['user_id' => $mark->id]);
        $jeffsFolder = FolderFactory::new()->create(['user_id' => $jeff->id]);
        $permission =  FolderAccessFactory::new()->user($collaborator->id);

        $permission->folder($marksFolder->id)->create();
        $permission->folder($jeffsFolder->id)->create();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Passport::actingAs($mark);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($collaborator);
        $this->userCollaborationsResponse()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $jeffsFolder->id]);
    }
}

<?php

namespace Tests\Feature\Folder;

use App\Models\Folder;
use Database\Factories\FolderAccessFactory;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\AssertableJsonString;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FetchUserFoldersWhereHasCollaboratorTest extends TestCase
{
    protected function whereHasCollaboratorsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserFoldersWhereHasCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/contains_collaborator', 'fetchUserFoldersWhereHasCollaborator');
    }

    public function testUnAuthorizedUserCannotAccessRoute(): void
    {
        $this->whereHasCollaboratorsResponse()->assertUnauthorized();
    }

    public function testRequiredAttributesMustBePresent(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse()->assertJsonValidationErrors(['collaborator_id']);
    }

    public function testParametersMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['collaborator_id' => 'foo'])->assertJsonValidationErrors(['collaborator_id']);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => -23])->assertJsonValidationErrors(['collaborator_id']);
    }

    public function testPaginationDataMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['per_page' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must be at least 15.']
            ]);

        $this->whereHasCollaboratorsResponse(['per_page' => 51])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page' => ['The per page must not be greater than 39.']
            ]);

        $this->whereHasCollaboratorsResponse(['page' => 2001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must not be greater than 2000.']
            ]);

        $this->whereHasCollaboratorsResponse(['page' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page' => ['The page must be at least 1.']
            ]);
    }

    public function testWillFetchFolders(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $factory = FolderAccessFactory::new()->user($collaborator->id);
        $folders = FolderFactory::times(3)
            ->for($user)
            ->create()
            ->each(fn (Folder $folder) => $factory->folder($folder->id)->create())
            ->pluck('id')
            ->all();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data',
                "links" => [
                    "first",
                    "prev",
                ],
                "meta" => [
                    "current_page",
                    "path",
                    "per_page",
                    "has_more_pages",
                ]
            ])
            ->collect('data')
            ->each(function (array $data) use (&$folders) {
                $this->assertContains($id = $data['attributes']['id'], $folders);

                //remove the current id to ensure no duplicates are returned.
                unset($folders[array_search($id, $folders)]);

                (new AssertableJsonString($data))
                    ->assertCount(2)
                    ->assertCount(12, 'attributes')
                    ->assertCount(5, 'attributes.storage')
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
    }

    public function testWillReturnCorrectPermissions(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $folder = FolderFactory::new()->for($user)->create();
        $factory = FolderAccessFactory::new()->user($collaborator->id)->folder($folder->id);

        $factory->addBookmarksPermission()->create();
        $factory->updateFolderPermission()->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.permissions.canInviteUsers', false)
            ->assertJsonPath('data.0.attributes.permissions.canAddBookmarks', true)
            ->assertJsonPath('data.0.attributes.permissions.canUpdateFolder', true)
            ->assertJsonPath('data.0.attributes.permissions.canRemoveBookmarks', false);
    }

    public function testWillReturnOnlyUserFolders(): void
    {
        [$user, $collaborator, $anotherUser] = UserFactory::times(3)->create();
        $factory = FolderAccessFactory::new()->user($collaborator->id);

        $userFolder = FolderFactory::new()->for($user)->create();
        $anotherUserFolder = FolderFactory::new()->for($anotherUser)->create();

        //is a collaborator in both folders
        $factory->folder($userFolder->id)->create();
        $factory->folder($anotherUserFolder->id)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $userFolder->id);
    }

    public function testWillReturnEmptyDataSetWhenUserDoesNotExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id + 1])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhenUserIsNotACollaboratorsInAnyUserFolders(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhen_userIsACollaborator_butHasDeletedAccount(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $userFolder = FolderFactory::new()->for($user)->create();

        FolderAccessFactory::new()->user($collaborator->id)->folder($userFolder->id)->create();

        Passport::actingAs($collaborator);
        $this->deleteJson(route('deleteUserAccount'), ['password' => 'password'])->assertOk();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])->assertJsonCount(0, 'data');
    }

    public function testCanRequestPartialResource(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $userFolder = FolderFactory::new()->for($user)->create();

        FolderAccessFactory::new()->user($collaborator->id)->folder($userFolder->id)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse([
            'collaborator_id' => $collaborator->id,
            'fields' => 'id,name,permissions'
        ])
            ->assertJsonCount(1, 'data')
            ->collect('data')
            ->each(function (array $data) {
                (new AssertableJsonString($data))
                    ->assertCount(3, 'attributes')
                    ->assertCount(4, 'attributes.permissions')
                    ->assertStructure([
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            'permissions' => [
                                'canInviteUsers',
                                'canAddBookmarks',
                                'canRemoveBookmarks',
                                'canUpdateFolder'
                            ]
                        ]
                    ]);
            });
    }

    public function testFieldsMustBeValid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,foo,1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields is invalid.']
            ]);

        $this->whereHasCollaboratorsResponse(['fields' => '1,2,3,4'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields is invalid.']
            ]);
    }

    public function testFieldsMustBeUnique(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,description,description'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                    'The fields.3 field has a duplicate value.'
                ]
            ]);
    }

    public function testCannotRequestStorageWithAStorageSubType(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,storage,storage.items_count'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['Cannot request storage with a storage child field']
            ]);
    }

    public function testCannotRequestPermissionWithAPermissionSubType(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,permissions,permissions.canInviteUsers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['Cannot request permission with a permission child field']
            ]);
    }
}

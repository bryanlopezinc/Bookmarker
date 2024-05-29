<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Collections\FolderPublicIdsCollection;
use App\Enums\Permission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\GeneratesId;

class FetchUserFoldersWhereHasCollaboratorTest extends TestCase
{
    use AssertValidPaginationData;
    use CreatesCollaboration;
    use GeneratesId;

    protected function whereHasCollaboratorsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchUserFoldersWhereHasCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/users/folders/collaborators/{collaborator_id}', 'fetchUserFoldersWhereHasCollaborator');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->whereHasCollaboratorsResponse(['collaborator_id' => 5])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenCollaboratorIdIsInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserFoldersWhereHasCollaborator', ['collaborator_id' => 4]);

        $this->whereHasCollaboratorsResponse(['collaborator_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotFound']);
    }

    public function testWillFetchFolders(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $foldersPublicIds = FolderPublicIdsCollection::fromObjects($folders)->present();

        $this->CreateCollaborationRecord($collaborator, $folders[0], [Permission::INVITE_USER, Permission::ADD_BOOKMARKS]);

        $this->loginUser($folderOwner);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.permissions', function (array $permissions) {
                $this->assertCount(2, $permissions);
                $this->assertContains('addBookmarks', $permissions);
                $this->assertContains('inviteUsers', $permissions);

                return true;
            })
            ->assertJsonPath('data.0.attributes.id', $foldersPublicIds[0])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            "description",
                            "has_description",
                            "date_created",
                            "last_updated",
                            "visibility",
                            'storage' => [
                                'items_count',
                                'capacity',
                                'is_full',
                                'available',
                                'percentage_used'
                            ],
                            'permissions',
                        ]
                    ]
                ],
            ]);
    }

    #[Test]
    public function whenCollaboratorHasNoPermissions(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folders[0]);

        $this->loginUser($folderOwner);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(0, 'data.0.attributes.permissions');
    }

    public function testWillReturnOnlyUserFolders(): void
    {
        [$folderOwner, $collaborator, $otherUser] = UserFactory::times(3)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $foldersPublicIds = FolderPublicIdsCollection::fromObjects($folders)->present();

        $this->CreateCollaborationRecord($collaborator, $folders[0], Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($otherUser, $folders[1], Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->public_id->present()])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $foldersPublicIds[0]);
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorDoesNotExists(): void
    {
        $this->loginUser(UserFactory::new()->create());
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $this->generateUserId()->present()])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorIsNotACollaboratorInAnyUserFolders(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        $this->loginUser($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->public_id->present()])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorHasDeletedAccount(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $userFolder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($collaborator, $userFolder, Permission::ADD_BOOKMARKS);

        $this->loginUser($user);
        $this->whereHasCollaboratorsResponse($query = ['collaborator_id' => $collaborator->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $collaborator->delete();

        $this->whereHasCollaboratorsResponse($query)->assertJsonCount(0, 'data');
    }

    public function testCanRequestPartialResource(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $userFolder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($collaborator, $userFolder);

        $this->loginUser($user);
        $this->whereHasCollaboratorsResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'fields' => 'id,name,permissions'
        ])
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        "type",
                        "attributes" => [
                            "id",
                            "name",
                            'permissions'
                        ]
                    ]
                ]
            ]);
    }

    public function testFieldsMustBeValid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,foo,1', 'collaborator_id' => $publicId = $this->generateUserId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->whereHasCollaboratorsResponse(['fields' => '1,2,3,4', 'collaborator_id' => $publicId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,description,description', 'collaborator_id' => $publicId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);
    }
}

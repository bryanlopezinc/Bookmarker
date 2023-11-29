<?php

namespace Tests\Feature\Folder;

use App\Enums\Permission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class FetchUserFoldersWhereHasCollaboratorTest extends TestCase
{
    use AssertValidPaginationData,
        CreatesCollaboration;

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
        Passport::actingAs(UserFactory::new()->create());

        $this->assertValidPaginationData($this, 'fetchUserFoldersWhereHasCollaborator', ['collaborator_id' => 4]);

        $this->whereHasCollaboratorsResponse(['collaborator_id' => 'foo'])->assertNotFound();
        $this->whereHasCollaboratorsResponse(['collaborator_id' => -23])->assertNotFound();
    }

    public function testWillFetchFolders(): void
    {
        $users = UserFactory::times(2)->create();

        $folders = FolderFactory::times(2)->for($users[1])->create();

        $this->CreateCollaborationRecord($users[0], $folders[0], [Permission::INVITE_USER, Permission::ADD_BOOKMARKS]);

        Passport::actingAs($users[1]);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $users[0]->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.attributes.permissions')
            ->assertJsonPath('data.0.attributes.permissions', function (array $permissions) {
                $this->assertCount(2, $permissions);
                $this->assertContains('addBookmarks', $permissions);
                $this->assertContains('inviteUsers', $permissions);

                return true;
            })
            ->assertJsonPath('data.0.attributes.id', $folders[0]->id)
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

    public function testWillReturnOnlyUserFolders(): void
    {
        $users = UserFactory::times(3)->create();

        $collaborator = $users[0];

        $folders = FolderFactory::times(2)
            ->sequence(
                ['user_id' => $users[1]->id],
                ['user_id' => $users[2]->id]
            )
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folders[0]);
        $this->CreateCollaborationRecord($collaborator, $folders[1]);

        Passport::actingAs($users[1]);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $folders[0]->id);
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorDoesNotExists(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id + 1])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorIsNotACollaboratorInAnyUserFolders(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse(['collaborator_id' => $collaborator->id])->assertJsonCount(0, 'data');
    }

    public function testWillReturnEmptyDataSetWhenCollaboratorHasDeletedAccount(): void
    {
        [$user, $collaborator] = UserFactory::times(2)->create();
        $userFolder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($collaborator, $userFolder);

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse($query = ['collaborator_id' => $collaborator->id])
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

        Passport::actingAs($user);
        $this->whereHasCollaboratorsResponse([
            'collaborator_id' => $collaborator->id,
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
        Passport::actingAs(UserFactory::new()->create());

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,foo,1', 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.2 is invalid.']
            ]);

        $this->whereHasCollaboratorsResponse(['fields' => '1,2,3,4', 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => ['The selected fields.0 is invalid.']
            ]);

        $this->whereHasCollaboratorsResponse(['fields' => 'id,name,description,description', 'collaborator_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'fields' => [
                    'The fields.2 field has a duplicate value.',
                ]
            ]);
    }
}

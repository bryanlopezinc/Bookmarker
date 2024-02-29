<?php

namespace Tests\Feature\Folder;

use App\Filesystem\ProfileImageFileSystem;
use App\Models\BannedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;

class FetchFolderBannedUsersTest extends TestCase
{
    use AssertValidPaginationData;

    protected function fetchBannedCollaboratorsResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('fetchBannedCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/banned', 'fetchBannedCollaborator');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->fetchBannedCollaboratorsResponse(['folder_id' => 33])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenFolderIdIsInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchBannedCollaboratorsResponse(['folder_id' => 'f00'])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->fetchBannedCollaboratorsResponse(['name' => str_repeat('A', 11), 'folder_id' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.']);

        $this->assertValidPaginationData($this, 'fetchBannedCollaborator', ['folder_id' => 4]);
    }

    public function testSuccess(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id)
            ->assertJsonPath('data.0.attributes.name', $collaborator->full_name->present())
            ->assertJsonPath('data.0.attributes.profile_image_url', (new ProfileImageFileSystem())->publicUrl($collaborator->profile_image_path))
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'name',
                            'profile_image_url'
                        ]
                    ]
                ]
            ]);
    }

    public function testWillReturnOnlyUsersWithSpecifiedName(): void
    {
        Passport::actingAs($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(3)
            ->sequence(
                ['first_name' => 'Bryan'],
                ['first_name' => 'Bryan'],
                ['first_name' => 'Jack']
            )
            ->create();

        $folderID = FolderFactory::new()->for($user)->create()->id;

        $this->ban($collaborators[0]->id, $folderID);
        $this->ban($collaborators[1]->id, $folderID - 1);
        $this->ban($collaborators[2]->id, $folderID);

        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folderID, 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->id);
    }

    private function ban(int $userId, int $folderId): void
    {
        BannedCollaborator::query()->create([
            'folder_id' => $folderId,
            'user_id'   => $userId
        ]);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs(UserFactory::new()->create());
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id + 1])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        Passport::actingAs(UserFactory::new()->create());
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id + 1])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWilReturnOnlyFolderBannedCollaborators(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $this->ban($collaborator->id, $folders[0]->id);
        $this->ban($otherCollaborator->id, $folders[1]->id);

        Passport::actingAs($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folders[0]->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id);
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoBannedCollaborators(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUserAccounts(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        $collaborator->delete();

        Passport::actingAs($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

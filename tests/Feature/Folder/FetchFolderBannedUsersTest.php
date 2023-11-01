<?php

namespace Tests\Feature\Folder;

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

    protected function folderBannedCollaboratorsResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists($key = 'folder_id', $parameters)) {
            $parameters[$key] = (string) $parameters[$key];
        }

        return $this->getJson(route('fetchBannedCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/banned', 'fetchBannedCollaborator');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->folderBannedCollaboratorsResponse(['folder_id' => 33])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAs(UserFactory::new()->create());

        $this->folderBannedCollaboratorsResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id']);

        $this->assertValidPaginationData($this, 'fetchBannedCollaborator', ['folder_id' => 4]);
    }

    public function testSuccess(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id)
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'type',
                        'attributes' => [
                            'id',
                            'first_name',
                            'last_name',
                        ]
                    ]
                ]
            ]);
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
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs(UserFactory::new()->create());
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWilReturnOnlyFolderBannedCollaborators(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $this->ban($collaborator->id, $folders[0]->id);
        $this->ban($otherCollaborator->id, $folders[1]->id);

        Passport::actingAs($folderOwner);
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folders[0]->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborator->id);
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoBannedCollaborators(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id])
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
        $this->folderBannedCollaboratorsResponse(['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

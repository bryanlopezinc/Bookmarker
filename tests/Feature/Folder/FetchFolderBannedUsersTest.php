<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Filesystem\ProfileImageFileSystem;
use App\Models\BannedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AssertValidPaginationData;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class FetchFolderBannedUsersTest extends TestCase
{
    use AssertValidPaginationData;
    use GeneratesId;

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
        $this->loginUser(UserFactory::new()->create());

        $this->fetchBannedCollaboratorsResponse(['folder_id' => 'f00'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->fetchBannedCollaboratorsResponse(['name' => str_repeat('A', 11), 'folder_id' => $this->generateFolderId()->present()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name must not be greater than 10 characters.']);

        $this->assertValidPaginationData($this, 'fetchBannedCollaborator', ['folder_id' => $this->generateFolderId()->present()]);
    }

    public function testSuccess(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->hasProfileImage()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        $this->loginUser($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.attributes')
            ->assertJsonPath('data.0.attributes.id', $collaborator->public_id->present())
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
        $this->loginUser($user = UserFactory::new()->create());

        $collaborators = UserFactory::times(3)
            ->sequence(
                ['first_name' => 'Bryan'],
                ['first_name' => 'Bryan'],
                ['first_name' => 'Jack']
            )
            ->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->ban($collaborators[0]->id, $folder->id);
        $this->ban($collaborators[1]->id, $folder->id - 1);
        $this->ban($collaborators[2]->id, $folder->id);

        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->public_id->present(), 'name' => 'bryan'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborators[0]->public_id->present());
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

        $this->loginUser(UserFactory::new()->create());
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        $this->loginUser($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser(UserFactory::new()->create());
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $this->generateFolderId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWilReturnOnlyFolderBannedCollaborators(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folders = FolderFactory::times(2)->for($folderOwner)->create();

        $this->ban($collaborator->id, $folders[0]->id);
        $this->ban($otherCollaborator->id, $folders[1]->id);

        $this->loginUser($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folders[0]->public_id->present()])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.id', $collaborator->public_id->present());
    }

    public function testWillReturnEmptyResponseWhenFolderHasNoBannedCollaborators(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testWillNotIncludeDeletedUserAccounts(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        $collaborator->delete();

        $this->loginUser($folderOwner);
        $this->fetchBannedCollaboratorsResponse(['folder_id' => $folder->public_id->present()])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

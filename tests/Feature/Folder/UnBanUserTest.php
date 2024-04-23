<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Models\BannedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\GeneratesId;

class UnBanUserTest extends TestCase
{
    use GeneratesId;

    protected function unBanUserResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('unBanUser', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/ban', 'unBanUser');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->unBanUserResponse(['folder_id' => 33, 'collaborator_id' => 14])->assertUnauthorized();
    }

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->loginUser();

        $this->unBanUserResponse([
            'folder_id' => 44,
            'collaborator_id' => $this->generateUserId()->present()
        ])->assertNotFound()
        ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->unBanUserResponse([
            'folder_id' => $this->generateFolderId()->present(),
            'collaborator_id' => 'foo'
        ])->assertNotFound()
        ->assertJsonFragment(['message' => 'UserNotFound']);
    }

    public function testSuccess(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);
        $this->ban($otherCollaborator->id, $folder->id);

        $this->loginUser($folderOwner);
        $this->unBanUserResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertOk();

        $bannedUser = BannedCollaborator::query()->where('folder_id', $folder->id)->sole();

        $this->assertEquals($otherCollaborator->id, $bannedUser->user_id);
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
        $this->unBanUserResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        $this->loginUser($folderOwner);
        $this->unBanUserResponse(['folder_id' => $this->generateFolderId()->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->loginUser(UserFactory::new()->create());
        $this->unBanUserResponse(['folder_id' => $this->generateFolderId()->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenUserIsNotBanned(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->unBanUserResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }
}

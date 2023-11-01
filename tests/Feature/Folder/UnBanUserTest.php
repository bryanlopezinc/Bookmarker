<?php

namespace Tests\Feature\Folder;

use App\Models\BannedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UnBanUserTest extends TestCase
{
    protected function unBanUserResponse(array $parameters = []): TestResponse
    {
        if (array_key_exists('folder_id', $parameters)) {
            $parameters['folder_id'] = (string) $parameters['folder_id'];
        }

        if (array_key_exists('user_id', $parameters)) {
            $parameters['user_id'] = (string) $parameters['user_id'];
        }

        return $this->deleteJson(route('unBanUser'), $parameters);
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/ban', 'unBanUser');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->unBanUserResponse(['folder_id' => 33, 'user_id' => 14])->assertUnauthorized();
    }

    public function testSuccess(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);
        $this->ban($otherCollaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->unBanUserResponse(['folder_id' => $folder->id, 'user_id' => $collaborator->id])
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

        Passport::actingAs(UserFactory::new()->create());
        $this->unBanUserResponse(['folder_id' => $folder->id, 'user_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->ban($collaborator->id, $folder->id);

        Passport::actingAs($folderOwner);
        $this->unBanUserResponse(['folder_id' => $folder->id + 1, 'user_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        Passport::actingAs(UserFactory::new()->create());
        $this->unBanUserResponse(['folder_id' => $folder->id + 1, 'user_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotFoundWhenUserIsNotBanned(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        Passport::actingAs($folderOwner);
        $this->unBanUserResponse(['folder_id' => $folder->id, 'user_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }
}

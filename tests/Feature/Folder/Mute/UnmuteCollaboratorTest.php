<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Mute;

use App\Models\MutedCollaborator;
use App\Services\Folder\MuteCollaboratorService;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnmuteCollaboratorTest extends TestCase
{
    protected MuteCollaboratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MuteCollaboratorService::class);
    }

    protected function UnMuteCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('UnMuteCollaborator', $parameters));
    }

    #[Test]
    public function uri(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/mute', 'UnMuteCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->UnMuteCollaboratorResponse(['folder_id' => 33, 'collaborator_id' => 14])->assertUnauthorized();
    }

    #[Test]
    public function willReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->UnMuteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->UnMuteCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => 44])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function success(): void
    {
        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->service->mute($folder->id, $collaborator->id, $folderOwner->id);
        $this->service->mute($folder->id, $otherCollaborator->id, $folderOwner->id);

        $this->loginUser($folderOwner);
        $this->UnMuteCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertOk();

        $this->assertDatabaseMissing(MutedCollaborator::class, [
            'folder_id' => $folder->id,
            'user_id' => $collaborator->id
        ]);

        $this->assertDatabaseHas(MutedCollaborator::class, [
            'folder_id' => $folder->id,
            'user_id' => $otherCollaborator->id
        ]);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->UnMuteCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => UserFactory::publicId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->UnMuteCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => UserFactory::publicId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExists(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->UnMuteCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => UserFactory::publicId()->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->UnMuteCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $collaborator->public_id->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }
}

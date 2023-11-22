<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Mute;

use App\Enums\Permission;
use App\Models\MutedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class MuteCollaboratorTest extends TestCase
{
    use CreatesCollaboration;

    protected function muteCollaboratorResponse(array $parameters = []): TestResponse
    {
        foreach (['folder_id', 'collaborator_id'] as $key) {
            if (array_key_exists($key, $parameters)) {
                $parameters[$key] = (string) $parameters[$key];
            }
        }

        return $this->postJson(route('muteCollaborator'), $parameters);
    }

    #[Test]
    public function uri(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/mute', 'muteCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->muteCollaboratorResponse()->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParameterAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->muteCollaboratorResponse()
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'folder_id'       => 'The folder id field is required.',
                'collaborator_id' => 'The collaborator id field is required.'
            ]);

        $this->muteCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => -2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id', 'collaborator_id']);
    }

    #[Test]
    public function muteCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertCreated();

        $this->assertDatabaseHas(MutedCollaborator::class, [
            'folder_id' => $folder->id,
            'user_id'   => $collaborator->id
        ]);
    }

    #[Test]
    public function willReturnSuccessWhenCollaboratorIsAlreadyMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse($query = ['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertCreated();

        $this->muteCollaboratorResponse($query)->assertCreated();
    }

    #[Test]
    public function willReturnForbiddenWhenUserIsMutingSelf(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $user->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotMuteSelf']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->muteCollaboratorResponse(['folder_id' => $folder->id + 1, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }
}

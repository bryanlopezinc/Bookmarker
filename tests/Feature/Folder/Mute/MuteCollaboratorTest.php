<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Mute;

use App\Enums\Permission;
use App\Models\MutedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;

class MuteCollaboratorTest extends TestCase
{
    use CreatesCollaboration;
    use WithFaker;

    protected function muteCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->postJson(
            route('muteCollaborator', Arr::only($parameters, $routeParameters = ['folder_id', 'collaborator_id'])),
            Arr::except($parameters, $routeParameters)
        );
    }

    #[Test]
    public function uri(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/mute', 'muteCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => 33, 'collaborator_id' => 14])
            ->assertUnauthorized();
    }

    #[Test]
    public function willReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->muteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])->assertNotFound();
        $this->muteCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => 44])->assertNotFound();
    }

    #[Test]
    public function muteCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertCreated();

        /** @var MutedCollaborator */
        $record = MutedCollaborator::query()->where(['folder_id' => $folder->id, 'user_id' => $collaborator->id])->sole();

        $this->assertNull($record->muted_until);
        $this->assertNotNull($record->muted_at);
    }

    #[Test]
    public function muteCollaboratorForAGivenPeriod(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $query = ['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id];

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['mute_until' => -1, ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mute_until' => 'The mute until must be at least 1.']);

        $this->withRequestId()
            ->muteCollaboratorResponse(['mute_until' => 745, ...$query])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mute_until' => 'The mute until must not be greater than 744.']);

        $this->withRequestId()
            ->muteCollaboratorResponse(['mute_until' => 1, ...$query])
            ->assertCreated();

        /** @var MutedCollaborator */
        $record = MutedCollaborator::query()->where(['folder_id' => $folder->id, 'user_id' => $collaborator->id])->sole();

        $this->assertEquals(1, $record->muted_at->diffInHours($record->muted_until));
    }

    #[Test]
    public function muteACollaboratorAgainWhenMuteDurationIsPast(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $query = ['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id];

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);

        $this->withRequestId()
            ->muteCollaboratorResponse(['mute_until' => 1, ...$query])
            ->assertCreated();

        $this->travel(61)->minutes(function () use ($query) {
            $this->withRequestId()
                ->muteCollaboratorResponse(['mute_until' => 1, ...$query])
                ->assertCreated();
        });

        /** @var MutedCollaborator */
        $record = MutedCollaborator::query()->where(['folder_id' => $folder->id, 'user_id' => $collaborator->id])->sole();

        $this->assertEquals(1, $record->muted_at->diffInHours($record->muted_until));
    }

    #[Test]
    public function whenRequestHasBeenCompleted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);

        $this->withRequestId($requestId = $this->faker->uuid)
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertCreated();

        $this->withRequestId($requestId)->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id]);
        $this->assertRequestAlreadyCompleted();
    }

    #[Test]
    public function willReturnCOnflictWhenCollaboratorIsAlreadyMuted(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse($query = ['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertCreated();

        $this->withRequestId()
            ->muteCollaboratorResponse($query)
            ->assertConflict()
            ->assertExactJson(['message' => 'CollaboratorAlreadyMuted']);
    }

    #[Test]
    public function willReturnForbiddenWhenUserIsMutingSelf(): void
    {
        $user = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->loginUser($user);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $user->id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotMuteSelf']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id + 1])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorIsNotACollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($folderOwner);
        $this->withRequestId()
            ->muteCollaboratorResponse(['folder_id' => $folder->id + 1, 'collaborator_id' => $collaborator->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);
    }
}

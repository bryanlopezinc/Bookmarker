<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Suspension;

use App\Enums\ActivityType;
use App\DataTransferObjects\Activities\SuspensionLiftedActivityLogData as ActivityLogData;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Arr;
use Tests\Traits\GeneratesId;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;
use Database\Factories\FolderFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;

class ReInstateSuspendedCollaboratorTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;

    private function reInstateSuspendCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(
            route('reInstateSuspendCollaborator', $parameters),
            Arr::except($parameters, ['folder_id', 'collaborator_id'])
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/suspend', 'reInstateSuspendCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->reInstateSuspendCollaboratorResponse(['folder_id' => 5, 'collaborator_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        [$folderId, $userId] = [
            $this->generateFolderId()->present(),
            $this->generateUserId()->present()
        ];

        $this->loginUser();

        $this->reInstateSuspendCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => $userId])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->reInstateSuspendCollaboratorResponse(['folder_id' => $folderId, 'collaborator_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotFound']);
    }

    #[Test]
    public function reInstateSuspendCollaborator(): void
    {
        /** @var User */
        [$currentUser, $suspendedCollaborator, $otherSuspendedCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($currentUser)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($otherSuspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);
        SuspendCollaborator::suspend($otherSuspendedCollaborator, $folder);

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
        ])->assertSuccessful();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertEquals(
            $folder->suspendedCollaborators->sole()->collaborator_id,
            $otherSuspendedCollaborator->id
        );

        $this->assertEquals($activity->type, ActivityType::SUSPENSION_LIFTED);
        $this->assertEquals($activity->data, (new ActivityLogData($suspendedCollaborator, $currentUser))->toArray());
    }

    #[Test]
    public function willReturnNotFoundWhenSuspendedCollaboratorIsAlreadyReInstated(): void
    {
        /** @var User */
        [$currentUser, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($currentUser)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse($query = [
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
        ])->assertSuccessful();

        $this->refreshApplication();
        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse($query)
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'CollaboratorNotSuspended']);

        $this->assertCount(1, $folder->activities);
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        [$currentUser, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $suspendedCollaborator->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertEquals($folder->suspendedCollaborators->sole()->collaborator_id, $suspendedCollaborator->id);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$currentUser, $collaborator] = UserFactory::times(2)->create();

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse([
            'folder_id'       => $this->generateFolderId()->present(),
            'collaborator_id' => $collaborator->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExists(): void
    {
        $currentUser = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($currentUser)->create();

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $this->generateUserId()->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenAffectedUserIsNotACollaborator(): void
    {
        [$currentUser, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($currentUser)->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $otherFolder = FolderFactory::new()->create());

        SuspendCollaborator::suspend($suspendedCollaborator, $otherFolder);

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $suspendedCollaborator->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);

        $this->assertEquals($otherFolder->suspendedCollaborators->sole()->collaborator_id, $suspendedCollaborator->id);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorIsReInstatingASuspendedCollaborator(): void
    {
        [$collaborator, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($collaborator);
        $this->reInstateSuspendCollaboratorResponse([
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->loginUser($suspendedCollaborator);
        $this->reInstateSuspendCollaboratorResponse([
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals($folder->suspendedCollaborators->sole()->collaborator_id, $suspendedCollaborator->id);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        /** @var User */
        [$currentUser, $suspendedCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($currentUser)
            ->settings(FolderSettingsBuilder::new()->enableActivities(false))
            ->create();

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);

        $this->loginUser($currentUser);
        $this->reInstateSuspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
        ])->assertSuccessful();

        $this->assertCount(0, $folder->activities);
    }
}

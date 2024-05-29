<?php

declare(strict_types=1);

namespace Tests\Feature\Folder\Suspension;

use App\Actions\ToggleFolderFeature;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\DataTransferObjects\Activities\CollaboratorSuspendedActivityLogData as ActivityLogData;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\Models\SuspendedCollaborator;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class SuspendCollaboratorTest extends TestCase
{
    use GeneratesId;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;

    private function suspendCollaboratorResponse(array $data = []): TestResponse
    {
        return $this->postJson(
            route('suspendCollaborator', $data),
            Arr::except($data, ['folder_id', 'collaborator_id'])
        );
    }

    #[Test]
    public function route(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}/suspend', 'suspendCollaborator');
    }

    #[Test]
    public function willReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->suspendCollaboratorResponse(['folder_id' => 5, 'collaborator_id' => 5])->assertUnauthorized();
    }

    #[Test]
    public function willReturnUnprocessableWhenParametersAreInvalid(): void
    {
        [$folderId, $userId] = [
            $this->generateFolderId()->present(),
            $this->generateUserId()->present()
        ];

        $this->loginUser();

        $this->suspendCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => $userId])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->suspendCollaboratorResponse(['folder_id' => $folderId, 'collaborator_id' => 'foo'])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotFound']);

        $this->suspendCollaboratorResponse([
            'folder_id'       => $folderId,
            'collaborator_id' => $userId,
            'duration'        => -1
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['duration' => 'The duration must be at least 1.']);

        $this->suspendCollaboratorResponse([
            'folder_id'       => $folderId,
            'collaborator_id' => $userId,
            'duration'        => 'foo'
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['duration' => 'The duration must be an integer.']);

        $this->suspendCollaboratorResponse([
            'folder_id'       => $folderId,
            'collaborator_id' => $userId,
            'duration'        => 745
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['duration' => 'The duration must not be greater than 744.']);
    }

    #[Test]
    public function suspendCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();
        $time = now();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->travelTo($time, function () use ($folder, $collaborator) {
            $this->suspendCollaboratorResponse([
                'folder_id'       => $folder->public_id->present(),
                'collaborator_id' => $collaborator->public_id->present(),
            ])->assertCreated();
        });

        /** @var SuspendedCollaborator */
        $record = $folder->suspendedCollaborators->sole();

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertEquals($collaborator->id, $record->collaborator_id);
        $this->assertEquals($time->toDateTimeString(), $record->suspended_at->toDateTimeString());
        $this->assertNull($record->suspended_until);
        $this->assertNull($record->duration_in_hours);
        $this->assertEquals($folderOwner->id, $record->suspended_by);
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::SUSPENDED_COLLABORATORS);
        $this->assertEquals($activity->type, ActivityType::USER_SUSPENDED);
        $this->assertEquals($activity->data, (new ActivityLogData($collaborator, $folderOwner, null))->toArray());
    }

    #[Test]
    public function collaboratorWithPermissionCanSuspendAnotherCollaborator(): void
    {
        [$collaboratorWithSuspendUserPermission, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);
        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertCreated();

        /** @var SuspendedCollaborator */
        $record = $folder->suspendedCollaborators->sole();

        $this->assertEquals($collaboratorWithSuspendUserPermission->id, $record->suspended_by);
        $this->assertFolderCollaboratorMetric($collaboratorWithSuspendUserPermission->id, $folder->id, $type = CollaboratorMetricType::SUSPENDED_COLLABORATORS);
        $this->assertFolderCollaboratorMetricsSummary($collaboratorWithSuspendUserPermission->id, $folder->id, $type);
        $this->assertTrue($folder->activities->isNotEmpty());
    }

    #[Test]
    public function collaboratorWithRoleCanSuspendAnotherCollaborator(): void
    {
        [$collaboratorWithSuspendUserRole, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserRole, $folder, Permission::ADD_BOOKMARKS);
        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->attachRoleToUser($collaboratorWithSuspendUserRole, $this->createRole('foo', $folder, Permission::SUSPEND_USER));

        $this->loginUser($collaboratorWithSuspendUserRole);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
        ])->assertCreated();

        /** @var SuspendedCollaborator */
        $record = $folder->suspendedCollaborators->sole();

        $this->assertEquals($collaboratorWithSuspendUserRole->id, $record->suspended_by);
        $this->assertTrue($folder->activities->isNotEmpty());
    }

    #[Test]
    public function collaboratorWithPermissionCannotSuspendFolderOwner(): void
    {
        [$collaboratorWithSuspendUserPermission, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $folderOwner->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'CannotSuspendFolderOwner']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function suspendCollaboratorForACertainPeriod(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
            'duration'        => 3
        ])->assertCreated();

        /** @var SuspendedCollaborator */
        $record = $folder->suspendedCollaborators->sole();

        $this->assertEquals(3, $record->suspended_at->diffInHours($record->suspended_until));
        $this->assertEquals(3, $record->duration_in_hours);
        $this->assertEquals($folder->activities->sole()->data, (new ActivityLogData($collaborator, $folderOwner, 3))->toArray());
    }

    #[Test]
    public function canSuspendCollaboratorAgainWhenSuspensionPeriodIsPast(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse($query = [
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
            'duration'        => 1,
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->travel(62)->minutes(function () use ($query) {
            $this->suspendCollaboratorResponse($query)->assertCreated();
        });

        /** @var SuspendedCollaborator */
        $record = $folder->suspendedCollaborators->sole();

        $this->assertEquals(2, $record->suspended_at->diffInHours($record->suspended_until));
        $this->assertEquals(1, $record->duration_in_hours);
        $this->assertCount(2, $folder->activities);
    }

    #[Test]
    public function willReturnConflictWhenCollaboratorIsAlreadySuspended(): void
    {
        [$folderOwner, $collaborator, $collaboratorWithSuspendUserPermission] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse($data = [
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaborator->public_id->present(),
            'duration'        => 3
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->travel(177)->minutes(function () use ($data) {
            $this->suspendCollaboratorResponse($data)->assertConflict()->assertJsonFragment(['message' => 'CollaboratorAlreadySuspended']);
        });

        $this->suspendCollaboratorResponse($data)->assertConflict()->assertJsonFragment(['message' => 'CollaboratorAlreadySuspended']);

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse($data)->assertConflict()->assertJsonFragment(['message' => 'CollaboratorAlreadySuspended']);

        $this->assertCount(1, $folder->activities);
    }

    #[Test]
    public function willReturnForbiddenWhenSuspendingSelf(): void
    {
        [$folderOwner, $collaboratorWithSuspendUserPermission] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $folderOwner->public_id->present()])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotSuspendSelf']);

        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $collaboratorWithSuspendUserPermission->public_id->present()
        ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'CannotSuspendSelf']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenCollaboratorDoesNotExists(): void
    {
        $folderOwner = UserFactory::new()->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $this->generateUserId()->present()])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenAffectedUserIsNotACollaborator(): void
    {
        [$folderOwner, $user] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $request = [
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $user->public_id->present()
        ];

        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse($request)
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotBelongToUser(): void
    {
        [$user, $otherUser] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $this->loginUser($user);
        $this->suspendCollaboratorResponse(['folder_id' => $folder->public_id->present(), 'collaborator_id' => $otherUser->public_id->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnNotFoundWhenFolderDoesNotExists(): void
    {
        [$user, $otherUser] = UserFactory::times(2)->create();

        $this->loginUser($user);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $this->generateFolderId()->present(),
            'collaborator_id' => $otherUser->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorWithInAdequatePermissionIsSuspendingAnotherCollaborator(): void
    {
        [$collaborator, $otherCollaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $allPermissions = array_column(Permission::cases(), 'value');

        $allPermissionsExceptSuspendUserPermission = collect($allPermissions)
            ->reject(Permission::SUSPEND_USER->value)
            ->tap(fn ($permissions) => $this->assertCount(count($allPermissions) - 1, $permissions))
            ->all();

        $this->CreateCollaborationRecord($collaborator, $folder, $allPermissionsExceptSuspendUserPermission);
        $this->CreateCollaborationRecord($otherCollaborator, $folder);

        $this->loginUser($collaborator);
        $this->suspendCollaboratorResponse([
            'collaborator_id' => $otherCollaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
        ])->assertForbidden()->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertTrue($folder->suspendedCollaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function whenSuspendCollaboratorFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $toggleFolderFeature = app(ToggleFolderFeature::class);

        [$folderOwner, $collaborator, $collaboratorWithSuspendUserPermission] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($collaboratorWithSuspendUserPermission, $folder, Permission::SUSPEND_USER);

        //Assert collaborator can suspend collaborator when disabled feature is not suspend user feature.
        $toggleFolderFeature->disable($folder->id, Feature::SEND_INVITES);
        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse($data = [
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present(),
        ])->assertCreated();

        $toggleFolderFeature->disable($folder->id, Feature::SUSPEND_USER);

        $folder->suspendedCollaborators->toQuery()->delete();

        //assert folder owner can suspend user when feature is disabled.
        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse($data)->assertCreated();

        $this->refreshApplication();
        $this->loginUser($collaboratorWithSuspendUserPermission);
        $this->suspendCollaboratorResponse($data)->assertForbidden()->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->assertCount(2, $folder->activities);
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new LogActivities(false))
            ->create();

        $suspendedCollaborators = UserFactory::times(2)->create()->each(function ($collaborator) use ($folder) {
            $this->CreateCollaborationRecord($collaborator, $folder);
        });

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::SUSPEND_USER);

        $this->loginUser($collaborator);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $suspendedCollaborators[0]->public_id->present(),
        ])->assertCreated();

        $this->refreshApplication();
        $this->loginUser($folderOwner);
        $this->suspendCollaboratorResponse([
            'folder_id'       => $folder->public_id->present(),
            'collaborator_id' => $suspendedCollaborators[1]->public_id->present(),
        ])->assertCreated();

        $this->assertCount(0, $folder->activities);
    }
}

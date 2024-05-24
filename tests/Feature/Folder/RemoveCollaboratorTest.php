<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Collections\UserPublicIdsCollection;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData as ActivityLogData;
use App\FolderSettings\Settings\Activities\LogActivities;
use App\Http\Handlers\SuspendCollaborator\SuspendCollaborator;
use App\Models\Folder;
use App\Models\FolderCollaboratorPermission;
use App\Models\User;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\TestCase;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;
use Tests\Traits\GeneratesId;

class RemoveCollaboratorTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use AssertFolderCollaboratorMetrics;
    use CreatesRole;
    use GeneratesId;

    protected function deleteCollaboratorResponse(array $parameters = []): TestResponse
    {
        return $this->deleteJson(route('deleteFolderCollaborator', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/{folder_id}/collaborators/{collaborator_id}', 'deleteFolderCollaborator');
    }

    public function testWillReturnUnAuthorizedWhenUserIsNotLoggedIn(): void
    {
        $this->deleteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 33])->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => $this->generateUserId()->present()])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->deleteCollaboratorResponse(['folder_id' => $this->generateFolderId()->present(), 'collaborator_id' => 44])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotFound']);

        $this->deleteCollaboratorResponse([
            'ban'             => 'foo',
            'folder_id'       => $this->generateFolderId()->present(),
            'collaborator_id' => $this->generateUserId()->present()
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ban']);
    }

    public function testRemoveCollaborator(): void
    {
        Notification::fake();

        [$folderOwner, $collaborator, $otherCollaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->toArray());
        $this->CreateCollaborationRecord($otherCollaborator, $folder, UAC::all()->toArray());

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        Notification::assertNothingSentTo($folderOwner);

        $collaboratorIds = $folder->collaborators->pluck('collaborator_id');

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'folder_id' => $folder->id,
            'user_id'   => $collaborator->id,
        ]);

        $this->assertNotContains($collaborator->id, $collaboratorIds);
        $this->assertNotContains($collaborator->id, $folder->bannedUsers->pluck('id'));
        $this->assertContains($otherCollaborator->id, $collaboratorIds);
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::COLLABORATORS_REMOVED);
        $this->assertEquals($activity->type, ActivityType::COLLABORATOR_REMOVED);
        $this->assertEquals($activity->data, (new ActivityLogData($collaborator, $folderOwner))->toArray());
    }

    #[Test]
    public function willRemoveCollaboratorFromMutedCollaboratorsList(): void
    {
        [$currentUser, $mutedCollaborator, $otherMutedCollaborator] = UserFactory::times(3)->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        [$folder, $currentUserSecondFolderWhereCollaboratorIsMuted] = FolderFactory::times(2)->for($currentUser)->create();

        $otherFolderWhereCollaboratorIsMuted = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($mutedCollaborator, $folder);

        $muteCollaboratorService->mute($folder->id, [$mutedCollaborator->id, $otherMutedCollaborator->id], $currentUser->id);
        $muteCollaboratorService->mute($currentUserSecondFolderWhereCollaboratorIsMuted->id, $mutedCollaborator->id, $currentUser->id);
        $muteCollaboratorService->mute($otherFolderWhereCollaboratorIsMuted->id, $mutedCollaborator->id, $otherFolderWhereCollaboratorIsMuted->user_id);


        $this->loginUser($currentUser);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $mutedCollaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        $this->assertEquals($folder->mutedCollaborators->sole()->user_id, $otherMutedCollaborator->id);
        $this->assertTrue($currentUserSecondFolderWhereCollaboratorIsMuted->mutedCollaborators->isNotEmpty());
        $this->assertTrue($otherFolderWhereCollaboratorIsMuted->mutedCollaborators->isNotEmpty());
    }

    #[Test]
    public function willRevokeCollaboratorRoles(): void
    {
        /** @var User */
        [$currentUser, $collaborator, $otherCollaboratorWithSameRoleAsCollaborator] = UserFactory::times(3)->create();

        [$folder, $currentUserSecondFolderWhereCollaboratorHasRole] = FolderFactory::times(2)->for($currentUser)->create();

        $otherFolderWhereCollaboratorHasRole = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->CreateCollaborationRecord($otherCollaboratorWithSameRoleAsCollaborator, $folder);
        $this->CreateCollaborationRecord($collaborator, $otherFolderWhereCollaboratorHasRole);
        $this->CreateCollaborationRecord($collaborator, $currentUserSecondFolderWhereCollaboratorHasRole);

        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $folder));
        $this->attachRoleToUser($otherCollaboratorWithSameRoleAsCollaborator, $role);
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $otherFolderWhereCollaboratorHasRole));
        $this->attachRoleToUser($collaborator, $this->createRole(folder: $currentUserSecondFolderWhereCollaboratorHasRole));

        $this->loginUser($currentUser);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        $folderIdsWhereCollaboratorHasRoles = $collaborator->roles->pluck('folder_id')->all();

        $this->assertCount(2, $folderIdsWhereCollaboratorHasRoles);
        $this->assertCount(1, $otherCollaboratorWithSameRoleAsCollaborator->roles);

        $this->assertEqualsCanonicalizing(
            $folderIdsWhereCollaboratorHasRoles,
            [$otherFolderWhereCollaboratorHasRole->id, $currentUserSecondFolderWhereCollaboratorHasRole->id]
        );
    }

    #[Test]
    public function willRemoveCollaboratorFromSuspendedCollaboratorsList(): void
    {
        /** @var User */
        [$currentUser, $suspendedCollaborator, $otherSuspendedCollaborator] = UserFactory::times(3)->create();

        /** @var Folder */
        $folder = FolderFactory::new()->for($currentUser)->create();

        $otherFolderWhereCollaboratorIsSuspended = FolderFactory::new()->create();

        SuspendCollaborator::suspend($suspendedCollaborator, $folder);
        SuspendCollaborator::suspend($suspendedCollaborator, $otherFolderWhereCollaboratorIsSuspended);
        SuspendCollaborator::suspend($otherSuspendedCollaborator, $folder);

        $this->CreateCollaborationRecord($suspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($otherSuspendedCollaborator, $folder);
        $this->CreateCollaborationRecord($suspendedCollaborator, $otherFolderWhereCollaboratorIsSuspended);

        $this->loginUser($currentUser);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $suspendedCollaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        $this->assertEquals($folder->suspendedCollaborators->sole()->collaborator_id, $otherSuspendedCollaborator->id);
        $this->assertTrue($otherFolderWhereCollaboratorIsSuspended->suspendedCollaborators->isNotEmpty());
    }

    #[Test]
    public function collaboratorWithPermissionOrRoleCanRemoveCollaborator(): void
    {
        [$collaborator, $collaboratorWithRole] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();
        $folderPublicId = $folder->public_id->present();

        $collaboratorsToBeKickedOut = UserFactory::times(3)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $collaboratorsToBeKickedOutPublicIds = UserPublicIdsCollection::fromObjects($collaboratorsToBeKickedOut)->present();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);
        $this->CreateCollaborationRecord($collaboratorWithRole, $folder);
        $this->attachRoleToUser($collaboratorWithRole, $this->createRole(folder: $folder, permissions: Permission::REMOVE_USER));

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOutPublicIds[0], 'folder_id' => $folderPublicId])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::COLLABORATORS_REMOVED);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);
        $this->assertNotContains($collaboratorsToBeKickedOut[0]->id, $folder->collaborators->pluck('collaborator_id'));

        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOutPublicIds[1], 'folder_id' => $folderPublicId])->assertOk();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);

        $this->loginUser($collaboratorWithRole);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOutPublicIds[2], 'folder_id' => $folderPublicId])->assertOk();
        $this->assertNotContains($collaboratorsToBeKickedOut[0]->id, $folder->refresh()->collaborators->pluck('collaborator_id'));
    }

    public function testBanCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $collaboratorsToBeKickedOut = UserFactory::times(2)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $collaboratorsToBeKickedOutPublicIds = UserPublicIdsCollection::fromObjects($collaboratorsToBeKickedOut)->present();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaboratorsToBeKickedOutPublicIds[0],
            'folder_id'       => $folder->public_id->present(),
            'ban'             => true
        ])->assertOk();

        $this->assertContains($collaboratorsToBeKickedOut[0]->id, $folder->bannedUsers->pluck('id'));

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaboratorsToBeKickedOutPublicIds[1],
            'folder_id' => $folder->public_id->present(),
            'ban'       => true
        ])->assertOk();

        $this->assertContains($collaboratorsToBeKickedOut[1]->id, $folder->refresh()->bannedUsers->pluck('id'));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'       => $this->generateFolderId()->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $folder = FolderFactory::new()->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function cannotRemoveUserThatIsNotACollaborator(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->public_id->present(),
            'folder_id'      => $folder->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => $this->generateUserId()->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    public function testWillReturnForbiddenWhenUserIsRemovingSelf(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse(['collaborator_id' => $folderOwner->public_id->present(), 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment($expectation = ['message' => 'CannotRemoveSelf']);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaborator->public_id->present(), 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment($expectation);

        $this->assertEquals($collaborator->id, $folder->collaborators->sole()->collaborator_id);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function collaboratorWithPermissionCannotRemoveFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $folderOwner->public_id->present(), 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotRemoveFolderOwner']);

        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorDoesNotHavePermissionOrRole(): void
    {
        [$collaborator, $folderOwner, $collaboratorToBeKickedOut] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->except(Permission::REMOVE_USER)->toArray());
        $this->CreateCollaborationRecord($collaboratorToBeKickedOut, $folder);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorToBeKickedOut->public_id->present(), 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals([$collaborator->id, $collaboratorToBeKickedOut->id], $folder->collaborators->pluck('collaborator_id')->all());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenFeatureIsDisabled(): void
    {
        /** @var ToggleFolderFeature */
        $updateCollaboratorActionService = app(ToggleFolderFeature::class);

        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $collaboratorsToBeKickedOut = UserFactory::times(2)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $collaboratorsToBeKickedOutPublicIds = UserPublicIdsCollection::fromObjects($collaboratorsToBeKickedOut)->present();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        //Assert collaborator can remove bookmark when disabled action is not remove user feature
        $updateCollaboratorActionService->disable($folder->id, Feature::ADD_BOOKMARKS);
        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOutPublicIds[0], 'folder_id' => $folder->public_id->present()])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaborator);
        $updateCollaboratorActionService->disable($folder->id, Feature::REMOVE_USER);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOutPublicIds[1], 'folder_id' => $folder->public_id->present()])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->assertCount(2, $folder->collaborators);
        $this->assertCount(1, $folder->activities);
        $this->assertContains($collaboratorsToBeKickedOut[1]->id, $folder->collaborators->pluck('collaborator_id'));
    }

    #[Test]
    public function willNotifyCollaboratorThatWasRemoved(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        /** @var \App\Models\DatabaseNotification */
        $notification = $collaborator->notifications()->sole(['data', 'type']);

        $this->assertEquals(7, $notification->type->value);
        $this->assertEquals($notification->data, [
            'version'  => '1.0.0',
            'folder'   => [
                'id'   => $folder->id,
                'name' => $folder->name->value,
                'public_id' => $folder->public_id->value,
            ],
        ]);
    }

    #[Test]
    public function willNotifyFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        /** @var User[] */
        $collaboratorsKickedOut = UserFactory::times(2)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $collaboratorsToBeKickedOutPublicIds = UserPublicIdsCollection::fromObjects($collaboratorsKickedOut)->present();

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaboratorsToBeKickedOutPublicIds[0],
            'folder_id' => $folder->public_id->present()
        ])->assertOk();

        //To Ensure will always sort by latest (created_at)
        $this->travel(1)->minute(function () use ($collaboratorsToBeKickedOutPublicIds, $folder) {
            $this->deleteCollaboratorResponse([
                'collaborator_id' => $collaboratorsToBeKickedOutPublicIds[1],
                'folder_id'       => $folder->public_id->present(),
                'ban'             => true
            ])->assertOk();
        });

        $notifications = $folderOwner->notifications()->get(['data'])->pluck('data')->toArray();

        $json = AssertableJson::fromArray($notifications);

        $this->assertCount(2, $notifications);
        $json->where('0.banned', true)
            ->where('1.banned', false)
            ->where('0.collaborator_removed.id', $collaboratorsKickedOut[1]->id)
            ->where('0.collaborator_removed.public_id', $collaboratorsKickedOut[1]->public_id->value)
            ->where('0.collaborator_removed.full_name', $collaboratorsKickedOut[1]->full_name->value)
            ->each(function (AssertableJson $json) use ($folder, $collaborator) {
                $json->where('version', '1.0.0')
                    ->where('folder.id', $folder->id)
                    ->where('folder.name', $folder->name->value)
                    ->where('collaborator.id', $collaborator->id)
                    ->where('collaborator.public_id', $collaborator->public_id->value)
                    ->where('collaborator.full_name', $collaborator->full_name->value)
                    ->etc();
            });
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(new LogActivities(false))
            ->create();

        $collaborators = UserFactory::times(2)->create()->each(function ($collaborator) use ($folder) {
            $this->CreateCollaborationRecord($collaborator, $folder);
        });

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->toArray());

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborators[0]->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        $this->refreshApplication();
        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborators[1]->public_id->present(),
            'folder_id'       => $folder->public_id->present()
        ])->assertOk();

        $this->assertCount(0, $folder->activities);
    }
}

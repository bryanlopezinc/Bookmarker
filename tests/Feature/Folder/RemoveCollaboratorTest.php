<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Models\FolderCollaboratorPermission;
use App\Models\User;
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

class RemoveCollaboratorTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use AssertFolderCollaboratorMetrics;
    use CreatesRole;

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

    public function testWillReturnNotFoundWhenRouteParametersAreInvalid(): void
    {
        $this->deleteCollaboratorResponse(['folder_id' => 44, 'collaborator_id' => 'foo'])->assertNotFound();
        $this->deleteCollaboratorResponse(['folder_id' => 'foo', 'collaborator_id' => 44])->assertNotFound();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteCollaboratorResponse(['ban' => 'foo', 'folder_id' => 44, 'collaborator_id' => 33])
            ->assertUnprocessable()
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
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaborator->id, 'folder_id' => $folder->id])->assertOk();

        Notification::assertNothingSentTo($folderOwner);

        $collaboratorIds = $folder->collaborators->pluck('id');

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, [
            'user_id'   => $collaborator->id,
            'folder_id' => $folder->id
        ]);

        $this->assertNotContains($collaborator->id, $collaboratorIds);
        $this->assertNotContains($collaborator->id, $folder->bannedUsers->pluck('id'));
        $this->assertContains($otherCollaborator->id, $collaboratorIds);
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::COLLABORATORS_REMOVED);
    }

    #[Test]
    public function collaboratorWithPermissionOrRoleCanRemoveCollaborator(): void
    {
        [$collaborator, $collaboratorWithRole] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->create();

        $collaboratorsToBeKickedOut = UserFactory::times(3)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);
        $this->CreateCollaborationRecord($collaboratorWithRole, $folder);
        $this->attachRoleToUser($collaboratorWithRole, $this->createRole(folder: $folder, permissions: Permission::REMOVE_USER));

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOut[0]->id, 'folder_id' => $folder->id])->assertOk();

        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::COLLABORATORS_REMOVED);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);
        $this->assertNotContains($collaboratorsToBeKickedOut[0]->id, $folder->collaborators->pluck('id'));

        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOut[1]->id, 'folder_id' => $folder->id])->assertOk();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);

        $this->loginUser($collaboratorWithRole);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOut[2]->id, 'folder_id' => $folder->id])->assertOk();
        $this->assertNotContains($collaboratorsToBeKickedOut[0]->id, $folder->refresh()->collaborators->pluck('id'));
    }

    public function testBanCollaborator(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $collaboratorsToBeKickedOut = UserFactory::times(2)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaboratorsToBeKickedOut[0]->id,
            'folder_id' => $folder->id,
            'ban'       => true
        ])->assertOk();

        $this->assertContains($collaboratorsToBeKickedOut[0]->id, $folder->bannedUsers->pluck('id'));

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaboratorsToBeKickedOut[1]->id,
            'folder_id' => $folder->id,
            'ban'       => true
        ])->assertOk();

        $this->assertContains($collaboratorsToBeKickedOut[1]->id, $folder->refresh()->bannedUsers->pluck('id'));
    }

    public function testWillReturnNotFoundWhenFolderDoesNotExist(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id + 1
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    public function testWillReturnNotWhenFolderDoesNotBelongToUser(): void
    {
        $this->loginUser(UserFactory::new()->create());

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => FolderFactory::new()->create()->id
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'FolderNotFound']);
    }

    #[Test]
    public function cannotRemoveUserThatIsNotACollaborator(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnNotFoundWhenUserDoesNotExists(): void
    {
        $this->loginUser($user = UserFactory::new()->create());
        $folder = FolderFactory::new()->for($user)->create();

        $this->deleteCollaboratorResponse([
            'collaborator_id' => UserFactory::new()->create()->id + 1,
            'folder_id' => $folder->id
        ])->assertNotFound()
            ->assertJsonFragment(['message' => 'UserNotACollaborator']);
    }

    public function testWillReturnForbiddenWhenUserIsRemovingSelf(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse(['collaborator_id' => $folderOwner->id, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($expectation = ['message' => 'CannotRemoveSelf']);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaborator->id, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment($expectation);

        $this->assertEquals($collaborator->id, $folder->collaborators->sole()->id);
    }

    #[Test]
    public function collaboratorWithPermissionCannotRemoveFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $folderOwner->id, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'CannotRemoveFolderOwner']);
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorDoesNotHavePermissionOrRole(): void
    {
        [$collaborator, $folderOwner, $collaboratorToBeKickedOut] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, UAC::all()->toCollection()->reject(Permission::REMOVE_USER->value)->all());
        $this->CreateCollaborationRecord($collaboratorToBeKickedOut, $folder);

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorToBeKickedOut->id, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'PermissionDenied']);

        $this->assertEquals([$collaborator->id, $collaboratorToBeKickedOut->id], $folder->collaborators->pluck('id')->all());
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

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        //Assert collaborator can remove bookmark when disabled action is not remove user feature
        $updateCollaboratorActionService->disable($folder->id, Feature::ADD_BOOKMARKS);
        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOut[0]->id, 'folder_id' => $folder->id])->assertOk();

        $updateCollaboratorActionService->disable($folder->id, Feature::REMOVE_USER);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsToBeKickedOut[1]->id, 'folder_id' => $folder->id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderFeatureDisAbled']);

        $this->assertCount(2, $folder->collaborators);
        $this->assertContains($collaboratorsToBeKickedOut[1]->id, $folder->collaborators->pluck('id'));
    }

    #[Test]
    public function willNotifyCollaboratorThatWasRemoved(): void
    {
        [$folderOwner, $collaborator] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->loginUser($folderOwner);
        $this->deleteCollaboratorResponse([
            'collaborator_id' => $collaborator->id,
            'folder_id' => $folder->id
        ])->assertOk();

        $notificationData = $collaborator->notifications()->sole(['data', 'type']);
        $this->assertEquals('YouHaveBeenKickedOut', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type'      => 'YouHaveBeenKickedOut',
            'version'     => '1.0.0',
            'folder_id'   => $folder->id,
            'folder_name' => $folder->name->value
        ]);
    }

    #[Test]
    public function willNotifyFolderOwner(): void
    {
        [$collaborator, $folderOwner] = UserFactory::times(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::REMOVE_USER);

        $collaboratorsKickedOut = UserFactory::times(2)
            ->create()
            ->each(fn (User $collaborator) => $this->CreateCollaborationRecord($collaborator, $folder));

        $this->loginUser($collaborator);
        $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsKickedOut[0]->id, 'folder_id' => $folder->id])->assertOk();

        //To Ensure will always sort by latest (created_at)
        $this->travel(1)->minute(function () use ($collaboratorsKickedOut, $folder) {
            $this->deleteCollaboratorResponse(['collaborator_id' => $collaboratorsKickedOut[1]->id, 'folder_id' => $folder->id, 'ban' => true])->assertOk();
        });

        $notificationsData = $folderOwner->notifications()->get(['data'])->pluck('data')->toArray();

        $json = AssertableJson::fromArray($notificationsData);

        $this->assertCount(2, $notificationsData);
        $json->where('0.was_banned', true)
            ->where('1.was_banned', false)
            ->where('0.collaborator.id', $collaboratorsKickedOut[1]->id)
            ->where('0.collaborator.name', $collaboratorsKickedOut[1]->full_name->value)
            ->each(function (AssertableJson $json) use ($folder, $collaborator) {
                $json->where('version', '1.0.0')
                    ->where('N-type', 'CollaboratorRemoved')
                    ->where('folder.id', $folder->id)
                    ->where('folder.name', $folder->name->value)
                    ->where('removed_by.id', $collaborator->id)
                    ->where('removed_by.name', $collaborator->full_name->value)
                    ->etc();
            });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Repositories\FolderInviteDataRepository;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\DataTransferObjects\FolderInviteData;
use App\Enums\ActivityType;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\DataTransferObjects\Activities\InviteAcceptedActivityLogData as ActivityLogData;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\Folder;
use App\Models\FolderCollaborator;
use App\Models\User;
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use App\ValueObjects\InviteId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Folder\Concerns\AssertFolderCollaboratorMetrics;
use Tests\Traits\CreatesCollaboration;
use Tests\Traits\CreatesRole;

class AcceptFolderInviteTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;
    use CreatesRole;
    use AssertFolderCollaboratorMetrics;

    protected FolderInviteDataRepository $tokenStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenStore = app(FolderInviteDataRepository::class);
    }

    protected function acceptInviteResponse(array $parameters = []): TestResponse
    {
        return $this->getJson(route('acceptFolderCollaborationInvite', $parameters));
    }

    public function testIsAccessibleViaPath(): void
    {
        $this->assertRouteIsAccessibleViaPath('v1/folders/invite/accept', 'acceptFolderCollaborationInvite');
    }

    public function testUnAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        //should be UnAuthorized if route is protected but returns
        // a assertUnprocessable response because we provided an invalid data.
        $this->acceptInviteResponse()->assertUnprocessable();
    }

    public function testAuthorizedUserCanAccessRoute(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse()->assertUnprocessable();
    }

    public function testUnAuthorizedClientCannotAccessRoute(): void
    {
        $this->acceptInviteResponse()->assertUnauthorized();
    }

    public function testWillReturnUnprocessableWhenParametersAreInvalid(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invite_hash' => 'The invite hash field is required.']);

        $this->acceptInviteResponse(['invite_hash' => $this->faker->word])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invite_hash' => 'The invite hash must be a valid UUID.']);
    }

    public function testWillReturnNotFoundWhenInvitationHasExpiredOrDoesNotExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $this->acceptInviteResponse(['invite_hash' => $this->faker->uuid])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'InvitationNotFoundOrExpired',
                'info'    => 'The invitation token is expired or is invalid.'
            ]);
    }

    #[Test]
    public function acceptInviteFromFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        /** @var FolderCollaborator */
        $savedRecord = $folder->collaborators->sole();

        $this->assertEquals($invitee->id, $savedRecord->collaborator_id);
        $this->assertEquals($folderOwner->id, $savedRecord->invited_by);
        $this->assertNoMetricsRecorded($folderOwner->id, $folder->id, CollaboratorMetricType::COLLABORATORS_ADDED);
    }

    #[Test]
    public function acceptInviteFromCollaborator(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee, $otherInvitee] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->create();

        $this->CreateCollaborationRecord($collaborator, $folder);

        $this->tokenStore->store(
            $inviteeToken = InviteId::generate(),
            new FolderInviteData($collaborator->id, $invitee->id, $folder->id)
        );

        $this->tokenStore->store(
            $otherInviteeToken = InviteId::generate(),
            new FolderInviteData($collaborator->id, $otherInvitee->id, $folder->id)
        );

        $this->acceptInviteResponse(['invite_hash' => $inviteeToken->value])->assertCreated();
        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::COLLABORATORS_ADDED);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeToken->value])->assertCreated();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->visibleToCollaboratorsOnly()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->private()->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'FolderIsMarkedAsPrivate',
                'info' => 'Folder has been marked as private by owner.'
            ]);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());

        Notification::assertNothingSent();
    }

    #[Test]
    public function whenFolderIsPasswordProtected(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->passwordProtected()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderIsMarkedAsPrivate',]);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    #[DataProvider('acceptInviteData')]
    public function acceptInvite(array $permissions): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        /** @var \App\Models\User */
        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                $permissions = new UAC($permissions),
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        $savedPermissions = (new CollaboratorPermissionsRepository())->all($invitee->id, $folder->id);

        /** @var \App\Models\FolderActivity */
        $activity = $folder->activities->sole();

        $this->assertEqualsCanonicalizing(
            $permissions->toArray(),
            $savedPermissions->toArray(),
        );

        $this->assertEquals($activity->type, ActivityType::NEW_COLLABORATOR);
        $this->assertEquals($activity->data, (new ActivityLogData($user, $invitee))->toArray());
    }

    public static function acceptInviteData(): array
    {
        return  [
            'No permissions'            => [[]],
            'All'                       => [UAC::all()->toArray()],
            'Add bookmarks'             => [[Permission::ADD_BOOKMARKS]],
            'Remove bookmarks'          => [[Permission::DELETE_BOOKMARKS]],
            'Invite users'              => [[Permission::INVITE_USER]],
            'Update folder name'        => [[Permission::UPDATE_FOLDER_NAME]],
            'Update folder description' => [[Permission::UPDATE_FOLDER_DESCRIPTION]],
            'Update folder icon'        => [[Permission::UPDATE_FOLDER_ICON]],
            'Add and Remove bookmarks'  => [[Permission::DELETE_BOOKMARKS, Permission::ADD_BOOKMARKS]]
        ];
    }

    #[Test]
    public function acceptInviteWithRoles(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $role = $this->createRole(folder: $folder);

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([]),
                [$role->name]
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        $savedPermissions = (new CollaboratorPermissionsRepository())->all($invitee->id, $folder->id);

        $this->assertEmpty($savedPermissions);

        $this->assertEquals($invitee->roles->sole()->id, $role->id);
        $this->assertEquals($invitee->roles->sole()->folder_id, $folder->id);
    }

    #[Test]
    public function whenAssignedRoleNoLongerExists(): void
    {
    }

    #[Test]
    public function whenInviteHasAlreadyBeenAccepted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($invitee, $folder);

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'InvitationAlreadyAccepted',
                'info' => 'The invitation has already been accepted.'
            ]);

        $this->assertTrue($folder->activities->isEmpty());
    }

    public function testWillReturnNotFoundWhenFolderHasBeenDeleted(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        Passport::actingAs($user);

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $folder->delete();

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'FolderNotFound',
                'info' => 'The folder could not be found.'
            ]);

        $this->assertDatabaseMissing(FolderCollaborator::class, ['folder_id' => $folder->id]);

        Notification::assertNothingSent();
    }

    public function testWillReturnNotFoundWhenInviteeHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $invitee->delete();

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InvitationNotFoundOrExpired']);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    public function testWillReturnNotFoundWhenInviterHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $user->delete();

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InvitationNotFoundOrExpired']);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenFolderHas_1000_collaborators(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        Folder::retrieved(function (Folder $retrieved) {
            $retrieved->collaborators_count = 1000;
        });

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'MaxCollaboratorsLimitReached',
                'info' => 'Folder has reached its max collaborators limit.'
            ]);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function willReturnForbiddenWhenCollaboratorsLimitSetByFolderOwnerIsExceeded(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($user)
            ->settings(FolderSettingsBuilder::new()->setMaxCollaboratorsLimit(12))
            ->create();

        for ($i = 0; $i < 13; $i++) {
            $this->CreateCollaborationRecord(UserFactory::new()->create(), $folder);
        }

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'MaxFolderCollaboratorsLimitReached',
                'info' => 'The Folder has reached its max collaborators limit set by the folder owner.'
            ]);

        $this->assertCount(13, $folder->collaborators);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function whenInviteeIsNotAnActiveCollaborator(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee, $collaborator, $otherInvitee] = UserFactory::times(4)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->tokenStore->store(
            $otherInviteeInviteHash = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $otherInvitee->id,
                $folder->id,
            )
        );

        // can accept new collaborators when inviter is not an active collaborator by default.
        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        $folder->settings = FolderSettingsBuilder::new()->enableCannotAcceptInviteIfInviterIsNotAnActiveCollaborator()->build();
        $folder->save();

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeInviteHash->value])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterIsNotAnActiveCollaborator']);

        /** @var FolderCollaborator */
        $soleCollaborator = $folder->collaborators->sole();

        $this->assertEquals($soleCollaborator->collaborator_id, $invitee->id);
        $this->assertCount(1, $folder->activities);
    }

    #[Test]
    public function whenInviteeRoleNoLongerExists(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee, $collaborator] = UserFactory::times(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->CreateCollaborationRecord($collaborator, $folder);
        $this->attachRoleToUser($collaborator, $role = $this->createRole(folder: $folder, permissions: Permission::INVITE_USER));

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([]),
                []
            )
        );

        $folder->settings = FolderSettingsBuilder::new()->enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission()->build();
        $folder->save();

        $role->delete();

        $this->acceptInviteResponse(['invite_hash' => $id->value])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterCanNoLongerSendInvites']);

        /** @var FolderCollaborator */
        $soleCollaborator = $folder->collaborators->sole();

        $this->assertEquals($soleCollaborator->collaborator_id, $collaborator->id);
        $this->assertTrue($folder->activities->isEmpty());
    }

    #[Test]
    public function whenCollaboratorPermissionHasBeenRevoked(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee, $collaborator, $otherInvitee] = UserFactory::times(4)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->tokenStore->store(
            $otherInviteeInviteHash = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $otherInvitee->id,
                $folder->id,
            )
        );

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        // can accept new collaborators when inviter permissions is revoked by default.
        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        $folder->settings = FolderSettingsBuilder::new()->enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission()->build();
        $folder->save();

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeInviteHash->value])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterCanNoLongerSendInvites']);

        $this->assertEqualsCanonicalizing(
            $folder->collaborators->pluck('collaborator_id')->all(),
            [$collaborator->id, $invitee->id]
        );

        $this->assertCount(1, $folder->activities);
    }

    #[Test]
    public function willReturnForbiddenResponseWhenFeatureIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        $toggleFolderFeature = new ToggleFolderFeature();

        [$collaborator, $folderOwner, $invitedByCollaborator, $invitedByFolderOwner] = UserFactory::times(4)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $toggleFolderFeature->disable($folder->id, Feature::JOIN_FOLDER);

        $this->tokenStore->store(
            $inviteIdSentToInviteeInvitedByCollaborator = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitedByCollaborator->id,
                $folder->id,
            )
        );

        $this->tokenStore->store(
            $inviteIdSentToInviteeInvitedByFolderOwner = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitedByFolderOwner->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $inviteIdSentToInviteeInvitedByCollaborator->value])
            ->assertForbidden()
            ->assertJsonFragment($expectedError = ['message' => 'FolderFeatureDisAbled']);

        $this->acceptInviteResponse(['invite_hash' => $inviteIdSentToInviteeInvitedByFolderOwner->value])
            ->assertForbidden()
            ->assertJsonFragment($expectedError);

        $this->assertTrue($folder->collaborators->isEmpty());
        $this->assertTrue($folder->activities->isEmpty());
    }

    public function testWillNotNotifyFolderOwnerWhenInvitationWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        Notification::fake();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwnerWhenInvitationWasNotSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        /** @var User */
        [$collaborator, $invitee, $folderOwner] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        /** @var \App\Models\DatabaseNotification */
        $notification = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals(10, $notification->type->value);
        $this->assertEquals($notification->data, [
            'version' => '1.0.0',
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value,
            ],
            'inviter' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value,
                'profile_image_path' => null
            ],
            'invitee' => [
                'id'        => $invitee->id,
                'full_name' => $invitee->full_name->value,
                'public_id' => $invitee->public_id->value,
                'profile_image_path' => null
            ],
        ]);
    }

    public function testWillNotNotifyFolderOwnerWhenNotificationsIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->settings(FolderSettingsBuilder::new()->disableNotifications())
            ->create();

        Notification::fake();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhenNewCollaboratorNotificationIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $settings = FolderSettingsBuilder::new()
            ->disableNewCollaboratorNotification()
            ->enableOnlyCollaboratorsInvitedByMeNotification();

        $folder = FolderFactory::new()->settings($settings)->create();

        Notification::fake();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->settings(FolderSettingsBuilder::new()->enableOnlyCollaboratorsInvitedByMeNotification())
            ->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWill_NotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled_andInviteWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableOnlyCollaboratorsInvitedByMeNotification())
            ->create();

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertSentTimes(\App\Notifications\NewCollaboratorNotification::class, 1);
    }

    #[Test]
    public function willNotNotifyFolderOwnerWhenCollaboratorIsMuted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folder->user_id);

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function willNotifyFolderOwnerWhenMuteDurationIsPast(): void
    {
        Notification::fake();

        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        /** @var MuteCollaboratorService */
        $muteCollaboratorService = app(MuteCollaboratorService::class);

        $muteCollaboratorService->mute($folder->id, $collaborator->id, $folder->user_id, now(), 1);

        $this->tokenStore->store(
            $id = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id
            )
        );

        $this->travel(61)->minutes(function () use ($id) {
            $this->acceptInviteResponse(['invite_hash' => $id->value])->assertCreated();

            Notification::assertCount(1);
        });
    }

    #[Test]
    public function willNotLogActivityWhenActivityLoggingIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $folderOwner] = UserFactory::new()->count(2)->create();

        $invitees = UserFactory::times(2)->create();

        $folder = FolderFactory::new()
            ->for($folderOwner)
            ->settings(FolderSettingsBuilder::new()->enableActivities(false))
            ->create();

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::INVITE_USER);

        $this->tokenStore->store(
            $invitationId = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitees[0]->id,
                $folder->id
            )
        );

        $this->tokenStore->store(
            $secondInvitationId = InviteId::generate(),
            new FolderInviteData(
                $collaborator->id,
                $invitees[1]->id,
                $folder->id
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $invitationId->value])->assertCreated();
        $this->acceptInviteResponse(['invite_hash' => $secondInvitationId->value])->assertCreated();

        $this->assertCount(0, $folder->activities);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Actions\ToggleFolderFeature;
use App\Cache\FolderInviteDataRepository;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
use App\DataTransferObjects\FolderInviteData;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Models\FolderCollaboratorPermission;
use App\Enums\Permission;
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $savedRecord = FolderCollaborator::where('folder_id', $folder->id)->first();

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
            $inviteeToken = $this->faker->uuid,
            new FolderInviteData($collaborator->id, $invitee->id, $folder->id)
        );

        $this->tokenStore->store(
            $otherInviteeToken = $this->faker->uuid,
            new FolderInviteData($collaborator->id, $otherInvitee->id, $folder->id)
        );

        $this->acceptInviteResponse(['invite_hash' => $inviteeToken])->assertCreated();
        $this->assertFolderCollaboratorMetric($collaborator->id, $folder->id, $type = CollaboratorMetricType::COLLABORATORS_ADDED);
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type);

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeToken])->assertCreated();
        $this->assertFolderCollaboratorMetricsSummary($collaborator->id, $folder->id, $type, 2);
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->visibleToCollaboratorsOnly()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->private()->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'FolderIsMarkedAsPrivate',
                'info' => 'Folder has been marked as private by owner.'
            ]);

        $this->assertTrue($folder->collaborators->isEmpty());

        Notification::assertNothingSent();
    }

    #[Test]
    public function whenFolderIsPasswordProtected(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->passwordProtected()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'FolderIsMarkedAsPrivate',]);
    }

    #[Test]
    #[DataProvider('acceptInviteData')]
    public function acceptInvite(array $permissions): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                $permissions = new UAC($permissions),
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $savedPermissions = (new CollaboratorPermissionsRepository())->all($invitee->id, $folder->id);

        $this->assertEqualsCanonicalizing(
            $permissions->toArray(),
            $savedPermissions->toArray(),
        );
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
            'Update folder thumbnail'   => [[Permission::UPDATE_FOLDER_THUMBNAIL]],
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([]),
                [$role->name]
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'InvitationAlreadyAccepted',
                'info' => 'The invitation has already been accepted.'
            ]);
    }

    public function testWillReturnNotFoundWhenFolderHasBeenDeleted(): void
    {
        Notification::fake();
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        Passport::actingAs($user);

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $folder->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $invitee->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InvitationNotFoundOrExpired']);

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, ['folder_id' => $folder->id]);
    }

    public function testWillReturnNotFoundWhenInviterHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $user->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'InvitationNotFoundOrExpired']);

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, ['folder_id' => $folder->id,]);
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'MaxCollaboratorsLimitReached',
                'info' => 'Folder has reached its max collaborators limit.'
            ]);
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $user->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'MaxFolderCollaboratorsLimitReached',
                'info' => 'The Folder has reached its max collaborators limit set by the folder owner.'
            ]);
    }

    #[Test]
    public function whenInviteeIsNotAnActiveCollaborator(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee, $collaborator, $otherInvitee] = UserFactory::times(4)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([]),
                []
            )
        );

        $this->tokenStore->store(
            $otherInviteeInviteHash = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $otherInvitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        // can accept new collaborators when inviter is not an active collaborator by default.
        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $folder->settings = FolderSettingsBuilder::new()->enableCannotAcceptInviteIfInviterIsNotAnActiveCollaborator()->build();
        $folder->save();

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeInviteHash])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterIsNotAnActiveCollaborator']);
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
            $id = $this->faker->uuid,
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

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterCanNoLongerSendInvites']);
    }

    #[Test]
    public function whenCollaboratorPermissionHasBeenRevoked(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee, $collaborator, $otherInvitee] = UserFactory::times(4)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->tokenStore->store(
            $otherInviteeInviteHash = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $otherInvitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->CreateCollaborationRecord($collaborator, $folder, Permission::ADD_BOOKMARKS);

        // can accept new collaborators when inviter permissions is revoked by default.
        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $folder->settings = FolderSettingsBuilder::new()->enableCannotAcceptInviteIfInviterNoLongerHasRequiredPermission()->build();
        $folder->save();

        $this->acceptInviteResponse(['invite_hash' => $otherInviteeInviteHash])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'InviterCanNoLongerSendInvites']);
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
            $inviteIdSentToInviteeInvitedByCollaborator = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitedByCollaborator->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->tokenStore->store(
            $inviteIdSentToInviteeInvitedByFolderOwner = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitedByFolderOwner->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $inviteIdSentToInviteeInvitedByCollaborator])
            ->assertForbidden()
            ->assertJsonFragment($expectedError = ['message' => 'FolderFeatureDisAbled']);

        $this->acceptInviteResponse(['invite_hash' => $inviteIdSentToInviteeInvitedByFolderOwner])
            ->assertForbidden()
            ->assertJsonFragment($expectedError);
    }

    public function testWillNotNotifyFolderOwnerWhenInvitationWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        Notification::fake();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwnerWhenInvitationWasNotSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        /** @var User */
        [$collaborator, $invitee, $folderOwner] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('CollaboratorAddedToFolder', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type' => 'CollaboratorAddedToFolder',
            'version' => '1.0.0',
            'folder'          => [
                'id'        => $folder->id,
                'public_id' => $folder->public_id->value,
                'name'      => $folder->name->value
            ],
            'collaborator' => [
                'id'        => $collaborator->id,
                'full_name' => $collaborator->full_name->value,
                'public_id' => $collaborator->public_id->value
            ],
            'new_collaborator' => [
                'id'        => $invitee->id,
                'full_name' => $invitee->full_name->value,
                'public_id' => $invitee->public_id->value
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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $folderOwner->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

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
            $id = $this->faker->uuid,
            new FolderInviteData(
                $collaborator->id,
                $invitee->id,
                $folder->id,
                new UAC([Permission::ADD_BOOKMARKS]),
                []
            )
        );

        $this->travel(61)->minutes(function () use ($id) {
            $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

            Notification::assertCount(1);
        });
    }
}

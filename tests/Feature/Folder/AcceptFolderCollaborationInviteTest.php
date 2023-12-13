<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Cache\InviteTokensStore;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
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
use App\Repositories\Folder\CollaboratorPermissionsRepository;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\CreatesCollaboration;

class AcceptFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;
    use CreatesCollaboration;

    protected InviteTokensStore $tokenStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenStore = app(InviteTokensStore::class);
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
            ->assertExactJson(['message' => 'InvitationNotFoundOrExpired']);
    }

    #[Test]
    public function whenFolderVisibilityIsPublic(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC(Permission::ADD_BOOKMARKS)
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $savedRecord = FolderCollaborator::where('folder_id', $folder->id)->first();

        $this->assertEquals($invitee->id, $savedRecord->collaborator_id);
        $this->assertEquals($folderOwner->id, $savedRecord->invited_by);
    }

    #[Test]
    public function whenFolderVisibilityIsCollaboratorsOnly(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->visibleToCollaboratorsOnly()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC(Permission::ADD_BOOKMARKS)
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();
    }

    #[Test]
    public function whenFolderVisibilityIsPrivate(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->private()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC(Permission::ADD_BOOKMARKS)
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'PrivateFolder']);
    }

    public function testAcceptInviteWithPermissions(): void
    {
        //Will give only view-bookmarks permission if no permissions were specified
        $this->assertAcceptInvite([]);
        $this->assertAcceptInvite([Permission::ADD_BOOKMARKS]);
        $this->assertAcceptInvite([Permission::DELETE_BOOKMARKS]);
        $this->assertAcceptInvite([Permission::INVITE_USER]);
        $this->assertAcceptInvite([Permission::UPDATE_FOLDER]);
        $this->assertAcceptInvite([Permission::DELETE_BOOKMARKS, Permission::ADD_BOOKMARKS]);
        $this->assertAcceptInvite(['*']);
    }

    private function assertAcceptInvite(array $permissions): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        if (in_array('*', $permissions)) {
            $permissions = UAC::all()->toArray();
        }

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            $permissions = new UAC($permissions)
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $savedPermissions = (new CollaboratorPermissionsRepository())->all($invitee->id, $folder->id);

        $this->assertEquals(array_diff($permissions->toArray(), $savedPermissions->toArray()), []);
    }

    #[Test]
    public function willReturnCreatedWhenInviteHasAlreadyBeenAccepted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->CreateCollaborationRecord($invitee, $folder);

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            UAC::all()
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();
        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();
    }

    public function testWillReturnNotFoundWhenFolderHasBeenDeleted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        Passport::actingAs($user);

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        $folder->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'FolderNotFound']);

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, ['folder_id' => $folder->id]);
    }

    public function testWillReturnNotFoundWhenInviteeHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        $invitee->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, ['folder_id' => $folder->id]);
    }

    public function testWillReturnNotFoundWhenInviterHasDeletedAccount(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        $user->delete();

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'UserNotFound']);

        $this->assertDatabaseMissing(FolderCollaboratorPermission::class, ['folder_id' => $folder->id,]);
    }

    #[Test]
    public function willReturnForbiddenWhenFolderHas_1000_collaborators(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        Folder::retrieved(function (Folder $retrieved) {
            $retrieved->collaboratorsCount = 1000;
        });

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $user->id,
            $invitee->id,
            $folder->id,
            UAC::all()
        );

        $this->acceptInviteResponse(['invite_hash' => $id])
            ->assertForbidden()
            ->assertExactJson(['message' => 'MaxCollaboratorsLimitReached']);
    }

    public function testWillNotNotifyFolderOwnerWhenInvitationWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        Notification::fake();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwnerWhenInvitationWasNotSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee, $folderOwner] = UserFactory::new()->count(3)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $notificationData = $folderOwner->notifications()->sole(['data', 'type']);

        $this->assertEquals('collaboratorAddedToFolder', $notificationData->type);
        $this->assertEquals($notificationData->data, [
            'N-type' => 'collaboratorAddedToFolder',
            'version' => '1.0.0',
            'new_collaborator_id'   => $invitee->id,
            'added_to_folder'       => $folder->id,
            'added_by_collaborator' => $collaborator->id
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
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
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
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
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
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
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
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
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
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::ADD_BOOKMARKS])
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Folder;

use App\Cache\InviteTokensStore;
use App\Models\FolderCollaboratorPermission;
use App\Enums\Permission;
use Database\Factories\FolderFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Database\Factories\ClientFactory;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Enums\FolderSettingKey;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderSetting;
use App\Services\Folder\MuteCollaboratorService;
use App\UAC;
use Database\Factories\FolderCollaboratorPermissionFactory;
use Illuminate\Testing\Assert as PHPUnit;
use PHPUnit\Framework\Attributes\Test;

class AcceptFolderCollaborationInviteTest extends TestCase
{
    use WithFaker;

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

    public function testAcceptInviteWithPermissions(): void
    {
        //Will give only view-bookmarks permission if no permissions were specified
        $this->assertWillAcceptInvite([], function (Collection $savedPermissions) {
            $this->assertCount(1, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite([Permission::ADD_BOOKMARKS], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite([Permission::DELETE_BOOKMARKS], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite([Permission::INVITE_USER], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::INVITE_USER));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite([Permission::UPDATE_FOLDER], function (Collection $savedPermissions) {
            $this->assertCount(2, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::UPDATE_FOLDER));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite([Permission::DELETE_BOOKMARKS, Permission::ADD_BOOKMARKS], function (Collection $savedPermissions) {
            $this->assertCount(3, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
        });

        $this->assertWillAcceptInvite(['*'], function (Collection $savedPermissions) {
            $this->assertCount(5, $savedPermissions);
            $this->assertTrue($savedPermissions->containsStrict(Permission::DELETE_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::ADD_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::VIEW_BOOKMARKS));
            $this->assertTrue($savedPermissions->containsStrict(Permission::UPDATE_FOLDER));
            $this->assertTrue($savedPermissions->containsStrict(Permission::INVITE_USER));
        });
    }

    private function assertWillAcceptInvite(array $permissions, \Closure $assertion): void
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
            new UAC($permissions)
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $savedPermissions = FolderCollaboratorPermission::query()->where([
            'folder_id' => $folder->id,
            'user_id' => $invitee->id,
        ])->get();

        $savedPermissionsTypes = FolderPermission::query()
            ->findMany($savedPermissions->pluck('permission_id')->all(), ['name'])
            ->pluck('name')
            ->map(fn (string $name) => Permission::from($name));

        $assertion($savedPermissionsTypes);
    }

    #[Test]
    public function willReturnCreatedWhenInviteHasAlreadyBeenAccepted(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$user, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($user)->create();

        FolderCollaboratorPermissionFactory::new()
            ->user($invitee->id)
            ->folder($folder->id)
            ->inviteUser()
            ->create();

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
            new UAC([Permission::VIEW_BOOKMARKS])
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
            new UAC([Permission::VIEW_BOOKMARKS])
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
            new UAC([Permission::VIEW_BOOKMARKS])
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
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotifyFolderOwnerWhenInvitationWasNotSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        $notificationData = DatabaseNotification::query()->where('notifiable_id', $folder->user_id)->first(['data'])->data;

        PHPUnit::assertArraySubset([
            'new_collaborator_id'   => $invitee->id,
            'added_to_folder'       => $folder->id,
            'added_by_collaborator' => $collaborator->id,
        ], $notificationData);
    }

    public function testWillNotNotifyFolderOwnerWhenNotificationsIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ENABLE_NOTIFICATIONS->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        Notification::fake();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhenNewCollaboratorNotificationIsDisabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();
        $folder = FolderFactory::new()->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::NEW_COLLABORATOR_NOTIFICATION->value,
            'value'     => false,
            'folder_id' => $folder->id
        ]);

        Notification::fake();

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWillNotNotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$collaborator, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION->value,
            'value'     => true,
            'folder_id' => $folder->id
        ]);

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $collaborator->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }

    public function testWill_NotifyFolderOwnerWhen_onlyCollaboratorsInvitedByMe_Notification_enabled_andInviteWasSentByFolderOwner(): void
    {
        Passport::actingAsClient(ClientFactory::new()->asPasswordClient()->create());

        [$folderOwner, $invitee] = UserFactory::new()->count(2)->create();

        $folder = FolderFactory::new()->for($folderOwner)->create();

        FolderSetting::create([
            'key'       => FolderSettingKey::ONLY_COLLABORATOR_INVITED_BY_USER_NOTIFICATION->value,
            'value'     => true,
            'folder_id' => $folder->id
        ]);

        $this->tokenStore->store(
            $id = $this->faker->uuid,
            $folderOwner->id,
            $invitee->id,
            $folder->id,
            new UAC([Permission::VIEW_BOOKMARKS])
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
            new UAC([Permission::VIEW_BOOKMARKS])
        );

        Notification::fake();

        $this->acceptInviteResponse(['invite_hash' => $id])->assertCreated();

        Notification::assertNothingSent();
    }
}
